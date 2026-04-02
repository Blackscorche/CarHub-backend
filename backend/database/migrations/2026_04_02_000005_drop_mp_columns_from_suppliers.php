<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['mp_access_token', 'mp_refresh_token', 'mp_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('mp_access_token')->nullable();
            $table->string('mp_refresh_token')->nullable();
            $table->string('mp_user_id')->nullable();
        });
    }
};
