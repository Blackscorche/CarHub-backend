<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'business_name' => 'AutoMec São Paulo',
                'category' => 'mecanica',
                'description' => 'Oficina mecânica completa com mais de 15 anos de experiência. Especialistas em motores, câmbio e suspensão.',
                'latitude' => -23.5505200,
                'longitude' => -46.6333090,
                'avg_rating' => 4.7,
                'total_ratings' => 128,
                'street' => 'Rua Augusta',
                'number' => '1200',
                'neighborhood' => 'Consolação',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01304-001',
            ],
            [
                'business_name' => 'Elétrica Rápida',
                'category' => 'eletrica',
                'description' => 'Serviços elétricos automotivos. Diagnóstico computadorizado, instalação de acessórios e manutenção preventiva.',
                'latitude' => -23.5610000,
                'longitude' => -46.6450000,
                'avg_rating' => 4.5,
                'total_ratings' => 87,
                'street' => 'Av. Paulista',
                'number' => '800',
                'neighborhood' => 'Bela Vista',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01310-100',
            ],
            [
                'business_name' => 'Funilaria Express',
                'category' => 'funilaria',
                'description' => 'Funilaria e pintura de alta qualidade. Reparo de amassados, pintura automotiva e polimento.',
                'latitude' => -23.5430000,
                'longitude' => -46.6250000,
                'avg_rating' => 4.3,
                'total_ratings' => 64,
                'street' => 'Rua da Mooca',
                'number' => '500',
                'neighborhood' => 'Mooca',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '03104-001',
            ],
            [
                'business_name' => 'PneuTop Center',
                'category' => 'pneus',
                'description' => 'Centro de pneus com as melhores marcas. Alinhamento, balanceamento e troca de pneus.',
                'latitude' => -23.5700000,
                'longitude' => -46.6500000,
                'avg_rating' => 4.8,
                'total_ratings' => 203,
                'street' => 'Av. Brigadeiro Faria Lima',
                'number' => '1500',
                'neighborhood' => 'Pinheiros',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '05426-100',
            ],
            [
                'business_name' => 'Estética Car Premium',
                'category' => 'estetica',
                'description' => 'Estética automotiva premium. Lavagem detalhada, cristalização, vitrificação e higienização interna.',
                'latitude' => -23.5550000,
                'longitude' => -46.6600000,
                'avg_rating' => 4.9,
                'total_ratings' => 156,
                'street' => 'Rua Oscar Freire',
                'number' => '300',
                'neighborhood' => 'Jardins',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01426-001',
            ],
        ];

        foreach ($suppliers as $data) {
            $user = User::create([
                'id' => Str::uuid(),
                'name' => $data['business_name'],
                'email' => Str::slug($data['business_name'], '.') . '@carhub.test',
                'password' => bcrypt('password'),
                'role' => 'supplier',
                'phone' => '11' . rand(900000000, 999999999),
            ]);

            $address = Address::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'street' => $data['street'],
                'number' => $data['number'],
                'neighborhood' => $data['neighborhood'],
                'city' => $data['city'],
                'state' => $data['state'],
                'zip_code' => $data['zip_code'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'is_default' => true,
            ]);

            Supplier::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'business_name' => $data['business_name'],
                'category' => $data['category'],
                'description' => $data['description'],
                'service_radius_km' => 15,
                'address_id' => $address->id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'avg_rating' => $data['avg_rating'],
                'total_ratings' => $data['total_ratings'],
                'is_verified' => true,
                'approval_status' => 'approved',
                'approved_at' => now(),
            ]);
        }
    }
}
