<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('transaction_payments', 'currency_code')) {
            Schema::table('transaction_payments', function (Blueprint $table) {
                $table->string('currency_code', 8)->nullable()->after('amount');
            });
        }

        if (!Schema::hasColumn('transaction_payments', 'exchange_rate_used')) {
            Schema::table('transaction_payments', function (Blueprint $table) {
                $table->decimal('exchange_rate_used', 22, 6)->nullable()->after('currency_code');
            });
        }

        if (!Schema::hasColumn('transaction_payments', 'amount_in_usd')) {
            Schema::table('transaction_payments', function (Blueprint $table) {
                $table->decimal('amount_in_usd', 22, 4)->nullable()->after('exchange_rate_used');
            });
        }

        if (!Schema::hasColumn('transaction_payments', 'amount_in_bs')) {
            Schema::table('transaction_payments', function (Blueprint $table) {
                $table->decimal('amount_in_bs', 22, 4)->nullable()->after('amount_in_usd');
            });
        }

        if (!Schema::hasColumn('transaction_payments', 'is_advance')) {
            Schema::table('transaction_payments', function (Blueprint $table) {
                $table->boolean('is_advance')->default(false)->after('gateway');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('transaction_payments', 'is_advance')) {
            Schema::table('transaction_payments', function (Blueprint $table) {
                $table->dropColumn('is_advance');
            });
        }

        if (Schema::hasColumn('transaction_payments', 'amount_in_bs')) {
            Schema::table('transaction_payments', function (Blueprint $table) {
                $table->dropColumn('amount_in_bs');
            });
        }

        if (Schema::hasColumn('transaction_payments', 'amount_in_usd')) {
            Schema::table('transaction_payments', function (Blueprint $table) {
                $table->dropColumn('amount_in_usd');
            });
        }

        if (Schema::hasColumn('transaction_payments', 'exchange_rate_used')) {
            Schema::table('transaction_payments', function (Blueprint $table) {
                $table->dropColumn('exchange_rate_used');
            });
        }

        if (Schema::hasColumn('transaction_payments', 'currency_code')) {
            Schema::table('transaction_payments', function (Blueprint $table) {
                $table->dropColumn('currency_code');
            });
        }
    }
};
