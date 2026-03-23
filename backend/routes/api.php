<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogItemController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'data' => [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ],
        'message' => 'CarHub API is running.',
    ]);
});

// Auth (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Public supplier routes
Route::get('/suppliers', [SupplierController::class, 'index']);
Route::get('/suppliers/nearby', [SupplierController::class, 'nearby']);
Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
Route::get('/suppliers/{supplier}/catalog', [CatalogItemController::class, 'index']);

// Payment webhook (public, no auth)
Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

// Authenticated routes
Route::middleware(['auth:sanctum', 'audit'])->group(function () {

    // Auth
    Route::delete('/auth/logout', [AuthController::class, 'logout']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::put('/profile/expo-push-token', [ProfileController::class, 'updatePushToken']);

    // Addresses
    Route::apiResource('addresses', AddressController::class);

    // Vehicles (customer only)
    Route::middleware('role:customer')->group(function () {
        Route::apiResource('vehicles', VehicleController::class);
    });

    // Supplier-only routes
    Route::middleware(['role:supplier', 'approved'])->prefix('supplier')->group(function () {
        Route::put('/profile', [SupplierController::class, 'updateProfile']);
        Route::post('/cover-image', [SupplierController::class, 'uploadCoverImage']);
        Route::post('/logo', [SupplierController::class, 'uploadLogo']);
        Route::get('/balance', [PaymentController::class, 'supplierBalance']);
        Route::get('/transactions', [PaymentController::class, 'supplierTransactions']);
    });

    // Catalog management (supplier only)
    Route::middleware(['role:supplier', 'approved'])->group(function () {
        Route::post('/catalog', [CatalogItemController::class, 'store']);
        Route::put('/catalog/{catalog}', [CatalogItemController::class, 'update']);
        Route::delete('/catalog/{catalog}', [CatalogItemController::class, 'destroy']);
        Route::post('/catalog/{catalog}/images', [CatalogItemController::class, 'uploadImages']);
    });

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    // Order creation (customer only)
    Route::middleware('role:customer')->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
        Route::put('/orders/{order}/confirm', [OrderController::class, 'confirm']);
    });

    // Order supplier actions
    Route::middleware(['role:supplier', 'approved'])->group(function () {
        Route::put('/orders/{order}/accept', [OrderController::class, 'accept']);
        Route::put('/orders/{order}/reject', [OrderController::class, 'reject']);
        Route::put('/orders/{order}/start', [OrderController::class, 'start']);
        Route::put('/orders/{order}/complete', [OrderController::class, 'complete']);
    });

    // Payments
    Route::middleware('throttle:payment')->group(function () {
        Route::post('/payments/checkout', [PaymentController::class, 'checkout']);
    });
    Route::get('/payments/{payment}/status', [PaymentController::class, 'status']);
    Route::get('/payments/order/{order}', [PaymentController::class, 'orderPayments']);

    // Quotes
    Route::get('/quotes', [QuoteController::class, 'index']);
    Route::get('/quotes/{quote}', [QuoteController::class, 'show']);

    Route::middleware('role:customer')->group(function () {
        Route::post('/quotes', [QuoteController::class, 'store']);
        Route::put('/quotes/{quote}/approve', [QuoteController::class, 'approve']);
        Route::put('/quotes/{quote}/final-approve', [QuoteController::class, 'finalApprove']);
        Route::put('/quotes/{quote}/reject', [QuoteController::class, 'reject']);
    });

    Route::middleware(['role:supplier', 'approved'])->group(function () {
        Route::put('/quotes/{quote}/respond', [QuoteController::class, 'respond']);
        Route::put('/quotes/{quote}/adjust', [QuoteController::class, 'adjust']);
    });

    // Coupons
    Route::post('/coupons/validate', [CouponController::class, 'validate']);

    // Cashback
    Route::get('/cashback', [CouponController::class, 'cashbackWallet']);
});
