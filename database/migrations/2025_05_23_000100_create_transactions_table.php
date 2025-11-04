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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('target_id');
            $table->string('server_id')->nullable();
            $table->enum('payment_method', ['qris', 'ewallet', 'bank', 'balance']);
            $table->decimal('amount', 16, 2)->unsigned();
            $table->enum('status', ['pending', 'paid', 'process', 'success', 'failed', 'refund'])->default('pending');
            $table->string('provider_trx_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
