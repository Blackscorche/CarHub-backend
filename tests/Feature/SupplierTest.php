<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CatalogItem;
use App\Models\Review;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use DatabaseTransactions;

    // ─── Helpers ──────────────────────────────────────────────

    protected function createCustomer(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'password' => 'Secret1234',
            'phone' => '11999990000',
            'role' => 'customer',
            'status' => 'active',
            'lgpd_consent' => true,
            'lgpd_consent_at' => now(),
        ], $overrides));
    }

    protected function createApprovedSupplier(array $userOverrides = [], array $supplierOverrides = []): array
    {
        $user = User::create(array_merge([
            'name' => 'Supplier User',
            'email' => 'supplier@test.com',
            'password' => 'Secret1234',
            'phone' => '11999990001',
            'role' => 'supplier',
            'status' => 'active',
            'lgpd_consent' => true,
            'lgpd_consent_at' => now(),
        ], $userOverrides));

        $supplier = Supplier::create(array_merge([
            'user_id' => $user->id,
            'business_name' => 'AutoPeças Teste',
            'category' => 'mecanica',
            'approval_status' => 'approved',
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'avg_rating' => 4.5,
            'total_ratings' => 10,
        ], $supplierOverrides));

        return ['user' => $user, 'supplier' => $supplier];
    }

    // ─── List Suppliers (public) ──────────────────────────────

    public function test_list_suppliers_returns_approved_only(): void
    {
        $this->createApprovedSupplier();

        // Create a pending supplier — should not appear
        $pendingUser = User::create([
            'name' => 'Pending Supplier',
            'email' => 'pending@test.com',
            'password' => 'Secret1234',
            'phone' => '11999990002',
            'role' => 'supplier',
            'status' => 'pending_approval',
            'lgpd_consent' => true,
            'lgpd_consent_at' => now(),
        ]);
        Supplier::create([
            'user_id' => $pendingUser->id,
            'business_name' => 'Pending Shop',
            'category' => 'pneus',
            'approval_status' => 'pending',
        ]);

        $response = $this->getJson('/api/suppliers');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data.data');
        $names = array_column($data, 'business_name');
        $this->assertContains('AutoPeças Teste', $names);
        $this->assertNotContains('Pending Shop', $names);
    }

    public function test_list_suppliers_filter_by_category(): void
    {
        $this->createApprovedSupplier(
            ['email' => 's1@test.com'],
            ['business_name' => 'Mecanica Shop', 'category' => 'mecanica']
        );
        $this->createApprovedSupplier(
            ['email' => 's2@test.com'],
            ['business_name' => 'Pneus Shop', 'category' => 'pneus']
        );

        $response = $this->getJson('/api/suppliers?category=pneus');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Pneus Shop', $data[0]['business_name']);
    }

    public function test_list_suppliers_search_by_name(): void
    {
        $this->createApprovedSupplier(
            ['email' => 's1@test.com'],
            ['business_name' => 'Oficina do João']
        );
        $this->createApprovedSupplier(
            ['email' => 's2@test.com'],
            ['business_name' => 'AutoPeças Maria']
        );

        $response = $this->getJson('/api/suppliers?search=Maria');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    // ─── Nearby Suppliers ─────────────────────────────────────

    public function test_nearby_requires_coordinates(): void
    {
        $response = $this->getJson('/api/suppliers/nearby');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_nearby_returns_suppliers_within_radius(): void
    {
        $this->createApprovedSupplier(
            ['email' => 'near@test.com'],
            ['business_name' => 'Near Shop', 'latitude' => -23.5505, 'longitude' => -46.6333]
        );

        $response = $this->getJson('/api/suppliers/nearby?latitude=-23.5500&longitude=-46.6330&radius_km=10');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    // ─── Show Supplier ────────────────────────────────────────

    public function test_show_approved_supplier(): void
    {
        ['supplier' => $supplier] = $this->createApprovedSupplier();

        $response = $this->getJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $supplier->id,
                    'business_name' => 'AutoPeças Teste',
                ],
            ]);
    }

    public function test_show_pending_supplier_returns_404(): void
    {
        $user = User::create([
            'name' => 'Pending Supplier',
            'email' => 'pending@test.com',
            'password' => 'Secret1234',
            'phone' => '11999990002',
            'role' => 'supplier',
            'status' => 'pending_approval',
            'lgpd_consent' => true,
            'lgpd_consent_at' => now(),
        ]);
        $supplier = Supplier::create([
            'user_id' => $user->id,
            'business_name' => 'Pending Shop',
            'category' => 'pneus',
            'approval_status' => 'pending',
        ]);

        $response = $this->getJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(404);
    }

    // ─── Update Profile (supplier only, approved) ─────────────

    public function test_supplier_can_update_own_profile(): void
    {
        ['user' => $user, 'supplier' => $supplier] = $this->createApprovedSupplier();

        $response = $this->actingAs($user)
            ->putJson('/api/supplier/profile', [
                'business_name' => 'Novo Nome',
                'description' => 'Melhor oficina da cidade.',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'business_name' => 'Novo Nome',
        ]);
    }

    public function test_customer_cannot_update_supplier_profile(): void
    {
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer)
            ->putJson('/api/supplier/profile', [
                'business_name' => 'Hack',
            ]);

        $response->assertStatus(403);
    }

    public function test_unapproved_supplier_cannot_update_profile(): void
    {
        $user = User::create([
            'name' => 'Pending Supplier',
            'email' => 'pending@test.com',
            'password' => 'Secret1234',
            'phone' => '11999990002',
            'role' => 'supplier',
            'status' => 'pending_approval',
            'lgpd_consent' => true,
            'lgpd_consent_at' => now(),
        ]);
        Supplier::create([
            'user_id' => $user->id,
            'business_name' => 'Pending Shop',
            'category' => 'pneus',
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/supplier/profile', [
                'business_name' => 'New Name',
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_update_supplier_profile(): void
    {
        $response = $this->putJson('/api/supplier/profile', [
            'business_name' => 'Hack',
        ]);

        $response->assertStatus(401);
    }
}
