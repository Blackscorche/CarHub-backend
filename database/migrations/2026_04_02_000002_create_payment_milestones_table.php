<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->integer('stage');
            $table->string('title', 100);
            $table->decimal('percentage', 5, 2);
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'released', 'disputed', 'refunded'])->default('pending');
            $table->json('evidence_urls')->nullable();
            $table->text('evidence_description')->nullable();
            $table->timestamp('evidence_submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('contested_at')->nullable();
            $table->text('contest_reason')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->unique(['order_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_milestones');
    }
};
