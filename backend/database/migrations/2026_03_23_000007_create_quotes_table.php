<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('supplier_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('customer_id')->constrained('users')->onDelete('cascade');
            $table->text('description')->nullable();
            $table->json('media_urls')->nullable();
            $table->decimal('initial_price', 10, 2)->nullable();
            $table->decimal('final_price', 10, 2)->nullable();
            $table->integer('estimated_duration')->nullable();
            $table->text('supplier_notes')->nullable();
            $table->integer('partial_payment_percent')->default(30);
            $table->enum('status', [
                'pending', 'sent', 'approved', 'adjusted',
                'final_approved', 'rejected', 'expired'
            ])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
