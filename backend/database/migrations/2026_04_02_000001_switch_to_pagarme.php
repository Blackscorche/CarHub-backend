<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // suppliers: payment_gateway_id → pagarme_recipient_id
        Schema::table('suppliers', function (Blueprint $table) {
            $table->renameColumn('payment_gateway_id', 'pagarme_recipient_id');
        });

        // orders: add payment_model
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_model', ['instant', 'milestone'])->default('instant')->after('type');
        });

        // payments: gateway_transaction_id → pagarme_charge_id
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('gateway_transaction_id', 'pagarme_charge_id');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->renameColumn('pagarme_recipient_id', 'payment_gateway_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_model');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('pagarme_charge_id', 'gateway_transaction_id');
        });
    }
};
