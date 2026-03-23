<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            [
                'key' => 'commission_rate',
                'value' => json_encode(15),
                'description' => 'Platform commission rate (%)',
            ],
            [
                'key' => 'hold_period_hours',
                'value' => json_encode(48),
                'description' => 'Payment hold period before release to supplier (hours)',
            ],
            [
                'key' => 'partial_payment_percent',
                'value' => json_encode(30),
                'description' => 'Default partial payment percentage for quotes (%)',
            ],
            [
                'key' => 'max_dispute_days',
                'value' => json_encode(7),
                'description' => 'Maximum days to open a dispute after order completion',
            ],
            [
                'key' => 'cashback_expiry_days',
                'value' => json_encode(90),
                'description' => 'Cashback expiration period (days)',
            ],
        ];

        foreach ($configs as $config) {
            DB::table('platform_configs')->updateOrInsert(
                ['key' => $config['key']],
                array_merge($config, [
                    'id' => Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
