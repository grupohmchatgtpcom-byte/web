<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega columnas is_active y rate_type a ghm_bcv_rates.
     */
    public function up(): void
    {
        Schema::table('ghm_bcv_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('ghm_bcv_rates', 'is_active')) {
                $table->tinyInteger('is_active')->default(0)->after('usd_to_ves_rate')
                    ->comment('1 = tasa activa manualmente por el usuario');
            }
            if (! Schema::hasColumn('ghm_bcv_rates', 'rate_type')) {
                $table->enum('rate_type', ['bcv', 'manual', 'paralelo'])->default('bcv')->after('is_active')
                    ->comment('Origen de la tasa: bcv (automático), manual (usuario) o paralelo');
            }
        });
    }

    /**
     * Revierte las columnas agregadas.
     */
    public function down(): void
    {
        Schema::table('ghm_bcv_rates', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'rate_type']);
        });
    }
};
