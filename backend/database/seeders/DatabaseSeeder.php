<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            PlatformConfigSeeder::class,
            SupplierSeeder::class,
            CustomerSeeder::class,
        ]);
    }
}
