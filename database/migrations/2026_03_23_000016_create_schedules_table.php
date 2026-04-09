<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('order_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignUuid('customer_id')->constrained('users')->onDelete('cascade');
            $table->date('scheduled_date');
            $table->time('scheduled_time');
            $table->integer('duration_minutes')->nullable();
            $table->enum('status', ['confirmed', 'cancelled', 'completed', 'no_show'])->default('confirmed');
            $table->boolean('reminder_sent')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
