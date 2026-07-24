<?php

use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\DriverVerificationController;
use App\Http\Controllers\Api\Admin\FareConfigController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\PaymentSettingsController;
use App\Http\Controllers\Api\Admin\PayoutController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\WalletController as AdminWalletController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ConciergeOrderController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\DriverDocumentController;
use App\Http\Controllers\Api\DriverLocationController;
use App\Http\Controllers\Api\DriverOrderController;
use App\Http\Controllers\Api\DriverPaymentController;
use App\Http\Controllers\Api\FareController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RiderOrderController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/catalog', [CatalogController::class, 'index']);
Route::get('/notifications', [NotificationController::class, 'index']);
Route::post('/fares/estimate', [FareController::class, 'estimate']);
Route::get('/fare-config', [FareController::class, 'show']);
Route::post('/webhooks/stripe', StripeWebhookController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/me/profile', [AuthController::class, 'updateProfile']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

    Route::get('/orders/{order}/messages', [ChatController::class, 'index']);
    Route::post('/orders/{order}/messages', [ChatController::class, 'store']);
    Route::post('/orders/{order}/messages/image', [ChatController::class, 'storeImage']);
    Route::post('/payments/orders/{order}/intent', [PaymentController::class, 'rideIntent']);

    Route::middleware('role:rider')->prefix('rider')->group(function () {
        Route::get('/orders', [RiderOrderController::class, 'index']);
        Route::get('/history', [RiderOrderController::class, 'history']);
        Route::get('/conversations', [RiderOrderController::class, 'conversations']);
        Route::post('/orders', [RiderOrderController::class, 'store']);
        Route::get('/orders/{order}/offers', [RiderOrderController::class, 'offers']);
        Route::post('/orders/{order}/choose-driver', [RiderOrderController::class, 'chooseDriver']);
        Route::post('/orders/{order}/cancel', [RiderOrderController::class, 'cancel']);
        Route::post('/orders/{order}/rating', [RiderOrderController::class, 'rating']);
        Route::get('/orders/{order}', [RiderOrderController::class, 'show']);
    });

    Route::middleware('role:concierge')->prefix('concierge')->group(function () {
        Route::get('/orders', [ConciergeOrderController::class, 'index']);
        Route::post('/orders', [ConciergeOrderController::class, 'store']);
        Route::get('/orders/{order}/offers', [ConciergeOrderController::class, 'offers']);
        Route::post('/orders/{order}/choose-driver', [ConciergeOrderController::class, 'chooseDriver']);
        Route::post('/orders/{order}/cancel', [ConciergeOrderController::class, 'cancel']);
        Route::post('/orders/{order}/rating', [ConciergeOrderController::class, 'rating']);
        Route::get('/orders/{order}', [ConciergeOrderController::class, 'show']);
    });

    Route::middleware('role:driver')->prefix('driver')->group(function () {
        Route::post('/online', [DriverOrderController::class, 'online']);
        Route::post('/offline', [DriverOrderController::class, 'offline']);
        Route::post('/location', [DriverLocationController::class, 'store']);
        Route::get('/orders', [DriverOrderController::class, 'index']);
        Route::get('/current-order', [DriverOrderController::class, 'current']);
        Route::get('/history', [DriverOrderController::class, 'history']);
        Route::get('/conversations', [DriverOrderController::class, 'conversations']);
        Route::get('/orders/{order}', [DriverOrderController::class, 'show']);
        Route::post('/orders/{order}/accept', [DriverOrderController::class, 'accept']);
        Route::post('/orders/{order}/counter', [DriverOrderController::class, 'counter']);
        Route::post('/orders/{order}/decline', [DriverOrderController::class, 'decline']);
        Route::post('/orders/{order}/arrived', [DriverOrderController::class, 'arrived']);
        Route::post('/orders/{order}/start', [DriverOrderController::class, 'start']);
        Route::post('/orders/{order}/complete', [DriverOrderController::class, 'complete']);
        Route::get('/wallet', [DriverOrderController::class, 'wallet']);
        Route::get('/payments/points/config', [DriverPaymentController::class, 'pointsConfig']);
        Route::post('/payments/points/intent', [DriverPaymentController::class, 'pointsIntent']);
        Route::post('/payments/connect/onboarding', [DriverPaymentController::class, 'connectOnboarding']);
        Route::get('/payments/connect/status', [DriverPaymentController::class, 'connectStatus']);
        Route::get('/documents', [DriverDocumentController::class, 'index']);
        Route::post('/documents', [DriverDocumentController::class, 'store']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin backend
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Users
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus']);

        // Orders / live rides
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
        Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel']);
        Route::post('/orders/{order}/flag', [AdminOrderController::class, 'flag']);

        // Driver verification
        Route::get('/drivers', [DriverVerificationController::class, 'index']);
        Route::get('/drivers/{driver}/documents', [DriverVerificationController::class, 'documents']);
        Route::post('/drivers/{driver}/approve', [DriverVerificationController::class, 'approve']);
        Route::post('/drivers/{driver}/reject', [DriverVerificationController::class, 'reject']);
        Route::post('/drivers/{driver}/request-document', [DriverVerificationController::class, 'requestDocument']);
        Route::get('/documents/{document}/file', [DriverVerificationController::class, 'file']);
        Route::post('/documents/{document}/review', [DriverVerificationController::class, 'reviewDocument']);

        // Fare config
        Route::get('/fare-config', [FareConfigController::class, 'show']);
        Route::post('/fare-config', [FareConfigController::class, 'store']);

        // Transactions / revenue
        Route::get('/transactions', [AdminTransactionController::class, 'index']);
        Route::get('/revenue', [AdminTransactionController::class, 'revenue']);

        // Wallets / points
        Route::get('/wallets', [AdminWalletController::class, 'index']);
        Route::get('/wallets/{wallet}', [AdminWalletController::class, 'show']);
        Route::post('/wallets/{wallet}/adjust', [AdminWalletController::class, 'adjust']);

        // Payouts
        Route::get('/payouts', [PayoutController::class, 'index']);
        Route::post('/payouts', [PayoutController::class, 'store']);
        Route::post('/payouts/{payout}/confirm', [PayoutController::class, 'confirm']);

        // Provider payments / refunds
        Route::post('/payments/{paymentIntent}/refund', [AdminPaymentController::class, 'refund']);

        // Payment provider configuration (secrets are encrypted and never returned)
        Route::get('/payment-settings', [PaymentSettingsController::class, 'index']);
        Route::put('/payment-settings', [PaymentSettingsController::class, 'update']);
        Route::post('/payment-settings/test', [PaymentSettingsController::class, 'test']);

        // Chat archive
        Route::get('/chats', [AdminChatController::class, 'index']);
        Route::get('/chats/{order}', [AdminChatController::class, 'show']);

        // Audit logs
        Route::get('/audit-logs', [AuditLogController::class, 'index']);

        // Platform settings
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::post('/settings', [SettingsController::class, 'update']);
    });
});
