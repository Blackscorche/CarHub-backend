<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatTest extends TestCase
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

    protected function createOrder(string $customerId, string $supplierId, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-' . date('Ymd') . '-0001',
            'customer_id' => $customerId,
            'supplier_id' => $supplierId,
            'type' => 'direct',
            'status' => 'accepted',
            'subtotal' => 150.00,
            'platform_fee' => 22.50,
            'total' => 150.00,
            'commission_rate' => 15.00,
            'payment_method' => 'pix',
            'delivery_type' => 'pickup',
            'confirmation_code' => 'ABC123',
        ], $overrides));
    }

    // ─── Send Message ─────────────────────────────────────────

    public function test_customer_can_send_message(): void
    {
        Event::fake();

        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        $response = $this->actingAs($customer)
            ->postJson("/api/chat/{$order->id}/messages", [
                'message' => 'Olá, qual o prazo de entrega?',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('chat_messages', [
            'order_id' => $order->id,
            'sender_id' => $customer->id,
            'message' => 'Olá, qual o prazo de entrega?',
            'type' => 'text',
        ]);
    }

    public function test_supplier_can_send_message(): void
    {
        Event::fake();

        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        $response = $this->actingAs($supplierUser)
            ->postJson("/api/chat/{$order->id}/messages", [
                'message' => 'Prazo de 2 dias úteis.',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('chat_messages', [
            'order_id' => $order->id,
            'sender_id' => $supplierUser->id,
        ]);
    }

    public function test_send_message_requires_text(): void
    {
        Event::fake();

        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        $response = $this->actingAs($customer)
            ->postJson("/api/chat/{$order->id}/messages", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    // ─── List Messages ────────────────────────────────────────

    public function test_customer_can_list_messages(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        ChatMessage::create([
            'order_id' => $order->id,
            'sender_id' => $customer->id,
            'message' => 'Hello!',
            'type' => 'text',
        ]);

        ChatMessage::create([
            'order_id' => $order->id,
            'sender_id' => $supplierUser->id,
            'message' => 'Hi there!',
            'type' => 'text',
        ]);

        $response = $this->actingAs($customer)
            ->getJson("/api/chat/{$order->id}/messages");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data.data');
        $this->assertCount(2, $data);
    }

    public function test_supplier_can_list_messages(): void
    {
        $customer = $this->createCustomer();
        ['user' => $supplierUser, 'supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        ChatMessage::create([
            'order_id' => $order->id,
            'sender_id' => $customer->id,
            'message' => 'Hello!',
            'type' => 'text',
        ]);

        $response = $this->actingAs($supplierUser)
            ->getJson("/api/chat/{$order->id}/messages");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    // ─── Send Image Validation ────────────────────────────────

    public function test_send_image_requires_file(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        $response = $this->actingAs($customer)
            ->postJson("/api/chat/{$order->id}/images", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_send_image_rejects_invalid_mime_type(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($customer)
            ->postJson("/api/chat/{$order->id}/images", [
                'image' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_send_image_success(): void
    {
        Event::fake();
        Storage::fake('public');

        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        $file = UploadedFile::fake()->image('photo.jpg', 640, 480);

        $response = $this->actingAs($customer)
            ->postJson("/api/chat/{$order->id}/images", [
                'image' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('chat_messages', [
            'order_id' => $order->id,
            'sender_id' => $customer->id,
            'type' => 'image',
        ]);
    }

    // ─── Authorization ────────────────────────────────────────

    public function test_customer_cannot_access_others_order_chat(): void
    {
        Event::fake();

        $customer = $this->createCustomer();
        $other = $this->createCustomer(['email' => 'other@test.com']);
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($other->id, $supplier->id);

        $response = $this->actingAs($customer)
            ->getJson("/api/chat/{$order->id}/messages");

        $response->assertStatus(403);
    }

    public function test_other_supplier_cannot_access_order_chat(): void
    {
        Event::fake();

        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        ['user' => $otherUser] = $this->createApprovedSupplier(
            ['email' => 'other-supplier@test.com'],
            ['business_name' => 'Other Shop']
        );

        $response = $this->actingAs($otherUser)
            ->getJson("/api/chat/{$order->id}/messages");

        $response->assertStatus(403);
    }

    public function test_customer_cannot_send_message_to_others_order(): void
    {
        Event::fake();

        $customer = $this->createCustomer();
        $other = $this->createCustomer(['email' => 'other@test.com']);
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($other->id, $supplier->id);

        $response = $this->actingAs($customer)
            ->postJson("/api/chat/{$order->id}/messages", [
                'message' => 'Trying to sneak in.',
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_chat(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier();
        $order = $this->createOrder($customer->id, $supplier->id);

        $response = $this->getJson("/api/chat/{$order->id}/messages");

        $response->assertStatus(401);
    }
}
