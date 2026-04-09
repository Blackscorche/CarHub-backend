<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained();
            $table->foreignUuid('customer_id')->constrained('users');
            $table->foreignUuid('supplier_id')->constrained('suppliers');
            $table->string('protocol_number', 30)->unique();
            $table->string('insurance_name');
            $table->string('policy_number')->nullable();
            $table->enum('claim_type', ['collision', 'theft', 'natural_disaster', 'mechanical', 'glass', 'third_party', 'other']);
            $table->text('description');
            $table->json('evidence_urls')->nullable();
            $table->json('vehicle_info')->nullable();
            $table->json('protocol_data')->nullable();
            $table->enum('status', [
                'draft',
                'submitted',
                'sent_to_insurer',
                'under_analysis',
                'approved',
                'partially_approved',
                'rejected',
                'completed',
            ])->default('draft');
            $table->string('insurer_reference')->nullable();
            $table->text('insurer_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['supplier_id', 'status']);
            $table->index(['insurance_name', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claims');
    }
};
