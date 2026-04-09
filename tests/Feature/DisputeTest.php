<?php

namespace Tests\Feature;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Dispute feature tests.
 */
#[Group('dispute')]
class DisputeTest extends TestCase
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

    protected function createAdmin(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => 'Secret1234',
            'phone' => '11999990099',
            'role' => 'admin',
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
            'status' => 'in_progress',
            'subtotal' => 300.00,
            'platform_fee' => 45.00,
            'total' => 300.00,
            'commission_rate' => 15.00,
            'payment_method' => 'pix',
            'delivery_type' => 'pickup',
            'confirmation_code' => 'ABC123',
        ], $overrides));
    }

    protected function createDispute(string $orderId, string $openedBy, array $overrides = []): Dispute
    {
        return Dispute::create(array_merge([
            'order_id' => $orderId,
            'opened_by' => $openedBy,
            'category' => 'quality',
            'description' => 'O serviço prestado ficou abaixo do esperado e incompleto.',
            'status' => 'open',
        ], $overrides));
    }

    // ─── Create Dispute ───────────────────────────────────────

    public function test_customer_can_create_dispute_on_in_progress_order(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'in_progress']);

        $response = $this->actingAs($customer)
            ->postJson('/api/disputes', [
                'order_id' => $order->id,
                'category' => 'quality',
                'description' => 'O serviço prestado ficou muito abaixo do esperado e precisa ser refeito.',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('disputes', [
            'order_id' => $order->id,
            'opened_by' => $customer->id,
            'category' => 'quality',
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'disputed',
        ]);
    }

    public function test_supplier_can_create_dispute(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'completed']);

        $response = $this->actingAs($supplierUser)
            ->postJson('/api/disputes', [
                'order_id' => $order->id,
                'category' => 'no_show',
                'description' => 'O cliente não apareceu para retirar o veículo no prazo combinado.',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_cannot_create_dispute_on_created_order(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'created']);

        $response = $this->actingAs($customer)
            ->postJson('/api/disputes', [
                'order_id' => $order->id,
                'category' => 'quality',
                'description' => 'This should fail because order is just created status.',
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_create_duplicate_dispute(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'in_progress']);

        $this->createDispute($order->id, $customer->id);

        $response = $this->actingAs($customer)
            ->postJson('/api/disputes', [
                'order_id' => $order->id,
                'category' => 'overcharge',
                'description' => 'Trying to create a second dispute for the same order.',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Já existe uma disputa para este pedido.',
            ]);
    }

    public function test_dispute_requires_description_min_20_chars(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        $response = $this->actingAs($customer)
            ->postJson('/api/disputes', [
                'order_id' => $order->id,
                'category' => 'quality',
                'description' => 'Too short',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_dispute_requires_valid_category(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        $response = $this->actingAs($customer)
            ->postJson('/api/disputes', [
                'order_id' => $order->id,
                'category' => 'invalid_category',
                'description' => 'This has an invalid category field value.',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    // ─── Authorization ────────────────────────────────────────

    public function test_unrelated_user_cannot_create_dispute(): void
    {
        $customer = $this->createCustomer();
        $other = $this->createCustomer(['email' => 'other@test.com']);
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'in_progress']);

        $response = $this->actingAs($other)
            ->postJson('/api/disputes', [
                'order_id' => $order->id,
                'category' => 'quality',
                'description' => 'Should fail since this user is not related to the order.',
            ]);

        $response->assertStatus(403);
    }

    // ─── List Disputes ────────────────────────────────────────

    public function test_customer_can_list_own_disputes(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'disputed']);
        $this->createDispute($order->id, $customer->id);

        $response = $this->actingAs($customer)->getJson('/api/disputes');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    public function test_supplier_can_list_own_disputes(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'disputed']);
        $this->createDispute($order->id, $customer->id);

        $response = $this->actingAs($supplierUser)->getJson('/api/disputes');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    public function test_customer_sees_only_own_disputes(): void
    {
        $customer = $this->createCustomer();
        $other = $this->createCustomer(['email' => 'other@test.com']);
        ['supplier' => $supplier] = $this->createApprovedSupplier();

        $order1 = $this->createOrder($customer->id, $supplier->id, ['status' => 'disputed']);
        $this->createDispute($order1->id, $customer->id);

        $order2 = $this->createOrder($other->id, $supplier->id, [
            'status' => 'disputed',
            'order_number' => 'ORD-' . date('Ymd') . '-0002',
        ]);
        $this->createDispute($order2->id, $other->id);

        $response = $this->actingAs($customer)->getJson('/api/disputes');

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    // ─── Show Dispute ─────────────────────────────────────────

    public function test_customer_can_view_own_dispute(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'disputed']);
        $dispute = $this->createDispute($order->id, $customer->id);

        $response = $this->actingAs($customer)->getJson("/api/disputes/{$dispute->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['id' => $dispute->id],
            ]);
    }

    public function test_unrelated_customer_cannot_view_dispute(): void
    {
        $customer = $this->createCustomer();
        $other = $this->createCustomer(['email' => 'other@test.com']);
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'disputed']);
        $dispute = $this->createDispute($order->id, $customer->id);

        $response = $this->actingAs($other)->getJson("/api/disputes/{$dispute->id}");

        $response->assertStatus(403);
    }

    // ─── Admin Resolve ────────────────────────────────────────

    public function test_admin_can_resolve_dispute(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'disputed']);
        $dispute = $this->createDispute($order->id, $customer->id);

        $response = $this->actingAs($admin)
            ->putJson("/api/disputes/{$dispute->id}/resolve", [
                'resolution' => 'closed',
                'admin_notes' => 'Dispute reviewed and closed.',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('disputes', [
            'id' => $dispute->id,
            'status' => 'closed',
            'resolution' => 'closed',
        ]);
    }

    public function test_customer_cannot_resolve_dispute(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'disputed']);
        $dispute = $this->createDispute($order->id, $customer->id);

        $response = $this->actingAs($customer)
            ->putJson("/api/disputes/{$dispute->id}/resolve", [
                'resolution' => 'closed',
            ]);

        $response->assertStatus(403);
    }

    public function test_supplier_cannot_resolve_dispute(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'disputed']);
        $dispute = $this->createDispute($order->id, $customer->id);

        $response = $this->actingAs($supplierUser)
            ->putJson("/api/disputes/{$dispute->id}/resolve", [
                'resolution' => 'closed',
            ]);

        $response->assertStatus(403);
    }

    public function test_resolve_requires_valid_resolution(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'disputed']);
        $dispute = $this->createDispute($order->id, $customer->id);

        $response = $this->actingAs($admin)
            ->putJson("/api/disputes/{$dispute->id}/resolve", [
                'resolution' => 'invalid_value',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['resolution']);
    }

    public function test_cannot_resolve_already_resolved_dispute(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id, ['status' => 'disputed']);
        $dispute = $this->createDispute($order->id, $customer->id, [
            'status' => 'closed',
            'resolution' => 'closed',
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/disputes/{$dispute->id}/resolve", [
                'resolution' => 'resolved_refund',
            ]);

        $response->assertStatus(422);
    }
}
