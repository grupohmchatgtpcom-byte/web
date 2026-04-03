<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'sync_status')) {
                $table->string('sync_status', 20)->default('synced')->after('offline_uuid');
                $table->string('origin_device_id', 80)->nullable()->after('sync_status');
                $table->unsignedInteger('origin_location_id')->nullable()->after('origin_device_id');

                $table->index('sync_status', 'transactions_sync_status_idx');
                $table->index('origin_location_id', 'transactions_origin_location_id_idx');
            }
        });

        Schema::table('transaction_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('transaction_payments', 'sync_status')) {
                $table->string('sync_status', 20)->default('synced')->after('payment_uuid');
                $table->index('sync_status', 'transaction_payments_sync_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'sync_status')) {
                $table->dropIndex('transactions_sync_status_idx');
                $table->dropIndex('transactions_origin_location_id_idx');
                $table->dropColumn(['sync_status', 'origin_device_id', 'origin_location_id']);
            }
        });

        Schema::table('transaction_payments', function (Blueprint $table) {
            if (Schema::hasColumn('transaction_payments', 'sync_status')) {
                $table->dropIndex('transaction_payments_sync_status_idx');
                $table->dropColumn('sync_status');
            }
        });
    }
};
