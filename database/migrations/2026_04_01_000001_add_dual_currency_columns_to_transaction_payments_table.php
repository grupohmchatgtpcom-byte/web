<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega columnas de dualidad de monedas a transaction_payments.
     */
    public function up(): void
    {
        Schema::table('transaction_payments', function (Blueprint $table) {
            // Solo agregar si no existen (seguridad ante re-ejecuciones)
            if (! Schema::hasColumn('transaction_payments', 'currency_code')) {
                $table->string('currency_code', 3)->default('USD')->after('method')
                    ->comment('Moneda en que se recibió el pago: USD o VES');
            }
            if (! Schema::hasColumn('transaction_payments', 'exchange_rate_used')) {
                $table->decimal('exchange_rate_used', 20, 6)->default(1.000000)->after('currency_code')
                    ->comment('Tasa BCV activa al momento del pago');
            }
            if (! Schema::hasColumn('transaction_payments', 'amount_in_usd')) {
                $table->decimal('amount_in_usd', 22, 4)->default(0.0000)->after('exchange_rate_used')
                    ->comment('Equivalente del pago en USD');
            }
            if (! Schema::hasColumn('transaction_payments', 'amount_in_bs')) {
                $table->decimal('amount_in_bs', 22, 4)->default(0.0000)->after('amount_in_usd')
                    ->comment('Equivalente del pago en Bolívares (VES)');
            }
        });
    }

    /**
     * Revierte las columnas agregadas.
     */
    public function down(): void
    {
        Schema::table('transaction_payments', function (Blueprint $table) {
            $table->dropColumn([
                'currency_code',
                'exchange_rate_used',
                'amount_in_usd',
                'amount_in_bs',
            ]);
        });
    }
};
