<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number', 20)->unique();
            $table->foreignUuid('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignUuid('vehicle_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('type', ['direct', 'quote']);
            $table->enum('status', [
                'pending', 'created', 'awaiting_quote', 'quote_sent', 'quote_approved',
                'partially_paid', 'paid', 'accepted', 'rejected',
                'in_progress', 'completed', 'delivered', 'confirmed',
                'cancelled', 'disputed', 'refunded'
            ])->default('pending');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('platform_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('partial_payment_amount', 10, 2)->nullable();
            $table->decimal('remaining_amount', 10, 2)->nullable();
            $table->decimal('commission_rate', 4, 2)->default(15.00);
            $table->enum('payment_method', ['pix', 'credit_card', 'debit_card'])->nullable();
            $table->enum('delivery_type', ['pickup', 'delivery', 'on_site'])->nullable();
            $table->foreignUuid('delivery_address_id')->nullable()->constrained('addresses')->onDelete('set null');
            $table->string('confirmation_code', 6)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['supplier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
