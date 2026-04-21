<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CashbackTransaction;
use App\Models\CashbackWallet;
use App\Models\Coupon;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CouponTest extends TestCase
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

    protected function createCoupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'PROMO10',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'min_order_value' => 0,
            'max_uses' => 100,
            'used_count' => 0,
            'is_active' => true,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ], $overrides));
    }

    // ─── Valid Coupon ─────────────────────────────────────────

    public function test_validate_valid_percent_coupon(): void
    {
        $customer = $this->createCustomer();
        $this->createCoupon();

        $response = $this->actingAs($customer)
            ->postJson('/api/coupons/validate', [
                'code' => 'PROMO10',
                'subtotal' => 200.00,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'valid' => true,
                    'discount' => 20.00,
                    'discount_type' => 'percent',
                    'discount_value' => 10.00,
                ],
            ]);
    }

    public function test_validate_valid_fixed_coupon(): void
    {
        $customer = $this->createCustomer();
        $this->createCoupon([
            'code' => 'FLAT50',
            'discount_type' => 'fixed',
            'discount_value' => 50.00,
        ]);

        $response = $this->actingAs($customer)
            ->postJson('/api/coupons/validate', [
                'code' => 'FLAT50',
                'subtotal' => 200.00,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'valid' => true,
                    'discount' => 50.00,
                    'discount_type' => 'fixed',
                ],
            ]);
    }

    // ─── Expired Coupon ───────────────────────────────────────

    public function test_validate_expired_coupon(): void
    {
        $customer = $this->createCustomer();
        $this->createCoupon([
            'code' => 'EXPIRED',
            'valid_from' => now()->subMonth(),
            'valid_until' => now()->subDay(),
        ]);

        $response = $this->actingAs($customer)
            ->postJson('/api/coupons/validate', [
                'code' => 'EXPIRED',
                'subtotal' => 200.00,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    // ─── Used Up Coupon ───────────────────────────────────────

    public function test_validate_used_up_coupon(): void
    {
        $customer = $this->createCustomer();
        $this->createCoupon([
            'code' => 'MAXED',
            'max_uses' => 5,
            'used_count' => 5,
        ]);

        $response = $this->actingAs($customer)
            ->postJson('/api/coupons/validate', [
                'code' => 'MAXED',
                'subtotal' => 200.00,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    // ─── Inactive Coupon ──────────────────────────────────────

    public function test_validate_inactive_coupon(): void
    {
        $customer = $this->createCustomer();
        $this->createCoupon([
            'code' => 'INACTIVE',
            'is_active' => false,
        ]);

        $response = $this->actingAs($customer)
            ->postJson('/api/coupons/validate', [
                'code' => 'INACTIVE',
                'subtotal' => 200.00,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    // ─── Wrong Category ───────────────────────────────────────

    public function test_validate_coupon_wrong_category(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier([], ['category' => 'mecanica']);
        $this->createCoupon([
            'code' => 'PNEUSONLY',
            'category' => 'pneus',
        ]);

        $response = $this->actingAs($customer)
            ->postJson('/api/coupons/validate', [
                'code' => 'PNEUSONLY',
                'subtotal' => 200.00,
                'supplier_id' => $supplier->id,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_validate_coupon_matching_category(): void
    {
        $customer = $this->createCustomer();
        ['supplier' => $supplier] = $this->createApprovedSupplier([], ['category' => 'pneus']);
        $this->createCoupon([
            'code' => 'PNEUSONLY',
            'category' => 'pneus',
        ]);

        $response = $this->actingAs($customer)
            ->postJson('/api/coupons/validate', [
                'code' => 'PNEUSONLY',
                'subtotal' => 200.00,
                'supplier_id' => $supplier->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['valid' => true],
            ]);
    }

    // ─── Minimum Order Value ──────────────────────────────────

    public function test_validate_coupon_below_min_order_value(): void
    {
        $customer = $this->createCustomer();
        $this->createCoupon([
            'code' => 'MINVAL',
            'min_order_value' => 500.00,
        ]);

        $response = $this->actingAs($customer)
            ->postJson('/api/coupons/validate', [
                'code' => 'MINVAL',
                'subtotal' => 100.00,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_validate_coupon_meets_min_order_value(): void
    {
        $customer = $this->createCustomer();
        $this->createCoupon([
            'code' => 'MINVAL',
            'min_order_value' => 100.00,
        ]);

        $response = $this->actingAs($customer)
            ->postJson('/api/coupons/validate', [
                'code' => 'MINVAL',
                'subtotal' => 200.00,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['valid' => true],
            ]);
    }

    // ─── Nonexistent Coupon ───────────────────────────────────

    public function test_validate_nonexistent_coupon(): void
    {
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer)
            ->postJson('/api/coupons/validate', [
                'code' => 'DOESNOTEXIST',
                'subtotal' => 200.00,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    // ─── Validation Rules ─────────────────────────────────────

    public function test_validate_requires_code_and_subtotal(): void
    {
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer)
            ->postJson('/api/coupons/validate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'subtotal']);
    }

    public function test_coupon_code_is_case_insensitive(): void
    {
        $customer = $this->createCustomer();
        $this->createCoupon(['code' => 'PROMO10']);

        $response = $this->actingAs($customer)
            ->postJson('/api/coupons/validate', [
                'code' => 'promo10',
                'subtotal' => 200.00,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['valid' => true],
            ]);
    }

    // ─── Unauthenticated ──────────────────────────────────────

    public function test_unauthenticated_cannot_validate_coupon(): void
    {
        $response = $this->postJson('/api/coupons/validate', [
            'code' => 'PROMO10',
            'subtotal' => 200.00,
        ]);

        $response->assertStatus(401);
    }

    // ─── Cashback Wallet ──────────────────────────────────────

    public function test_customer_can_view_cashback_wallet(): void
    {
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer)
            ->getJson('/api/cashback');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'balance' => 0,
                    'transactions' => [],
                ],
            ]);
    }

    public function test_cashback_wallet_shows_balance_and_transactions(): void
    {
        $customer = $this->createCustomer();

        $wallet = CashbackWallet::create([
            'user_id' => $customer->id,
            'balance' => 25.50,
        ]);

        CashbackTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => 25.50,
            'expires_at' => now()->addDays(90),
        ]);

        $response = $this->actingAs($customer)
            ->getJson('/api/cashback');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'balance' => 25.50,
                ],
            ]);

        $transactions = $response->json('data.transactions');
        $this->assertCount(1, $transactions);
        $this->assertEquals('credit', $transactions[0]['type']);
    }

    public function test_unauthenticated_cannot_view_cashback(): void
    {
        $response = $this->getJson('/api/cashback');

        $response->assertStatus(401);
    }
}
