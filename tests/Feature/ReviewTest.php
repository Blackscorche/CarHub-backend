<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Review;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

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
            'avg_rating' => 0,
            'total_ratings' => 0,
        ], $supplierOverrides));

        return ['user' => $user, 'supplier' => $supplier];
    }

    protected function createOrder(string $customerId, string $supplierId, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-' . date('Ymd') . '-0001',
            'customer_id' => $customerId,
            'supplier_id' => $supplierId,
            'type' => 'direct',
            'status' => 'confirmed',
            'subtotal' => 150.00,
            'platform_fee' => 22.50,
            'total' => 150.00,
            'commission_rate' => 15.00,
            'payment_method' => 'pix',
            'delivery_type' => 'pickup',
            'confirmation_code' => 'ABC123',
        ], $overrides));
    }

    // ─── Create Review ────────────────────────────────────────

    public function test_customer_can_review_confirmed_order(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'confirmed']);

        $response = $this->actingAs($customer)
            ->postJson('/api/reviews', [
                'order_id' => $order->id,
                'rating' => 5,
                'comment' => 'Excelente serviço!',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('reviews', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'supplier_id' => $supplier->id,
            'rating' => 5,
        ]);

        // Verify supplier rating was recalculated
        $supplier->refresh();
        $this->assertEquals(5.0, (float) $supplier->avg_rating);
        $this->assertEquals(1, $supplier->total_ratings);
    }

    public function test_cannot_review_non_confirmed_order(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'in_progress']);

        $response = $this->actingAs($customer)
            ->postJson('/api/reviews', [
                'order_id' => $order->id,
                'rating' => 4,
                'comment' => 'Should fail.',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Só é possível avaliar pedidos confirmados.',
            ]);
    }

    public function test_cannot_review_completed_but_unconfirmed_order(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'completed']);

        $response = $this->actingAs($customer)
            ->postJson('/api/reviews', [
                'order_id' => $order->id,
                'rating' => 3,
            ]);

        $response->assertStatus(422);
    }

    // ─── Duplicate Review Prevention ──────────────────────────

    public function test_cannot_review_same_order_twice(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'confirmed']);

        Review::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'supplier_id' => $supplier->id,
            'rating' => 5,
            'comment' => 'First review.',
        ]);

        $response = $this->actingAs($customer)
            ->postJson('/api/reviews', [
                'order_id' => $order->id,
                'rating' => 1,
                'comment' => 'Trying to add a second review.',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Este pedido já foi avaliado.',
            ]);
    }

    // ─── Authorization ────────────────────────────────────────

    public function test_cannot_review_others_order(): void
    {
        $customer = $this->createCustomer();
        $other = $this->createCustomer(['email' => 'other@test.com']);
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($other->id, $supplier->id, ['status' => 'confirmed']);

        $response = $this->actingAs($customer)
            ->postJson('/api/reviews', [
                'order_id' => $order->id,
                'rating' => 2,
            ]);

        $response->assertStatus(403);
    }

    public function test_supplier_cannot_create_review(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'confirmed']);

        $response = $this->actingAs($supplierUser)
            ->postJson('/api/reviews', [
                'order_id' => $order->id,
                'rating' => 5,
            ]);

        $response->assertStatus(403);
    }

    // ─── Validation ───────────────────────────────────────────

    public function test_review_requires_order_id_and_rating(): void
    {
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer)
            ->postJson('/api/reviews', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_id', 'rating']);
    }

    public function test_rating_must_be_between_1_and_5(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'confirmed']);

        $response = $this->actingAs($customer)
            ->postJson('/api/reviews', [
                'order_id' => $order->id,
                'rating' => 6,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_rating_must_not_be_zero(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'confirmed']);

        $response = $this->actingAs($customer)
            ->postJson('/api/reviews', [
                'order_id' => $order->id,
                'rating' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    // ─── Supplier Reviews List (public) ───────────────────────

    public function test_supplier_reviews_list(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'confirmed']);

        Review::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'supplier_id' => $supplier->id,
            'rating' => 4,
            'comment' => 'Great service!',
        ]);

        $response = $this->getJson("/api/suppliers/{$supplier->id}/reviews");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals(4, $data[0]['rating']);
    }

    public function test_supplier_reviews_list_empty(): void
    {
        ['supplier' => $supplier] = $this->createApprovedSupplier();

        $response = $this->getJson("/api/suppliers/{$supplier->id}/reviews");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data.data');
        $this->assertCount(0, $data);
    }

    // ─── Supplier Rating Recalculation ────────────────────────

    public function test_supplier_rating_updates_after_multiple_reviews(): void
    {
        $customer1 = $this->createCustomer();
        $customer2 = $this->createCustomer(['email' => 'customer2@test.com']);
        ['supplier' => $supplier] = $this->createApprovedSupplier();

        $order1 = $this->createOrder($customer1->id, $supplier->id, ['status' => 'confirmed']);
        $order2 = $this->createOrder($customer2->id, $supplier->id, [
            'status' => 'confirmed',
            'order_number' => 'ORD-' . date('Ymd') . '-0002',
        ]);

        // First review
        $this->actingAs($customer1)
            ->postJson('/api/reviews', [
                'order_id' => $order1->id,
                'rating' => 5,
            ])
            ->assertStatus(201);

        // Second review
        $this->actingAs($customer2)
            ->postJson('/api/reviews', [
                'order_id' => $order2->id,
                'rating' => 3,
            ])
            ->assertStatus(201);

        $supplier->refresh();
        $this->assertEquals(4.0, (float) $supplier->avg_rating);
        $this->assertEquals(2, $supplier->total_ratings);
    }
}
