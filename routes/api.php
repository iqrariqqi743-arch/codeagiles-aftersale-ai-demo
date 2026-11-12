<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\KnowledgeBaseController;
use App\Http\Controllers\Api\AISettingsController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StripeCheckoutController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SubscriptionPlanController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController;
use App\Http\Controllers\Api\SuperAdmin\MerchantController;
use App\Http\Controllers\Api\SuperAdmin\UserController;
use App\Http\Controllers\Api\SuperAdmin\PlanController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionController as SuperAdminSubscriptionController;
use App\Http\Controllers\Api\SuperAdmin\SettingsController as SuperAdminSettingsController;
/*
|--------------------------------------------------------------------------
| PUBLIC AUTHENTICATION ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/debug/current-user', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'success' => true,
        'user_id' => $request->user()->id,
        'email' => $request->user()->email,
    ]);
});

// ==========================================
// MERCHANT REGISTRATION
// ==========================================

Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware(['throttle:5,1', 'registration.enabled']);

// ==========================================
// FORGOT PASSWORD
// ==========================================

Route::post('/auth/forgot-password', [
    AuthController::class,
    'forgotPassword'
])->middleware('throttle:5,1');

// ==========================================
// RESET PASSWORD
// ==========================================

Route::post('/auth/reset-password', [
    AuthController::class,
    'resetPassword'
])->middleware('throttle:5,1');

Route::prefix('auth')->group(function () {

    Route::post('/login', [AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| PROTECTED API ROUTES - SANCTUM
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATION
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->group(function () {

        Route::get('/me', [AuthController::class, 'me']);

        Route::post('/logout', [AuthController::class, 'logout']);
    });

    /*
    |--------------------------------------------------------------------------
    | PROFILE SETTINGS
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/settings/profile',
        [SettingsController::class, 'getProfile']
    );

    Route::put(
        '/settings/profile',
        [SettingsController::class, 'updateProfile']
    );

    Route::put(
        '/settings/change-password',
        [SettingsController::class, 'changePassword']
    );

    /*
    |--------------------------------------------------------------------------
    | STRIPE CHECKOUT & SUBSCRIPTIONS
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/stripe/checkout',
        [StripeCheckoutController::class, 'checkout']
    );

    Route::get(
        '/subscription/me',
        [SubscriptionController::class, 'mySubscription']
    );

    Route::post(
        '/subscription/portal',
        [SubscriptionController::class, 'createPortalSession']
    );

    /*
    |--------------------------------------------------------------------------
    | ACTIVE SUBSCRIPTION REQUIRED
    |--------------------------------------------------------------------------
    */

    Route::middleware('active.subscription')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | STORES MANAGEMENT
        |--------------------------------------------------------------------------
        */

        Route::prefix('stores')->group(function () {

            Route::get(
                '/',
                [StoreController::class, 'index']
            );

            Route::post(
                '/',
                [StoreController::class, 'store']
            );

            Route::post(
                '/{id}/connect',
                [StoreController::class, 'connect']
            );

            Route::post(
                '/{id}/disconnect',
                [StoreController::class, 'disconnect']
            );

            Route::delete(
                '/{id}',
                [StoreController::class, 'destroy']
            );
        });

        /*
        |--------------------------------------------------------------------------
        | RETURNS & REFUNDS
        |--------------------------------------------------------------------------
        */

        Route::prefix('returns')->group(function () {

            Route::get(
                '/',
                [ReturnController::class, 'index']
            );

            Route::post(
                '/',
                [ReturnController::class, 'store']
            );

            Route::get(
                '/{id}',
                [ReturnController::class, 'show']
            );

            Route::patch(
                '/{id}/link-ticket',
                [ReturnController::class, 'linkTicket']
            );

            Route::patch(
                '/{id}/status',
                [ReturnController::class, 'updateStatus']
            );

            Route::patch(
                '/{id}/refund',
                [ReturnController::class, 'updateRefund']
            );
        });

        /*
        |--------------------------------------------------------------------------
        | ORDERS MANAGEMENT
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/orders',
            [OrderController::class, 'index']
        );

        Route::post(
            '/orders/demo',
            [OrderController::class, 'generateDemo']
        );

        Route::post(
            '/orders/sync-woocommerce',
            [OrderController::class, 'syncWooCommerce']
        );

        /*
        |--------------------------------------------------------------------------
        | SUPPORT TICKETS
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/tickets',
            [TicketController::class, 'index']
        );

        Route::post(
            '/tickets',
            [TicketController::class, 'store']
        );

        Route::get(
            '/tickets/{id}',
            [TicketController::class, 'show']
        )->whereNumber('id');

        Route::patch(
            '/tickets/{id}/status',
            [TicketController::class, 'updateStatus']
        )->whereNumber('id');

        Route::post(
            '/tickets/{id}/approve-reply',
            [TicketController::class, 'approveReply']
        )->whereNumber('id');

        Route::post(
            '/tickets/{id}/messages',
            [TicketController::class, 'sendMessage']
        )->whereNumber('id');

        Route::patch(
            '/tickets/{id}/apply-ai-suggestions',
            [TicketController::class, 'applyAISuggestions']
        )->whereNumber('id');

        Route::post(
            '/tickets/{id}/generate-ai-reply',
            [TicketController::class, 'generateAIReply']
        )->whereNumber('id');

        /*
        |--------------------------------------------------------------------------
        | AI TICKET OPERATIONS
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/ai/tickets/{id}/generate-reply',
            [TicketController::class, 'generateKBReply']
        );

        Route::post(
            '/ai/tickets/{id}/analyze',
            [TicketController::class, 'analyzeTicket']
        );

        /*
        |--------------------------------------------------------------------------
        | KNOWLEDGE BASE
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/ai/knowledge/{storeId}',
            [KnowledgeBaseController::class, 'createKnowledgeBase']
        );

        Route::get(
            '/ai/knowledge/{storeId}',
            [KnowledgeBaseController::class, 'getKnowledgeBase']
        );

        Route::get(
            '/knowledge-base/{id}',
            [KnowledgeBaseController::class, 'getKnowledgeBaseById']
        );

        Route::post(
            '/knowledge-base',
            [KnowledgeBaseController::class, 'createKnowledgeBase']
        );

        Route::put(
            '/knowledge-base/{id}',
            [KnowledgeBaseController::class, 'updateKnowledgeBase']
        );

        Route::delete(
            '/knowledge-base/{id}',
            [KnowledgeBaseController::class, 'deleteKnowledgeBase']
        );

        Route::post(
            '/knowledge-base/test',
            [KnowledgeBaseController::class, 'testKnowledgeBase']
        );

        /*
        |--------------------------------------------------------------------------
        | AI SETTINGS - MULTI STORE
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/ai/settings/{storeId}',
            [AISettingsController::class, 'getAISettings']
        );

        Route::put(
            '/ai/settings/{storeId}',
            [AISettingsController::class, 'updateAISettings']
        );

        /*
        |--------------------------------------------------------------------------
        | ANALYTICS
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/analytics/overview',
            [AnalyticsController::class, 'overview']
        );

        Route::get('/subscription/plans', [SubscriptionPlanController::class, 'index']);
    }); // End active.subscription

}); // End auth:sanctum

/*
|--------------------------------------------------------------------------
| STRIPE WEBHOOK
|--------------------------------------------------------------------------
*/

Route::post(
    '/stripe/webhook',
    [StripeWebhookController::class, 'handle']
);

/*
|--------------------------------------------------------------------------
| SUPER ADMIN ROUTES
|--------------------------------------------------------------------------
| No active subscription required
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'super_admin'])
    ->prefix('super-admin')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Merchants
        Route::get('/merchants', [MerchantController::class, 'index']);
        Route::get('/merchants/{id}', [MerchantController::class, 'show'])
            ->whereNumber('id');

        // Users
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);

        Route::get('/users/{id}', [UserController::class, 'show'])
            ->whereNumber('id');

        Route::put('/users/{id}', [UserController::class, 'update'])
            ->whereNumber('id');

        Route::patch('/users/{id}/status', [UserController::class, 'updateStatus'])
            ->whereNumber('id');

        Route::delete('/users/{id}', [UserController::class, 'destroy'])
            ->whereNumber('id');

        // Subscription Plans
        Route::get('/plans', [PlanController::class, 'index']);

        Route::get('/plans/{id}', [PlanController::class, 'show'])
            ->whereNumber('id');

        Route::put('/plans/{id}', [PlanController::class, 'update'])
            ->whereNumber('id');

        /*
|--------------------------------------------------------------------------
| SUPER ADMIN SUBSCRIPTIONS
|--------------------------------------------------------------------------
*/

        Route::get(
            '/subscriptions',
            [SuperAdminSubscriptionController::class, 'index']
        );

        Route::get(
            '/subscriptions/{id}',
            [SuperAdminSubscriptionController::class, 'show']
        )->whereNumber('id');



        // ==========================================
        // SUPER ADMIN SETTINGS
        // ==========================================

        Route::get('/settings', [SuperAdminSettingsController::class, 'index']);

        Route::put('/settings', [SuperAdminSettingsController::class, 'update']);
    });
