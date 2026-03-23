<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashback_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->onDelete('cascade');
            $table->decimal('balance', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('cashback_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('cashback_wallets')->onDelete('cascade');
            $table->foreignUuid('order_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 10, 2);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashback_transactions');
        Schema::dropIfExists('cashback_wallets');
    }
};
