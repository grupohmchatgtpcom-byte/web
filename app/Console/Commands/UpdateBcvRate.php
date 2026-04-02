<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class UpdateBcvRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:updateBcvRate {--date=} {--force-rate=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza la tasa BCV USD->VES del dia y aplica fallback si no hay publicacion';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            if (! Schema::hasTable('ghm_bcv_rates')) {
                $this->warn('No existe la tabla ghm_bcv_rates. Ejecute primero el SQL de preparacion.');

                return 1;
            }

            $timezone = config('app.timezone', 'UTC');
            $targetDate = $this->option('date')
                ? Carbon::parse($this->option('date'), $timezone)->toDateString()
                : Carbon::now($timezone)->toDateString();

            $forcedRate = $this->option('force-rate');
            if (! empty($forcedRate)) {
                $rate = $this->normalizeRate($forcedRate);
                if ($rate <= 0) {
                    $this->error('La opcion --force-rate no tiene un formato valido.');

                    return 1;
                }

                $this->persistRate($targetDate, $rate, 'manual:cli', 0, 'Tasa forzada por consola');
                $this->info("Tasa BCV guardada manualmente: {$rate} para {$targetDate}");

                return 0;
            }

            $bcvUrl = config('services.bcv.url', 'https://www.bcv.org.ve/estadisticas/tipo-cambio-de-referencia-smc');
            $verifySsl = (bool) config('services.bcv.verify_ssl', true);
            $allowInsecureFallback = (bool) config('services.bcv.allow_insecure_fallback', true);

            $requestBuilder = Http::withHeaders([
                'Accept-Language' => 'es-VE,es;q=0.9',
                'User-Agent' => 'GHM-HENRY/1.0 (+https://www.bcv.org.ve/)',
            ])->timeout(25)->retry(2, 1200);

            $response = null;
            $responseBody = null;
            try {
                $response = $verifySsl
                    ? $requestBuilder->get($bcvUrl)
                    : $requestBuilder->withoutVerifying()->get($bcvUrl);
            } catch (\Throwable $e) {
                $isSslError = str_contains($e->getMessage(), 'cURL error 60');

                if ($isSslError && $allowInsecureFallback) {
                    $this->warn('Fallo SSL al consultar BCV. Reintentando sin verificacion SSL por configuracion.');
                    try {
                        $response = $requestBuilder->withoutVerifying()->get($bcvUrl);
                    } catch (\Throwable $fallbackException) {
                        // Fallback final para entornos cPanel con cadena de certificados incompleta.
                        $responseBody = $this->fetchBcvHtmlWithCurlInsecure($bcvUrl);
                        if (empty($responseBody)) {
                            throw $fallbackException;
                        }
                    }
                } else {
                    throw $e;
                }
            }

            if (empty($responseBody) && ! empty($response) && $response->successful()) {
                $responseBody = $response->body();
            }

            if (! empty($responseBody)) {
                $html = $responseBody;
                $parsed = $this->extractUsdRateAndDate($html);

                if (! empty($parsed['rate']) && $parsed['rate'] > 0) {
                    $sourceDate = ! empty($parsed['source_date']) ? $parsed['source_date'] : $targetDate;
                    $note = 'Tasa obtenida automaticamente desde BCV. Fecha BCV: '.$sourceDate;

                    $this->persistRate($targetDate, (float) $parsed['rate'], 'bcv:web', 0, $note);
                    $this->info("Tasa BCV actualizada: {$parsed['rate']} para {$targetDate} (fecha BCV: {$sourceDate})");

                    return 0;
                }
            }

            $lastRate = DB::table('ghm_bcv_rates')
                ->whereNotNull('usd_to_ves_rate')
                ->orderByDesc('rate_date')
                ->first();

            if (! empty($lastRate) && ! empty($lastRate->usd_to_ves_rate)) {
                $source = ! empty($lastRate->source) ? $lastRate->source : 'bcv:last-known';
                $note = 'Fallback automatico desde '.$lastRate->rate_date;

                $this->persistRate($targetDate, $lastRate->usd_to_ves_rate, $source, 1, $note);
                $this->warn("No se pudo leer BCV en linea. Se aplico fallback {$lastRate->usd_to_ves_rate} para {$targetDate}");

                return 0;
            }

            $this->error('No se pudo obtener tasa BCV ni existe tasa previa para fallback.');

            return 1;
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());
            $this->error('Error al actualizar BCV: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Ultimo recurso para leer BCV en hosting con problemas de CA bundle.
     */
    protected function fetchBcvHtmlWithCurlInsecure(string $url): ?string
    {
        if (! function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => [
                'Accept-Language: es-VE,es;q=0.9',
                'User-Agent: GHM-HENRY/1.0 (+https://www.bcv.org.ve/)',
            ],
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hasCurlError = curl_errno($ch) !== 0;
        curl_close($ch);

        if ($hasCurlError || $httpCode >= 400 || empty($body)) {
            return null;
        }

        return (string) $body;
    }

    /**
     * Extrae la tasa USD desde el HTML del BCV.
     */
    protected function extractUsdRateAndDate(string $html): array
    {
        $plainText = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plainText = preg_replace('/\s+/u', ' ', $plainText);

        $patterns = [
            '/USD\s*([0-9\.,]+)\s*Fecha\s*Valor:\s*([^\|]+?)(?:\s[A-Z]{3}\s*[0-9]|\s*Tasas\s+Informativas|$)/iu',
            '/USD\s*([0-9\.,]+)\s*Fecha\s*Valor\s*:?\s*([^\n\r<]+)/iu',
            '/USD\s*([0-9\.,]+)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $plainText, $matches)) {
                $rate = $this->normalizeRate((string) ($matches[1] ?? ''));
                if ($rate <= 0) {
                    continue;
                }

                $sourceDate = null;
                if (! empty($matches[2])) {
                    $sourceDate = $this->parseBcvDate((string) $matches[2]);
                }

                return [
                    'rate' => $rate,
                    'source_date' => $sourceDate,
                ];
            }
        }

        return [
            'rate' => 0,
            'source_date' => null,
        ];
    }

    /**
     * Convierte fecha BCV tipo "Lunes, 23 Marzo 2026" a YYYY-MM-DD.
     */
    protected function parseBcvDate(string $rawText): ?string
    {
        $text = trim($rawText);
        if ($text === '') {
            return null;
        }

        $parts = preg_split('/\s*,\s*/u', $text);
        $base = count($parts) > 1 ? trim((string) $parts[1]) : trim((string) $parts[0]);
        $base = preg_replace('/\s+/u', ' ', $base);

        if (! preg_match('/^(\d{1,2})\s+([[:alpha:]áéíóúñ]+)\s+(\d{4})$/iu', $base, $matches)) {
            return null;
        }

        $months = [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ];

        $day = (int) $matches[1];
        $monthText = mb_strtolower(trim((string) $matches[2]));
        $monthText = strtr($monthText, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        $year = (int) $matches[3];

        if (! isset($months[$monthText])) {
            return null;
        }

        $month = $months[$monthText];
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Normaliza tasas con formatos 457,07570000 o 36.123,45.
     */
    protected function normalizeRate(string $rawRate): float
    {
        $clean = trim($rawRate);
        $hasComma = strpos($clean, ',') !== false;
        $hasDot = strpos($clean, '.') !== false;

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($clean, ',');
            $lastDot = strrpos($clean, '.');

            if ($lastComma > $lastDot) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($hasComma) {
            $clean = str_replace(',', '.', $clean);
        }

        return (float) $clean;
    }

    /**
     * Inserta o actualiza la tasa del dia.
     */
    protected function persistRate(string $targetDate, float $rate, string $source, int $isFallback, string $note): void
    {
        $now = Carbon::now();
        $table = 'ghm_bcv_rates';
        $existing = DB::table($table)->where('rate_date', $targetDate)->first();

        $payload = [];

        if (Schema::hasColumn($table, 'usd_to_ves_rate')) {
            $payload['usd_to_ves_rate'] = $rate;
        }
        if (Schema::hasColumn($table, 'usd_to_bs')) {
            $payload['usd_to_bs'] = $rate;
        }
        if (Schema::hasColumn($table, 'source')) {
            $payload['source'] = $source;
        }
        if (Schema::hasColumn($table, 'source_url')) {
            $payload['source_url'] = config('services.bcv.url');
        }
        if (Schema::hasColumn($table, 'is_official')) {
            $payload['is_official'] = str_starts_with($source, 'bcv:') ? 1 : 0;
        }
        if (Schema::hasColumn($table, 'is_fallback')) {
            $payload['is_fallback'] = $isFallback;
        }
        if (Schema::hasColumn($table, 'raw_payload')) {
            $payload['raw_payload'] = json_encode(['note' => $note], JSON_UNESCAPED_UNICODE);
        }
        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = $now;
        }

        if (empty($existing)) {
            if (Schema::hasColumn($table, 'rate_date')) {
                $payload['rate_date'] = $targetDate;
            }
            if (Schema::hasColumn($table, 'created_at')) {
                $payload['created_at'] = $now;
            }
            DB::table($table)->insert($payload);
        } else {
            DB::table($table)->where('rate_date', $targetDate)->update($payload);
        }
    }
}
