<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la columna ghm_bcv_rate a transactions para registrar
     * la tasa BCV activa al momento de crear/editar cada venta.
     * Se usa decimal(20,6) para soportar tasas venezolanas altas (ej. 58000.00).
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'ghm_bcv_rate')) {
                $table->decimal('ghm_bcv_rate', 20, 6)
                      ->default(1.000000)
                      ->nullable()
                      ->after('exchange_rate')
                      ->comment('Tasa BCV (Bs/USD) activa al momento de la transacción');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'ghm_bcv_rate')) {
                $table->dropColumn('ghm_bcv_rate');
            }
        });
    }
};
