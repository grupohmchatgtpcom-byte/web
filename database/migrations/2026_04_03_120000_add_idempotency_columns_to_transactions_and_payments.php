<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'offline_uuid')) {
                $table->string('offline_uuid', 64)->nullable()->after('invoice_token');
                $table->index('offline_uuid', 'transactions_offline_uuid_idx');
                $table->unique(['business_id', 'offline_uuid'], 'transactions_business_offline_uuid_unique');
            }
        });

        Schema::table('transaction_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('transaction_payments', 'payment_uuid')) {
                $table->string('payment_uuid', 64)->nullable()->after('payment_ref_no');
                $table->index('payment_uuid', 'transaction_payments_payment_uuid_idx');
                $table->unique(['business_id', 'payment_uuid'], 'transaction_payments_business_payment_uuid_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'offline_uuid')) {
                $table->dropUnique('transactions_business_offline_uuid_unique');
                $table->dropIndex('transactions_offline_uuid_idx');
                $table->dropColumn('offline_uuid');
            }
        });

        Schema::table('transaction_payments', function (Blueprint $table) {
            if (Schema::hasColumn('transaction_payments', 'payment_uuid')) {
                $table->dropUnique('transaction_payments_business_payment_uuid_unique');
                $table->dropIndex('transaction_payments_payment_uuid_idx');
                $table->dropColumn('payment_uuid');
            }
        });
    }
};
