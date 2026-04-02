<?php
/**
 * GHM Schema Updater — Aplica las 3 migraciones de dualidad de monedas.
 *
 * INSTRUCCIONES:
 *  1. Sube este archivo al cPanel en: public_html/ghm_schema_update.php
 *  2. Accede desde el navegador: https://prueba.grupohmvenezuela.com/ghm_schema_update.php?token=GHM2026
 *  3. Verifica que todo diga OK
 *  4. ¡ELIMINA ESTE ARCHIVO DEL SERVIDOR INMEDIATAMENTE DESPUÉS!
 *
 * SEGURIDAD: No dejes este archivo en el servidor más de lo necesario.
 */

// ─── Token de acceso ──────────────────────────────────────────────────────────
define('ACCESS_TOKEN', 'GHM2026');

if (empty($_GET['token']) || $_GET['token'] !== ACCESS_TOKEN) {
    http_response_code(403);
    die('<h2 style="color:red;font-family:monospace">403 — Acceso denegado. Requiere ?token=GHM2026</h2>');
}

// ─── Leer credenciales del .env ───────────────────────────────────────────────
function readEnv(string $path): array
{
    $env = [];
    if (!file_exists($path)) {
        return $env;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $env[trim($key)] = $value;
    }
    return $env;
}

$envPath = dirname(__DIR__) . '/.env';
$env     = readEnv($envPath);

$dbHost = $env['DB_HOST']     ?? '127.0.0.1';
$dbPort = $env['DB_PORT']     ?? '3306';
$dbName = $env['DB_DATABASE'] ?? '';
$dbUser = $env['DB_USERNAME'] ?? '';
$dbPass = $env['DB_PASSWORD'] ?? '';

// ─── Conectar a MySQL ─────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('<div style="color:red;font-family:monospace"><b>ERROR de conexión:</b> ' . htmlspecialchars($e->getMessage()) . '</div>');
}

// ─── Helpers de salida ────────────────────────────────────────────────────────
function ok(string $msg): void
{
    echo '<li style="color:green">✅ ' . htmlspecialchars($msg) . '</li>';
}
function skip(string $msg): void
{
    echo '<li style="color:#999">⏭ ' . htmlspecialchars($msg) . ' (ya existe, omitido)</li>';
}
function fail(string $msg): void
{
    echo '<li style="color:red">❌ ' . htmlspecialchars($msg) . '</li>';
}

function columnExists(PDO $pdo, string $table, string $column, string $db): bool
{
    $sql = "SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND COLUMN_NAME = :col";
    $st  = $pdo->prepare($sql);
    $st->execute([':db' => $db, ':table' => $table, ':col' => $column]);
    return (int) $st->fetchColumn() > 0;
}

function migrationApplied(PDO $pdo, string $name): bool
{
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = :name");
        $st->execute([':name' => $name]);
        return (int) $st->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function recordMigration(PDO $pdo, string $name): void
{
    try {
        $st = $pdo->prepare("SELECT MAX(batch) FROM migrations");
        $st->execute();
        $maxBatch = (int) $st->fetchColumn();
        $batch = $maxBatch + 1;

        $ins = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (:name, :batch)");
        $ins->execute([':name' => $name, ':batch' => $batch]);
    } catch (PDOException $e) {
        // ignorar si ya existe
    }
}

// ─── Función de ejecución de SQL con reporte ─────────────────────────────────
function runAlter(PDO $pdo, string $sql, string $label): bool
{
    try {
        $pdo->exec($sql);
        ok($label);
        return true;
    } catch (PDOException $e) {
        fail($label . ' — ' . $e->getMessage());
        return false;
    }
}

// ─── Inicio de salida HTML ────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>GHM Schema Updater</title>
<style>
  body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 30px; }
  h1   { color: #4fc1ff; }
  h2   { color: #ce9178; margin-top: 30px; }
  ul   { list-style: none; padding: 0; line-height: 2; }
  .box { background: #252526; border: 1px solid #3c3c3c; border-radius: 6px; padding: 20px; margin-top: 15px; }
  .warn { color: #f5a623; background: #3a2f00; border: 1px solid #f5a623; padding: 12px; border-radius: 4px; margin-top: 20px; }
</style>
</head>
<body>
<h1>🛠 GHM Schema Updater — prueba.grupohmvenezuela.com</h1>
<p>Base de datos: <b><?= htmlspecialchars($dbName) ?></b> | Host: <b><?= htmlspecialchars($dbHost) ?></b></p>

<div class="box">
<ul>

<?php

// ═══════════════════════════════════════════════════════════════════════════
// MIGRACIÓN 1: transaction_payments — columnas de dualidad de monedas
// ═══════════════════════════════════════════════════════════════════════════
echo '</ul><h2>Migración 1 — transaction_payments (dualidad de monedas)</h2><ul>';

$m1 = '2026_04_01_000001_add_dual_currency_columns_to_transaction_payments_table';

$cols1 = [
    'currency_code'      => "ALTER TABLE `transaction_payments` ADD COLUMN `currency_code` VARCHAR(3) DEFAULT 'USD' NULL COMMENT 'Moneda del pago: USD o VES' AFTER `note`",
    'exchange_rate_used' => "ALTER TABLE `transaction_payments` ADD COLUMN `exchange_rate_used` DECIMAL(20,6) DEFAULT 1.000000 NULL COMMENT 'Tasa BCV usada al registrar el pago' AFTER `currency_code`",
    'amount_in_usd'      => "ALTER TABLE `transaction_payments` ADD COLUMN `amount_in_usd` DECIMAL(22,4) DEFAULT 0.0000 NULL COMMENT 'Equivalente en USD' AFTER `exchange_rate_used`",
    'amount_in_bs'       => "ALTER TABLE `transaction_payments` ADD COLUMN `amount_in_bs` DECIMAL(22,4) DEFAULT 0.0000 NULL COMMENT 'Equivalente en Bolívares' AFTER `amount_in_usd`",
];

$m1Applied = true;
foreach ($cols1 as $col => $sql) {
    if (columnExists($pdo, 'transaction_payments', $col, $dbName)) {
        skip("transaction_payments.{$col}");
    } else {
        if (!runAlter($pdo, $sql, "Añadir transaction_payments.{$col}")) {
            $m1Applied = false;
        }
    }
}

if ($m1Applied && !migrationApplied($pdo, $m1)) {
    recordMigration($pdo, $m1);
    ok("Registro de migración '{$m1}' añadido");
} elseif (migrationApplied($pdo, $m1)) {
    skip("Registro de migración '{$m1}'");
}

// ═══════════════════════════════════════════════════════════════════════════
// MIGRACIÓN 2: ghm_bcv_rates — columnas is_active y rate_type
// ═══════════════════════════════════════════════════════════════════════════
echo '</ul><h2>Migración 2 — ghm_bcv_rates (is_active, rate_type)</h2><ul>';

$m2 = '2026_04_01_000002_add_rate_type_columns_to_ghm_bcv_rates_table';

$cols2 = [
    'is_active'  => "ALTER TABLE `ghm_bcv_rates` ADD COLUMN `is_active` TINYINT(1) DEFAULT 0 NULL COMMENT 'Indica si esta tasa es la activa actual' AFTER `usd_to_ves_rate`",
    'rate_type'  => "ALTER TABLE `ghm_bcv_rates` ADD COLUMN `rate_type` ENUM('bcv','manual','paralelo') DEFAULT 'bcv' NULL COMMENT 'Tipo de tasa' AFTER `is_active`",
];

$m2Applied = true;
foreach ($cols2 as $col => $sql) {
    if (columnExists($pdo, 'ghm_bcv_rates', $col, $dbName)) {
        skip("ghm_bcv_rates.{$col}");
    } else {
        if (!runAlter($pdo, $sql, "Añadir ghm_bcv_rates.{$col}")) {
            $m2Applied = false;
        }
    }
}

if ($m2Applied && !migrationApplied($pdo, $m2)) {
    recordMigration($pdo, $m2);
    ok("Registro de migración '{$m2}' añadido");
} elseif (migrationApplied($pdo, $m2)) {
    skip("Registro de migración '{$m2}'");
}

// ═══════════════════════════════════════════════════════════════════════════
// MIGRACIÓN 3: transactions — columna ghm_bcv_rate
// ═══════════════════════════════════════════════════════════════════════════
echo '</ul><h2>Migración 3 — transactions (ghm_bcv_rate)</h2><ul>';

$m3 = '2026_04_01_000003_add_ghm_bcv_rate_to_transactions_table';

if (columnExists($pdo, 'transactions', 'ghm_bcv_rate', $dbName)) {
    skip('transactions.ghm_bcv_rate');
} else {
    runAlter(
        $pdo,
        "ALTER TABLE `transactions` ADD COLUMN `ghm_bcv_rate` DECIMAL(20,6) DEFAULT 1.000000 NULL
         COMMENT 'Tasa BCV (Bs/USD) activa al momento de la transacción' AFTER `exchange_rate`",
        'Añadir transactions.ghm_bcv_rate'
    );
}

if (!migrationApplied($pdo, $m3)) {
    recordMigration($pdo, $m3);
    ok("Registro de migración '{$m3}' añadido");
} else {
    skip("Registro de migración '{$m3}'");
}

// ═══════════════════════════════════════════════════════════════════════════
// VERIFICACIÓN FINAL
// ═══════════════════════════════════════════════════════════════════════════
echo '</ul><h2>Verificación final</h2><ul>';

$checks = [
    ['transaction_payments', 'currency_code'],
    ['transaction_payments', 'exchange_rate_used'],
    ['transaction_payments', 'amount_in_usd'],
    ['transaction_payments', 'amount_in_bs'],
    ['ghm_bcv_rates',        'is_active'],
    ['ghm_bcv_rates',        'rate_type'],
    ['transactions',         'ghm_bcv_rate'],
];

foreach ($checks as [$table, $col]) {
    if (columnExists($pdo, $table, $col, $dbName)) {
        ok("{$table}.{$col} — existe ✓");
    } else {
        fail("{$table}.{$col} — NO EXISTE");
    }
}

?>

</ul>
</div>

<div class="warn">
  ⚠️ <b>IMPORTANTE:</b> Elimina este archivo del servidor inmediatamente después de ejecutarlo.<br>
  <code>Ruta en cPanel: public_html/ghm_schema_update.php</code>
</div>

</body>
</html>
