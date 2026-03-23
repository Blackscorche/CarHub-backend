<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignUuid('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('supplier_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('rating')->unsigned();
            $table->text('comment')->nullable();
            $table->json('media_urls')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
