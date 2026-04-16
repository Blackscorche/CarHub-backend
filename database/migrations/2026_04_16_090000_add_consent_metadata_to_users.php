<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('lgpd_consent_ip', 45)->nullable()->after('terms_version');
            $table->string('lgpd_consent_device', 500)->nullable()->after('lgpd_consent_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['lgpd_consent_ip', 'lgpd_consent_device']);
        });
    }
};
