<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrderTest extends TestCase
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
        ], $supplierOverrides));

        return ['user' => $user, 'supplier' => $supplier];
    }

    protected function createCatalogItem(string $supplierId, array $overrides = []): CatalogItem
    {
        return CatalogItem::create(array_merge([
            'supplier_id' => $supplierId,
            'type' => 'service',
            'name' => 'Troca de Óleo',
            'description' => 'Troca de óleo completa',
            'price' => 150.00,
            'price_type' => 'fixed',
            'category' => 'mecanica',
            'is_active' => true,
        ], $overrides));
    }

    protected function createOrder(string $customerId, string $supplierId, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-' . date('Ymd') . '-0001',
            'customer_id' => $customerId,
            'supplier_id' => $supplierId,
            'type' => 'direct',
            'status' => 'created',
            'subtotal' => 150.00,
            'platform_fee' => 22.50,
            'total' => 150.00,
            'commission_rate' => 15.00,
            'payment_method' => 'pix',
            'delivery_type' => 'pickup',
            'confirmation_code' => 'ABC123',
        ], $overrides));
    }

    // ─── Create Order ─────────────────────────────────────────

    public function test_customer_can_create_order(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $item = $this->createCatalogItem($supplier->id);

        $response = $this->actingAs($customer)
            ->postJson('/api/orders', [
                'supplier_id' => $supplier->id,
                'items' => [
                    ['catalog_item_id' => $item->id, 'quantity' => 1],
                ],
                'delivery_type' => 'pickup',
                'payment_method' => 'pix',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_create_order_requires_items(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();

        $response = $this->actingAs($customer)
            ->postJson('/api/orders', [
                'supplier_id' => $supplier->id,
                'delivery_type' => 'pickup',
                'payment_method' => 'pix',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_supplier_cannot_create_order(): void
    {
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $item = $this->createCatalogItem($supplier->id);

        $response = $this->actingAs($supplierUser)
            ->postJson('/api/orders', [
                'supplier_id' => $supplier->id,
                'items' => [
                    ['catalog_item_id' => $item->id, 'quantity' => 1],
                ],
                'delivery_type' => 'pickup',
                'payment_method' => 'pix',
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_create_order(): void
    {
        $response = $this->postJson('/api/orders', []);

        $response->assertStatus(401);
    }

    // ─── List Orders ──────────────────────────────────────────

    public function test_customer_sees_own_orders(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $this->createOrder($customer->id, $supplier->id);

        // Another customer's order
        $other = $this->createCustomer(['email' => 'other@test.com']);
        $this->createOrder($other->id, $supplier->id, [
            'order_number' => 'ORD-' . date('Ymd') . '-0002',
        ]);

        $response = $this->actingAs($customer)->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals($customer->id, $data[0]['customer_id']);
    }

    public function test_supplier_sees_own_orders(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $this->createOrder($customer->id, $supplier->id);

        $response = $this->actingAs($supplierUser)->getJson('/api/orders');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    // ─── Show Order ───────────────────────────────────────────

    public function test_customer_can_view_own_order(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        $response = $this->actingAs($customer)->getJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['id' => $order->id],
            ]);
    }

    public function test_customer_cannot_view_others_order(): void
    {
        $customer = $this->createCustomer();
        $other = $this->createCustomer(['email' => 'other@test.com']);
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($other->id, $supplier->id);

        $response = $this->actingAs($customer)->getJson("/api/orders/{$order->id}");

        $response->assertStatus(403);
    }

    // ─── Accept Order ─────────────────────────────────────────

    public function test_supplier_can_accept_created_order(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'created']);

        $response = $this->actingAs($supplierUser)
            ->putJson("/api/orders/{$order->id}/accept");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'accepted',
        ]);
    }

    public function test_cannot_accept_order_in_wrong_status(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'completed']);

        $response = $this->actingAs($supplierUser)
            ->putJson("/api/orders/{$order->id}/accept");

        $response->assertStatus(422);
    }

    // ─── Reject Order ─────────────────────────────────────────

    public function test_supplier_can_reject_order(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'created']);

        $response = $this->actingAs($supplierUser)
            ->putJson("/api/orders/{$order->id}/reject", [
                'reason' => 'Sem peças disponíveis.',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'rejected',
        ]);
    }

    public function test_reject_requires_reason(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'created']);

        $response = $this->actingAs($supplierUser)
            ->putJson("/api/orders/{$order->id}/reject", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    // ─── Start Order ──────────────────────────────────────────

    public function test_supplier_can_start_paid_order(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'paid']);

        $response = $this->actingAs($supplierUser)
            ->putJson("/api/orders/{$order->id}/start");

        $response->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_cannot_start_non_accepted_order(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'created']);

        $response = $this->actingAs($supplierUser)
            ->putJson("/api/orders/{$order->id}/start");

        $response->assertStatus(422);
    }

    // ─── Complete Order ───────────────────────────────────────

    public function test_supplier_can_complete_in_progress_order(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'in_progress']);

        $response = $this->actingAs($supplierUser)
            ->putJson("/api/orders/{$order->id}/complete");

        $response->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'completed',
        ]);
    }

    // ─── Confirm Order (customer with code) ───────────────────

    public function test_customer_can_confirm_completed_order_with_code(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, [
            'status' => 'completed',
            'confirmation_code' => 'XYZ789',
        ]);

        $response = $this->actingAs($customer)
            ->putJson("/api/orders/{$order->id}/confirm", [
                'confirmation_code' => 'XYZ789',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_confirm_fails_with_wrong_code(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, [
            'status' => 'completed',
            'confirmation_code' => 'XYZ789',
        ]);

        $response = $this->actingAs($customer)
            ->putJson("/api/orders/{$order->id}/confirm", [
                'confirmation_code' => 'WRONG1',
            ]);

        $response->assertStatus(422);
    }

    public function test_confirm_requires_confirmation_code(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'completed']);

        $response = $this->actingAs($customer)
            ->putJson("/api/orders/{$order->id}/confirm", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['confirmation_code']);
    }

    // ─── Cancel Order ─────────────────────────────────────────

    public function test_customer_can_cancel_created_order(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'created']);

        $response = $this->actingAs($customer)
            ->postJson("/api/orders/{$order->id}/cancel", [
                'reason' => 'Desisti da compra.',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cannot_cancel_in_progress_order(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'in_progress']);

        $response = $this->actingAs($customer)
            ->postJson("/api/orders/{$order->id}/cancel", [
                'reason' => 'Quero cancelar.',
            ]);

        $response->assertStatus(422);
    }

    public function test_customer_cannot_cancel_others_order(): void
    {
        $customer = $this->createCustomer();
        $other = $this->createCustomer(['email' => 'other@test.com']);
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($other->id, $supplier->id, ['status' => 'created']);

        $response = $this->actingAs($customer)
            ->postJson("/api/orders/{$order->id}/cancel", [
                'reason' => 'Not my order.',
            ]);

        $response->assertStatus(403);
    }

    // ─── Supplier authorization checks ────────────────────────

    public function test_other_supplier_cannot_accept_order(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'paid']);

        // Another supplier
        ['user' => $otherSupplierUser] = $this->createApprovedSupplier(
            ['email' => 'other-supplier@test.com'],
            ['business_name' => 'Other Shop']
        );

        $response = $this->actingAs($otherSupplierUser)
            ->putJson("/api/orders/{$order->id}/accept");

        $response->assertStatus(403);
    }
}
