<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\Admin\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Webhook routes (no authentication required)
Route::post('/webhooks/asaas', [WebhookController::class, 'handleAsaasWebhook']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'changePassword']);
    });

    // User routes
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Plans routes (public read, admin write)
    Route::get('/plans', [PlanController::class, 'index']);
    Route::get('/plans/{plan}', [PlanController::class, 'show']);
    Route::get('/plans-frequencies', [PlanController::class, 'frequencies']);
    Route::get('/bread-types', [PlanController::class, 'breadTypes']);

    // Address routes (customer only)
    Route::prefix('addresses')->group(function () {
        Route::get('/', [AddressController::class, 'index']);
        Route::post('/', [AddressController::class, 'store']);
        Route::get('/{address}', [AddressController::class, 'show']);
        Route::put('/{address}', [AddressController::class, 'update']);
        Route::delete('/{address}', [AddressController::class, 'destroy']);
        Route::post('/{address}/set-default', [AddressController::class, 'setDefault']);
        Route::post('/{address}/calculate-distance', [AddressController::class, 'calculateDistance']);
        Route::post('/search-postal-code', [AddressController::class, 'searchPostalCode']);
    });

    // Subscription routes (customer only)
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index']);
        Route::post('/', [SubscriptionController::class, 'store']);
        Route::get('/{subscription}', [SubscriptionController::class, 'show']);
        Route::put('/{subscription}', [SubscriptionController::class, 'update']);
        Route::post('/{subscription}/pause', [SubscriptionController::class, 'pause']);
        Route::post('/{subscription}/resume', [SubscriptionController::class, 'resume']);
        Route::post('/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
        Route::get('/{subscription}/payments', [SubscriptionController::class, 'payments']);
    });

    // Delivery routes (customer only)
    Route::prefix('deliveries')->group(function () {
        Route::get('/', [DeliveryController::class, 'index']);
        Route::get('/{delivery}', [DeliveryController::class, 'show']);
        Route::post('/{delivery}/rate', [DeliveryController::class, 'rate']);
        Route::get('/calendar/view', [DeliveryController::class, 'calendar']);
    });

    // Admin routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Dashboard routes
        Route::prefix('dashboard')->group(function () {
            Route::get('/overview', [DashboardController::class, 'overview']);
            Route::get('/analytics', [DashboardController::class, 'analytics']);
            Route::get('/top-plans', [DashboardController::class, 'topPlans']);
            Route::get('/recent-activities', [DashboardController::class, 'recentActivities']);
            Route::post('/export', [DashboardController::class, 'export']);
        });

        // Plan management
        Route::prefix('plans')->group(function () {
            Route::post('/', [PlanController::class, 'store']);
            Route::put('/{plan}', [PlanController::class, 'update']);
            Route::delete('/{plan}', [PlanController::class, 'destroy']);
            Route::get('/statistics', [PlanController::class, 'statistics']);
        });

        // Delivery management
        Route::prefix('deliveries')->group(function () {
            Route::get('/daily-route', [DeliveryController::class, 'dailyRoute']);
            Route::put('/{delivery}/status', [DeliveryController::class, 'updateStatus']);
            Route::post('/bulk-update-status', [DeliveryController::class, 'bulkUpdateStatus']);
            Route::get('/statistics', [DeliveryController::class, 'statistics']);
        });
    });
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'service' => 'Bread Subscription System API'
    ]);
});
