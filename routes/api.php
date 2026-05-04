<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogItemController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\DisputeController;
use App\Http\Controllers\Api\InsuranceClaimController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LegalController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\WithdrawalController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'data' => ['status' => 'ok', 'timestamp' => now()->toIso8601String()],
        'message' => 'CarHub API is running.',
    ]);
});

// Auth (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Public supplier routes
Route::get('/suppliers', [SupplierController::class, 'index']);
Route::get('/suppliers/nearby', [SupplierController::class, 'nearby']);
Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
Route::get('/suppliers/{supplier}/catalog', [CatalogItemController::class, 'index']);
Route::get('/suppliers/{supplier}/reviews', [ReviewController::class, 'supplierReviews']);
Route::get('/suppliers/{supplier}/availability', [ScheduleController::class, 'availability']);

// Legal (public)
Route::get('/legal/terms', [LegalController::class, 'terms']);
Route::get('/legal/privacy', [LegalController::class, 'privacy']);

// Payment webhook (public, no auth)
Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

// Authenticated routes
Route::middleware(['auth:sanctum', 'audit'])->group(function () {

    // Auth
    Route::delete('/auth/logout', [AuthController::class, 'logout']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::put('/profile/expo-push-token', [ProfileController::class, 'updatePushToken']); // legacy, use PUT /profile with fcm_token instead
    Route::post('/profile/delete-request', [LegalController::class, 'deleteRequest']);
    Route::get('/profile/export-data', [LegalController::class, 'exportData']);

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
        Route::get('/earnings', [PaymentController::class, 'supplierEarnings']);
        Route::post('/bank-account', [SupplierController::class, 'registerBankAccount']);
        Route::get('/bank-account', [SupplierController::class, 'getBankAccount']);
        Route::post('/withdrawals', [WithdrawalController::class, 'requestWithdrawal']);
        Route::get('/withdrawals', [WithdrawalController::class, 'index']);
        Route::get('/withdrawals/{withdrawal}', [WithdrawalController::class, 'show']);
        Route::get('/payouts', [WithdrawalController::class, 'index']);
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

    Route::middleware('role:customer')->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
        Route::post('/orders/{order}/pay', [OrderController::class, 'pay']);
        Route::get('/orders/{order}/payment-status', [OrderController::class, 'paymentStatus']);
        Route::put('/orders/{order}/confirm', [OrderController::class, 'confirm']);
    });

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

    // Chat
    Route::get('/chat/rooms', [ChatController::class, 'rooms']);
    Route::get('/chat/unread-count', [ChatController::class, 'unreadCount']);
    Route::get('/chat/{order}/messages', [ChatController::class, 'index']);
    Route::post('/chat/{order}/messages', [ChatController::class, 'store']);
    Route::post('/chat/{order}/images', [ChatController::class, 'sendImage']);

    // Deliveries
    Route::get('/deliveries/{order}', [DeliveryController::class, 'show']);
    Route::middleware(['role:supplier', 'approved'])->group(function () {
        Route::post('/deliveries', [DeliveryController::class, 'store']);
        Route::put('/deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus']);
        Route::put('/deliveries/{delivery}/location', [DeliveryController::class, 'updateLocation']);
        Route::post('/deliveries/{delivery}/photo', [DeliveryController::class, 'uploadPhoto']);
    });

    // Reviews
    Route::middleware('role:customer')->group(function () {
        Route::post('/reviews', [ReviewController::class, 'store']);
    });

    // Milestones (service orders — flexible payment flow)
    Route::get('/orders/{order}/milestones', [MilestoneController::class, 'index']);
    Route::middleware('role:customer')->group(function () {
        Route::post('/orders/{order}/milestones/pay', [MilestoneController::class, 'pay']);
        Route::put('/milestones/{milestone}/approve', [MilestoneController::class, 'approve']);
        Route::put('/milestones/{milestone}/decline', [MilestoneController::class, 'decline']);
        Route::put('/milestones/{milestone}/contest', [MilestoneController::class, 'contest']);
    });
    Route::middleware('role:supplier')->group(function () {
        Route::post('/milestones/{milestone}/evidence', [MilestoneController::class, 'submitEvidence']);
    });
    Route::middleware('role:admin')->group(function () {
        Route::put('/milestones/{milestone}/resolve', [MilestoneController::class, 'adminResolve']);
    });

    // Disputes
    Route::get('/disputes', [DisputeController::class, 'index']);
    Route::get('/disputes/{dispute}', [DisputeController::class, 'show']);
    Route::post('/disputes', [DisputeController::class, 'store']);
    Route::middleware('role:admin')->group(function () {
        Route::put('/disputes/{dispute}/resolve', [DisputeController::class, 'resolve']);
    });

    // Schedules
    Route::get('/schedules', [ScheduleController::class, 'index']);
    Route::middleware('role:customer')->group(function () {
        Route::post('/schedules', [ScheduleController::class, 'store']);
    });
    Route::put('/schedules/{schedule}/accept', [ScheduleController::class, 'accept']);
    Route::put('/schedules/{schedule}/reject', [ScheduleController::class, 'reject']);
    Route::put('/schedules/{schedule}/cancel', [ScheduleController::class, 'cancel']);
    // Invoices
    Route::get('/orders/{order}/invoice', [InvoiceController::class, 'download']);
    Route::middleware(['role:supplier', 'approved'])->group(function () {
        Route::post('/orders/{order}/invoice', [InvoiceController::class, 'upload']);
    });

    // Coupons
    Route::post('/coupons/validate', [CouponController::class, 'validate']);

    // Cashback
    Route::get('/cashback', [CouponController::class, 'cashbackWallet']);
    Route::get('/cashback/balance', [CouponController::class, 'cashbackBalance']);
    Route::get('/cashback/transactions', [CouponController::class, 'cashbackTransactions']);

    // Insurance Claims
    Route::get('/insurance-claims', [InsuranceClaimController::class, 'index']);
    Route::get('/insurance-claims/{claim}', [InsuranceClaimController::class, 'show']);
    Route::get('/insurance-claims/{claim}/protocol', [InsuranceClaimController::class, 'downloadProtocol']);

    Route::middleware('role:customer')->group(function () {
        Route::post('/insurance-claims', [InsuranceClaimController::class, 'store']);
        Route::put('/insurance-claims/{claim}/submit', [InsuranceClaimController::class, 'submit']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::put('/insurance-claims/{claim}/send', [InsuranceClaimController::class, 'sendToInsurer']);
        Route::put('/insurance-claims/{claim}/status', [InsuranceClaimController::class, 'updateStatus']);

        // Reports (CSV export)
        Route::get('/reports/export', [\App\Http\Controllers\Api\ReportController::class, 'exportCsv']);
    });
});
