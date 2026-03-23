<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('opened_by')->constrained('users')->onDelete('cascade');
            $table->enum('category', ['quality', 'incomplete', 'overcharge', 'no_show', 'damage', 'other']);
            $table->text('description');
            $table->json('evidence_urls')->nullable();
            $table->enum('status', [
                'open', 'under_review', 'resolved_refund',
                'resolved_partial', 'resolved_released', 'closed'
            ])->default('open');
            $table->text('admin_notes')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
