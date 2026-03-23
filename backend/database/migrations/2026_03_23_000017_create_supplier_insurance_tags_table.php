<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_insurance_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained()->onDelete('cascade');
            $table->string('insurance_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_insurance_tags');
    }
};
