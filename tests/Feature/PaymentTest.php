<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PaymentTest extends TestCase
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
            'status' => 'created',
            'subtotal' => 200.00,
            'platform_fee' => 30.00,
            'total' => 200.00,
            'commission_rate' => 15.00,
            'payment_method' => 'pix',
            'delivery_type' => 'pickup',
            'confirmation_code' => 'ABC123',
        ], $overrides));
    }

    protected function createPayment(string $orderId, string $payerId, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'order_id' => $orderId,
            'payer_id' => $payerId,
            'amount' => 200.00,
            'platform_fee' => 30.00,
            'supplier_amount' => 170.00,
            'method' => 'pix',
            'type' => 'full',
            'status' => 'pending',
        ], $overrides));
    }

    // ─── Checkout Validation ──────────────────────────────────

    public function test_checkout_requires_order_id_and_payment_method(): void
    {
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer)
            ->postJson('/api/payments/checkout', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_id', 'payment_method']);
    }

    public function test_checkout_requires_valid_payment_method(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        $response = $this->actingAs($customer)
            ->postJson('/api/payments/checkout', [
                'order_id' => $order->id,
                'payment_method' => 'bitcoin',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_checkout_requires_card_token_for_credit_card(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        $response = $this->actingAs($customer)
            ->postJson('/api/payments/checkout', [
                'order_id' => $order->id,
                'payment_method' => 'credit_card',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['card_token']);
    }

    public function test_checkout_forbidden_for_other_customer_order(): void
    {
        $customer = $this->createCustomer();
        $other = $this->createCustomer(['email' => 'other@test.com']);
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($other->id, $supplier->id);

        $response = $this->actingAs($customer)
            ->postJson('/api/payments/checkout', [
                'order_id' => $order->id,
                'payment_method' => 'pix',
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_checkout(): void
    {
        $response = $this->postJson('/api/payments/checkout', [
            'payment_method' => 'pix',
        ]);

        $response->assertStatus(401);
    }

    // ─── Payment Status ───────────────────────────────────────

    public function test_payer_can_check_payment_status(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);
        $payment = $this->createPayment($order->id, $customer->id);

        $response = $this->actingAs($customer)
            ->getJson("/api/payments/{$payment->id}/status");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'payment_id' => $payment->id,
                    'status' => 'pending',
                ],
            ]);
    }

    public function test_non_payer_cannot_check_payment_status(): void
    {
        $customer = $this->createCustomer();
        $other = $this->createCustomer(['email' => 'other@test.com']);
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);
        $payment = $this->createPayment($order->id, $customer->id);

        $response = $this->actingAs($other)
            ->getJson("/api/payments/{$payment->id}/status");

        $response->assertStatus(403);
    }

    // ─── Order Payments ───────────────────────────────────────

    public function test_customer_can_view_own_order_payments(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);
        $this->createPayment($order->id, $customer->id);

        $response = $this->actingAs($customer)
            ->getJson("/api/payments/order/{$order->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_supplier_can_view_own_order_payments(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);
        $this->createPayment($order->id, $customer->id);

        $response = $this->actingAs($supplierUser)
            ->getJson("/api/payments/order/{$order->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_other_customer_cannot_view_order_payments(): void
    {
        $customer = $this->createCustomer();
        $other = $this->createCustomer(['email' => 'other@test.com']);
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        $response = $this->actingAs($other)
            ->getJson("/api/payments/order/{$order->id}");

        $response->assertStatus(403);
    }

    // ─── Supplier Balance ─────────────────────────────────────

    public function test_supplier_can_view_balance(): void
    {
        ['user' => $supplierUser] = $this->createApprovedSupplier();

        $response = $this->actingAs($supplierUser)
            ->getJson('/api/supplier/balance');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_customer_cannot_view_supplier_balance(): void
    {
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer)
            ->getJson('/api/supplier/balance');

        $response->assertStatus(403);
    }

    // ─── Supplier Transactions ────────────────────────────────

    public function test_supplier_can_view_transactions(): void
    {
        ['user' => $supplierUser] = $this->createApprovedSupplier();

        $response = $this->actingAs($supplierUser)
            ->getJson('/api/supplier/transactions');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_customer_cannot_view_supplier_transactions(): void
    {
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer)
            ->getJson('/api/supplier/transactions');

        $response->assertStatus(403);
    }
}
