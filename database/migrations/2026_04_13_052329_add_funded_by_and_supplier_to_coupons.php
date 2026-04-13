<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignUuid('supplier_id')->nullable()->after('category')->constrained()->nullOnDelete();
            $table->enum('funded_by', ['platform', 'supplier'])->default('platform')->after('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['supplier_id', 'funded_by']);
        });
    }
};
