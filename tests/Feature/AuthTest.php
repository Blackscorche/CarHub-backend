<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    // ─── Helpers ──────────────────────────────────────────────

    protected function customerData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'João Cliente',
            'email' => 'joao@example.com',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'phone' => '11999990000',
            'role' => 'customer',
            'lgpd_consent' => true,
        ], $overrides);
    }

    protected function supplierData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria Fornecedora',
            'email' => 'maria@supplier.com',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'phone' => '11999990001',
            'role' => 'supplier',
            'lgpd_consent' => true,
            'business_name' => 'AutoPeças Maria',
            'category' => 'mecanica',
        ], $overrides);
    }

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

    // ─── Register ─────────────────────────────────────────────

    public function test_register_customer_success(): void
    {
        $response = $this->postJson('/api/auth/register', $this->customerData());

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => ['user' => ['id', 'name', 'email', 'role'], 'token'],
                'message',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'joao@example.com',
            'role' => 'customer',
            'status' => 'active',
        ]);
    }

    public function test_register_supplier_success(): void
    {
        $response = $this->postJson('/api/auth/register', $this->supplierData());

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'email' => 'maria@supplier.com',
            'role' => 'supplier',
            'status' => 'pending_approval',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'business_name' => 'AutoPeças Maria',
            'category' => 'mecanica',
            'approval_status' => 'pending',
        ]);
    }

    public function test_register_fails_without_required_fields(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'phone', 'role', 'lgpd_consent']);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        $this->createCustomer(['email' => 'taken@test.com']);

        $response = $this->postJson('/api/auth/register', $this->customerData([
            'email' => 'taken@test.com',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_fails_with_weak_password(): void
    {
        $response = $this->postJson('/api/auth/register', $this->customerData([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_supplier_requires_business_name_and_category(): void
    {
        $data = $this->customerData(['role' => 'supplier']);

        $response = $this->postJson('/api/auth/register', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['business_name', 'category']);
    }

    // ─── Login ────────────────────────────────────────────────

    public function test_login_success(): void
    {
        $user = $this->createCustomer();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'customer@test.com',
            'password' => 'Secret1234',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => ['user' => ['id', 'name', 'email'], 'token'],
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->createCustomer();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'customer@test.com',
            'password' => 'WrongPassword1',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Credenciais inválidas.',
            ]);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nobody@test.com',
            'password' => 'Secret1234',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_fails_for_suspended_user(): void
    {
        $this->createCustomer(['status' => 'suspended']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'customer@test.com',
            'password' => 'Secret1234',
        ]);

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    // ─── Logout ───────────────────────────────────────────────

    public function test_logout_success(): void
    {
        $user = $this->createCustomer();

        // Create a real Sanctum token so currentAccessToken()->delete() works
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_logout_fails_without_auth(): void
    {
        $response = $this->deleteJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    // ─── Forgot Password ─────────────────────────────────────

    public function test_forgot_password_requires_valid_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@test.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_validation_empty(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
