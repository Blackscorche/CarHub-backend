<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\CatalogItem;
use App\Models\Supplier;
use App\Models\SupplierInsuranceTag;
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
                'insurances' => ['Porto Seguro', 'Bradesco Seguros', 'SulAmérica'],
                'catalog' => [
                    ['name' => 'Troca de óleo', 'price' => 89.90, 'type' => 'service', 'duration' => 30],
                    ['name' => 'Revisão completa', 'price' => 350.00, 'type' => 'service', 'duration' => 120],
                    ['name' => 'Troca de pastilha de freio', 'price' => 180.00, 'type' => 'service', 'duration' => 60],
                ],
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
                'insurances' => ['Liberty', 'Tokio Marine'],
                'catalog' => [
                    ['name' => 'Diagnóstico computadorizado', 'price' => 120.00, 'type' => 'service', 'duration' => 45],
                    ['name' => 'Troca de bateria', 'price' => 450.00, 'type' => 'product', 'duration' => 20],
                    ['name' => 'Instalação de alarme', 'price' => 280.00, 'type' => 'service', 'duration' => 90],
                ],
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
                'insurances' => ['Porto Seguro', 'Allianz', 'Mapfre', 'HDI'],
                'catalog' => [
                    ['name' => 'Reparo de amassado pequeno', 'price' => 200.00, 'type' => 'service', 'duration' => 60],
                    ['name' => 'Pintura parcial', 'price' => 800.00, 'type' => 'service', 'duration' => 480],
                    ['name' => 'Pintura completa', 'price' => 3500.00, 'type' => 'service', 'duration' => 2400],
                ],
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
                'insurances' => ['Porto Seguro', 'SulAmérica', 'Zurich'],
                'catalog' => [
                    ['name' => 'Alinhamento e balanceamento', 'price' => 80.00, 'type' => 'service', 'duration' => 40],
                    ['name' => 'Pneu 195/55 R15', 'price' => 320.00, 'type' => 'product', 'duration' => 30],
                    ['name' => 'Pneu 225/45 R17', 'price' => 550.00, 'type' => 'product', 'duration' => 30],
                    ['name' => 'Rodízio de pneus', 'price' => 60.00, 'type' => 'service', 'duration' => 30],
                ],
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
                'insurances' => ['Bradesco Seguros', 'Tokio Marine', 'Allianz'],
                'catalog' => [
                    ['name' => 'Lavagem detalhada', 'price' => 150.00, 'type' => 'service', 'duration' => 90],
                    ['name' => 'Cristalização', 'price' => 400.00, 'type' => 'service', 'duration' => 180],
                    ['name' => 'Vitrificação', 'price' => 1200.00, 'type' => 'service', 'duration' => 360],
                    ['name' => 'Higienização interna completa', 'price' => 250.00, 'type' => 'service', 'duration' => 120],
                ],
            ],
        ];

        foreach ($suppliers as $data) {
            $index = array_search($data, $suppliers);
            $email = $index === 0 ? 'supplier1@gmail.com' : Str::slug($data['business_name'], '.') . '@carhub.test';

            $user = User::create([
                'id' => Str::uuid(),
                'name' => $data['business_name'],
                'email' => $email,
                'password' => bcrypt('123!@#qweQWE'),
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

            $supplier = Supplier::create([
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

            // Insurance tags
            foreach ($data['insurances'] as $insurance) {
                SupplierInsuranceTag::create([
                    'id' => Str::uuid(),
                    'supplier_id' => $supplier->id,
                    'insurance_name' => $insurance,
                ]);
            }

            // Catalog items
            foreach ($data['catalog'] as $item) {
                CatalogItem::create([
                    'id' => Str::uuid(),
                    'supplier_id' => $supplier->id,
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'type' => $item['type'],
                    'estimated_duration_minutes' => $item['duration'],
                    'category' => $data['category'],
                    'is_active' => true,
                ]);
            }
        }
    }
}
