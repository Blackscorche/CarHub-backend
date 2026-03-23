<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('payer_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->decimal('platform_fee', 10, 2)->default(0);
            $table->decimal('supplier_amount', 10, 2)->default(0);
            $table->enum('method', ['pix', 'credit_card', 'debit_card']);
            $table->enum('type', ['full', 'partial', 'remaining', 'refund']);
            $table->enum('status', ['pending', 'confirmed', 'held', 'released', 'refunded', 'failed'])->default('pending');
            $table->string('gateway_transaction_id')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('hold_until')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index(['status', 'hold_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
