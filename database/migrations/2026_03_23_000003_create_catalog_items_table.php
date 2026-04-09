<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['service', 'product']);
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->enum('price_type', ['fixed', 'quote_required'])->default('fixed');
            $table->integer('estimated_duration_minutes')->nullable();
            $table->string('category')->nullable();
            $table->json('image_urls')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('stock_quantity')->nullable();
            $table->boolean('requires_scheduling')->default(false);
            $table->timestamps();

            $table->index(['supplier_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
