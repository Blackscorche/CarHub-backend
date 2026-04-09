<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->onDelete('cascade');
            $table->enum('delivery_method', ['supplier', 'third_party']);
            $table->string('third_party_name')->nullable();
            $table->string('tracking_code')->nullable();
            $table->enum('status', ['preparing', 'picked_up', 'in_transit', 'delivered'])->default('preparing');
            $table->decimal('current_latitude', 10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();
            $table->timestamp('estimated_arrival')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('delivery_photo_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
