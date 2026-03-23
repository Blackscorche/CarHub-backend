<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteTest extends TestCase
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
        ], $supplierOverrides));

        return ['user' => $user, 'supplier' => $supplier];
    }

    protected function createQuoteOrder(string $customerId, string $supplierId, array $orderOverrides = [], array $quoteOverrides = []): array
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-' . date('Ymd') . '-0001',
            'customer_id' => $customerId,
            'supplier_id' => $supplierId,
            'type' => 'quote',
            'status' => 'awaiting_quote',
            'subtotal' => 0,
            'platform_fee' => 0,
            'total' => 0,
            'commission_rate' => 15.00,
            'payment_method' => 'pix',
            'delivery_type' => 'pickup',
            'confirmation_code' => 'ABC123',
        ], $orderOverrides));

        $quote = Quote::create(array_merge([
            'order_id' => $order->id,
            'supplier_id' => $supplierId,
            'customer_id' => $customerId,
            'description' => 'Preciso trocar a embreagem do meu carro, modelo 2019.',
            'status' => 'pending',
            'partial_payment_percent' => 30,
            'expires_at' => now()->addHours(48),
        ], $quoteOverrides));

        return ['order' => $order, 'quote' => $quote];
    }

    // ─── Create Quote Request ─────────────────────────────────

    public function test_customer_can_create_quote_request(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();

        $response = $this->actingAs($customer)
            ->postJson('/api/quotes', [
                'supplier_id' => $supplier->id,
                'description' => 'Preciso de um orçamento para troca de embreagem completa do meu veículo.',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('quotes', [
            'supplier_id' => $supplier->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);
    }

    public function test_quote_request_requires_description(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();

        $response = $this->actingAs($customer)
            ->postJson('/api/quotes', [
                'supplier_id' => $supplier->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_quote_description_must_be_at_least_20_chars(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();

        $response = $this->actingAs($customer)
            ->postJson('/api/quotes', [
                'supplier_id' => $supplier->id,
                'description' => 'Too short',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_supplier_cannot_create_quote_request(): void
    {
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();

        $response = $this->actingAs($supplierUser)
            ->postJson('/api/quotes', [
                'supplier_id' => $supplier->id,
                'description' => 'Supplier should not be able to create quote requests here.',
            ]);

        $response->assertStatus(403);
    }

    // ─── Supplier Respond ─────────────────────────────────────

    public function test_supplier_can_respond_to_quote(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        ['quote' => $quote] = $this->createQuoteOrder($customer->id, $supplier->id);

        $response = $this->actingAs($supplierUser)
            ->putJson("/api/quotes/{$quote->id}/respond", [
                'initial_price' => 500.00,
                'estimated_duration' => '180',
                'supplier_notes' => 'Peças inclusas.',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => 'sent',
            'initial_price' => 500.00,
        ]);
    }

    public function test_supplier_cannot_respond_twice(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        ['quote' => $quote] = $this->createQuoteOrder($customer->id, $supplier->id, [], [
            'status' => 'sent',
            'initial_price' => 500.00,
        ]);

        $response = $this->actingAs($supplierUser)
            ->putJson("/api/quotes/{$quote->id}/respond", [
                'initial_price' => 600.00,
            ]);

        $response->assertStatus(422);
    }

    public function test_other_supplier_cannot_respond_to_quote(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        ['quote' => $quote] = $this->createQuoteOrder($customer->id, $supplier->id);

        ['user' => $otherUser] = $this->createApprovedSupplier(
            ['email' => 'other@test.com'],
            ['business_name' => 'Other Shop']
        );

        $response = $this->actingAs($otherUser)
            ->putJson("/api/quotes/{$quote->id}/respond", [
                'initial_price' => 500.00,
            ]);

        $response->assertStatus(403);
    }

    // ─── Customer Approve ─────────────────────────────────────

    public function test_customer_can_approve_sent_quote(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        ['order' => $order, 'quote' => $quote] = $this->createQuoteOrder(
            $customer->id,
            $supplier->id,
            ['status' => 'quote_sent', 'total' => 500.00, 'subtotal' => 500.00],
            ['status' => 'sent', 'initial_price' => 500.00]
        );

        $response = $this->actingAs($customer)
            ->putJson("/api/quotes/{$quote->id}/approve");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => 'approved',
        ]);
    }

    public function test_cannot_approve_pending_quote(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        ['quote' => $quote] = $this->createQuoteOrder($customer->id, $supplier->id);

        $response = $this->actingAs($customer)
            ->putJson("/api/quotes/{$quote->id}/approve");

        $response->assertStatus(422);
    }

    public function test_other_customer_cannot_approve_quote(): void
    {
        $customer = $this->createCustomer();
        $other = $this->createCustomer(['email' => 'other@test.com']);
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        ['quote' => $quote] = $this->createQuoteOrder(
            $customer->id,
            $supplier->id,
            ['status' => 'quote_sent', 'total' => 500.00],
            ['status' => 'sent', 'initial_price' => 500.00]
        );

        $response = $this->actingAs($other)
            ->putJson("/api/quotes/{$quote->id}/approve");

        $response->assertStatus(403);
    }

    // ─── Supplier Adjust ──────────────────────────────────────

    public function test_supplier_can_adjust_approved_quote(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        ['order' => $order, 'quote' => $quote] = $this->createQuoteOrder(
            $customer->id,
            $supplier->id,
            ['status' => 'quote_approved', 'total' => 500.00, 'subtotal' => 500.00, 'commission_rate' => 15.00],
            ['status' => 'approved', 'initial_price' => 500.00]
        );

        $response = $this->actingAs($supplierUser)
            ->putJson("/api/quotes/{$quote->id}/adjust", [
                'final_price' => 650.00,
                'supplier_notes' => 'Peça mais cara do que esperado.',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => 'adjusted',
            'final_price' => 650.00,
        ]);
    }

    public function test_adjust_requires_final_price(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        ['quote' => $quote] = $this->createQuoteOrder(
            $customer->id,
            $supplier->id,
            ['status' => 'quote_approved'],
            ['status' => 'approved']
        );

        $response = $this->actingAs($supplierUser)
            ->putJson("/api/quotes/{$quote->id}/adjust", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['final_price']);
    }

    // ─── Final Approve ────────────────────────────────────────

    public function test_customer_can_final_approve_adjusted_quote(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        ['order' => $order, 'quote' => $quote] = $this->createQuoteOrder(
            $customer->id,
            $supplier->id,
            ['status' => 'quote_approved', 'total' => 650.00],
            ['status' => 'adjusted', 'initial_price' => 500.00, 'final_price' => 650.00]
        );

        $response = $this->actingAs($customer)
            ->putJson("/api/quotes/{$quote->id}/final-approve");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => 'final_approved',
        ]);
    }

    public function test_cannot_final_approve_non_adjusted_quote(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        ['quote' => $quote] = $this->createQuoteOrder(
            $customer->id,
            $supplier->id,
            [],
            ['status' => 'sent', 'initial_price' => 500.00]
        );

        $response = $this->actingAs($customer)
            ->putJson("/api/quotes/{$quote->id}/final-approve");

        $response->assertStatus(422);
    }

    // ─── Reject Quote ─────────────────────────────────────────

    public function test_customer_can_reject_sent_quote(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        ['order' => $order, 'quote' => $quote] = $this->createQuoteOrder(
            $customer->id,
            $supplier->id,
            ['status' => 'quote_sent'],
            ['status' => 'sent', 'initial_price' => 500.00]
        );

        $response = $this->actingAs($customer)
            ->putJson("/api/quotes/{$quote->id}/reject");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cannot_reject_pending_quote(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        ['quote' => $quote] = $this->createQuoteOrder($customer->id, $supplier->id);

        $response = $this->actingAs($customer)
            ->putJson("/api/quotes/{$quote->id}/reject");

        $response->assertStatus(422);
    }

    // ─── List & Show Quotes ───────────────────────────────────

    public function test_customer_can_list_own_quotes(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $this->createQuoteOrder($customer->id, $supplier->id);

        $response = $this->actingAs($customer)->getJson('/api/quotes');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    public function test_customer_can_show_own_quote(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        ['quote' => $quote] = $this->createQuoteOrder($customer->id, $supplier->id);

        $response = $this->actingAs($customer)->getJson("/api/quotes/{$quote->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['id' => $quote->id],
            ]);
    }

    public function test_customer_cannot_see_others_quote(): void
    {
        $customer = $this->createCustomer();
        $other = $this->createCustomer(['email' => 'other@test.com']);
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        ['quote' => $quote] = $this->createQuoteOrder($customer->id, $supplier->id);

        $response = $this->actingAs($other)->getJson("/api/quotes/{$quote->id}");

        $response->assertStatus(403);
    }
}
