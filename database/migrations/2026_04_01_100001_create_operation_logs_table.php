<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('module', 50)->index();        // sell, purchase, payment, cash_register, expense
            $table->string('action', 30)->index();        // created, updated, deleted
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('entity_ref', 60)->nullable(); // invoice/ref number
            $table->string('location_name', 100)->nullable();
            $table->string('user_name', 100)->nullable();
            $table->decimal('amount', 22, 4)->nullable();
            $table->string('currency', 10)->nullable();
            $table->json('changes')->nullable();          // before/after snapshot
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['business_id', 'module', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
    }
};
