<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('business_name');
            $table->string('cnpj', 18)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->string('logo_url')->nullable();
            $table->enum('category', ['mecanica', 'eletrica', 'funilaria', 'pneus', 'estetica', 'pecas', 'outros']);
            $table->json('categories')->nullable();
            $table->integer('service_radius_km')->default(10);
            $table->foreignUuid('address_id')->nullable()->constrained('addresses')->onDelete('set null');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('opening_hours')->nullable();
            $table->decimal('avg_rating', 2, 1)->default(0.0);
            $table->integer('total_ratings')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->string('kyc_document_url')->nullable();
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->string('payment_gateway_id')->nullable();
            $table->text('mp_access_token')->nullable();
            $table->string('mp_refresh_token')->nullable();
            $table->string('mp_user_id')->nullable();
            $table->json('insurance_partners')->nullable();
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
            $table->index('category');
            $table->index('approval_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
