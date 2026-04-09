<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->string('bank_code', 3);
            $table->string('agencia', 10);
            $table->string('agencia_dv', 2)->nullable();
            $table->string('conta', 20);
            $table->string('conta_dv', 2);
            $table->enum('type', ['conta_corrente', 'conta_poupanca']);
            $table->enum('document_type', ['cpf', 'cnpj']);
            $table->string('document_number', 18);
            $table->string('legal_name', 255);
            $table->string('pagarme_bank_account_id')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->unique('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bank_accounts');
    }
};
