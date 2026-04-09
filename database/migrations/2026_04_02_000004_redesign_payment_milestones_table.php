<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payment_milestones');

        Schema::create('payment_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->integer('sequence');                          // 1, 2, 3... auto-increment per order
            $table->decimal('amount', 10, 2);                    // how much the customer paid this round
            $table->decimal('percentage', 5, 2);                 // percentage of order total
            $table->string('pagarme_charge_id')->nullable();     // Pagar.me charge for this payment
            $table->enum('status', [
                'paid',          // customer paid, waiting for supplier to deliver
                'delivered',     // supplier submitted evidence
                'approved',      // customer approved → money released to supplier
                'declined',      // customer declined → supplier must redo
                'contested',     // customer contested within 48h (last payment only)
                'refunded',      // admin refunded
            ])->default('paid');
            $table->boolean('is_final')->default(false);         // last payment → triggers 48h contestation
            $table->json('evidence_urls')->nullable();            // supplier proof (photos/videos)
            $table->text('evidence_description')->nullable();     // supplier notes on deliverable
            $table->timestamp('paid_at')->nullable();             // when customer paid
            $table->timestamp('delivered_at')->nullable();        // when supplier submitted evidence
            $table->timestamp('approved_at')->nullable();         // when customer approved
            $table->timestamp('declined_at')->nullable();         // when customer declined
            $table->timestamp('released_at')->nullable();         // when money was released to supplier
            $table->timestamp('contested_at')->nullable();        // when customer contested (48h window)
            $table->text('contest_reason')->nullable();           // why customer contested
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->index(['order_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_milestones');

        // Recreate old structure
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
};
