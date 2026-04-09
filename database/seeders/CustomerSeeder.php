<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'name' => 'João Silva',
                'email' => 'client1@gmail.com',
                'phone' => '11999990001',
            ],
            [
                'name' => 'Maria Santos',
                'email' => 'client2@gmail.com',
                'phone' => '11999990002',
            ],
        ];

        foreach ($customers as $data) {
            User::create([
                'id' => Str::uuid(),
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt('123!@#qweQWE'),
                'role' => 'customer',
                'phone' => $data['phone'],
            ]);
        }
    }
}
