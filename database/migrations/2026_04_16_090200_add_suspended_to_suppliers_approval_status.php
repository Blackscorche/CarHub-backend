<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE suppliers MODIFY COLUMN approval_status ENUM('pending', 'approved', 'rejected', 'suspended') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert any suspended rows back to rejected so the enum change is safe
        DB::table('suppliers')->where('approval_status', 'suspended')->update(['approval_status' => 'rejected']);
        DB::statement("ALTER TABLE suppliers MODIFY COLUMN approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");
    }
};
