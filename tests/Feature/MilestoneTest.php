<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentMilestone;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PagarmeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestoneTest extends TestCase
{
    use RefreshDatabase;

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

    protected function createApprovedSupplier(array $overrides = []): array
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
        ]));

        $supplier = Supplier::create(array_merge([
            'user_id' => $user->id,
            'business_name' => 'Mecânica Teste',
            'category' => 'mecanica',
            'approval_status' => 'approved',
            'pagarme_recipient_id' => 'rp_test_123',
        ], $overrides));

        return ['user' => $user, 'supplier' => $supplier];
    }

    protected function createMilestoneOrder(string $customerId, string $supplierId, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-' . date('Ymd') . '-0001',
            'customer_id' => $customerId,
            'supplier_id' => $supplierId,
            'type' => 'direct',
            'payment_model' => 'milestone',
            'status' => 'accepted',
            'subtotal' => 1000.00,
            'platform_fee' => 150.00,
            'total' => 1000.00,
            'commission_rate' => 15.00,
            'delivery_type' => 'on_site',
            'confirmation_code' => 'ABC123',
        ], $overrides));
    }

    protected function createMilestone(string $orderId, array $overrides = []): PaymentMilestone
    {
        return PaymentMilestone::create(array_merge([
            'order_id' => $orderId,
            'sequence' => 1,
            'amount' => 300.00,
            'percentage' => 30.00,
            'status' => 'paid',
            'is_final' => false,
            'paid_at' => now(),
        ], $overrides));
    }

    // ─── Milestone List ─────────────────────────────────────

    public function test_customer_can_view_milestones(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createMilestoneOrder($customer->id, $supplier->id);
        $this->createMilestone($order->id);

        $response = $this->actingAs($customer)
            ->getJson("/api/orders/{$order->id}/milestones");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.can_pay_more', true);
    }

    public function test_supplier_can_view_milestones(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createMilestoneOrder($customer->id, $supplier->id);
        $this->createMilestone($order->id);

        $response = $this->actingAs($supplierUser)
            ->getJson("/api/orders/{$order->id}/milestones");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_unrelated_user_cannot_view_milestones(): void
    {
        $customer = $this->createCustomer();
        $other = $this->createCustomer(['email' => 'other@test.com']);
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createMilestoneOrder($customer->id, $supplier->id);

        $response = $this->actingAs($other)
            ->getJson("/api/orders/{$order->id}/milestones");

        $response->assertStatus(403);
    }

    // ─── Evidence Upload ────────────────────────────────────

    public function test_supplier_can_submit_evidence(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createMilestoneOrder($customer->id, $supplier->id);
        $milestone = $this->createMilestone($order->id);

        $response = $this->actingAs($supplierUser)
            ->postJson("/api/milestones/{$milestone->id}/evidence", [
                'evidence' => [
                    \Illuminate\Http\UploadedFile::fake()->image('progress1.jpg'),
                ],
                'description' => 'Trabalho em andamento',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('payment_milestones', [
            'id' => $milestone->id,
            'status' => 'delivered',
        ]);
    }

    public function test_customer_cannot_submit_evidence(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createMilestoneOrder($customer->id, $supplier->id);
        $milestone = $this->createMilestone($order->id);

        $response = $this->actingAs($customer)
            ->postJson("/api/milestones/{$milestone->id}/evidence", [
                'evidence' => [
                    \Illuminate\Http\UploadedFile::fake()->image('progress1.jpg'),
                ],
            ]);

        $response->assertStatus(403);
    }

    // ─── Approve / Decline ──────────────────────────────────

    public function test_customer_can_approve_delivered_milestone(): void
    {
        // Mock Pagar.me to avoid real API calls
        $this->mock(PagarmeClient::class, function ($mock) {
            $mock->shouldReceive('post')->andReturn(['id' => 'wd_test']);
        });

        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createMilestoneOrder($customer->id, $supplier->id);
        $milestone = $this->createMilestone($order->id, [
            'status' => 'delivered',
            'evidence_urls' => json_encode(['photo1.jpg']),
            'delivered_at' => now(),
        ]);

        $response = $this->actingAs($customer)
            ->putJson("/api/milestones/{$milestone->id}/approve");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('payment_milestones', [
            'id' => $milestone->id,
            'status' => 'approved',
        ]);
    }

    public function test_customer_can_decline_delivered_milestone(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createMilestoneOrder($customer->id, $supplier->id);
        $milestone = $this->createMilestone($order->id, [
            'status' => 'delivered',
            'evidence_urls' => ['photo1.jpg'],
            'delivered_at' => now(),
        ]);

        $response = $this->actingAs($customer)
            ->putJson("/api/milestones/{$milestone->id}/decline", [
                'reason' => 'Trabalho incompleto',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('payment_milestones', [
            'id' => $milestone->id,
            'status' => 'declined',
        ]);
    }

    public function test_cannot_approve_non_delivered_milestone(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createMilestoneOrder($customer->id, $supplier->id);
        $milestone = $this->createMilestone($order->id, ['status' => 'paid']);

        $response = $this->actingAs($customer)
            ->putJson("/api/milestones/{$milestone->id}/approve");

        $response->assertStatus(422);
    }

    public function test_supplier_cannot_approve_milestone(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createMilestoneOrder($customer->id, $supplier->id);
        $milestone = $this->createMilestone($order->id, ['status' => 'delivered']);

        $response = $this->actingAs($supplierUser)
            ->putJson("/api/milestones/{$milestone->id}/approve");

        $response->assertStatus(403);
    }

    // ─── Contest ────────────────────────────────────────────

    public function test_customer_can_contest_final_approved_milestone(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createMilestoneOrder($customer->id, $supplier->id);
        $milestone = $this->createMilestone($order->id, [
            'status' => 'approved',
            'is_final' => true,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($customer)
            ->putJson("/api/milestones/{$milestone->id}/contest", [
                'reason' => 'O serviço apresentou problemas graves após a entrega.',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('payment_milestones', [
            'id' => $milestone->id,
            'status' => 'contested',
        ]);
    }

    public function test_cannot_contest_non_final_milestone(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createMilestoneOrder($customer->id, $supplier->id);
        $milestone = $this->createMilestone($order->id, [
            'status' => 'approved',
            'is_final' => false,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($customer)
            ->putJson("/api/milestones/{$milestone->id}/contest", [
                'reason' => 'Problema encontrado no serviço.',
            ]);

        $response->assertStatus(422);
    }

    public function test_contest_requires_min_20_chars_reason(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createMilestoneOrder($customer->id, $supplier->id);
        $milestone = $this->createMilestone($order->id, [
            'status' => 'approved',
            'is_final' => true,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($customer)
            ->putJson("/api/milestones/{$milestone->id}/contest", [
                'reason' => 'Short',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    // ─── Instant order cannot use milestones ────────────────

    public function test_instant_order_cannot_use_milestone_pay(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createMilestoneOrder($customer->id, $supplier->id, [
            'payment_model' => 'instant',
        ]);

        $response = $this->actingAs($customer)
            ->postJson("/api/orders/{$order->id}/milestones/pay", [
                'percentage' => 30,
                'payment_method' => 'pix',
            ]);

        $response->assertStatus(422);
    }

    // ─── Supplier Bank Account ──────────────────────────────

    public function test_supplier_can_get_bank_account_404_when_none(): void
    {
        ['user' => $supplierUser] = $this->createApprovedSupplier();

        $response = $this->actingAs($supplierUser)
            ->getJson('/api/supplier/bank-account');

        $response->assertStatus(404);
    }

    public function test_customer_cannot_access_bank_account(): void
    {
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer)
            ->getJson('/api/supplier/bank-account');

        $response->assertStatus(403);
    }
}
