<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'id' => Str::uuid(),
                'name' => 'Admin CarHub',
                'password' => 'password',
                'role' => 'admin',
                'phone' => '+5511999999999',
                'status' => 'active',
                'lgpd_consent' => true,
                'lgpd_consent_at' => now(),
            ]
        );
    }
}
