<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class SubscriptionController extends Controller
{
    /**
     * Get logged-in merchant subscription.
     */
    public function mySubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->role !== 'owner') {
            return response()->json([
                'success' => false,
                'message' => 'Only store owners can access subscriptions.',
            ], 403);
        }

        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->orderByRaw("
                CASE
                    WHEN status IN ('active', 'trialing') THEN 0
                    WHEN status IN ('past_due', 'unpaid') THEN 1
                    ELSE 2
                END
            ")
            ->latest('id')
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => true,
                'message' => 'No subscription found.',
                'has_subscription' => false,
                'subscription' => null,
            ]);
        }

        $plans = [
            'starter' => [
                'name' => 'Starter',
                'price' => 29,
            ],
            'growth' => [
                'name' => 'Growth',
                'price' => 49,
            ],
            'pro' => [
                'name' => 'Pro',
                'price' => 119,
            ],
        ];

        $plan = $plans[$subscription->plan] ?? [
            'name' => ucfirst($subscription->plan),
            'price' => null,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Subscription fetched successfully.',
            'has_subscription' => true,
            'subscription' => [
                'id' => $subscription->id,
                'plan' => $subscription->plan,
                'plan_name' => $plan['name'],
                'price' => $plan['price'],
                'currency' => 'USD',
                'billing_interval' => 'month',
                'status' => $subscription->status,
                'is_active' => $subscription->isActive(),
                'current_period_start' =>
                $subscription->current_period_start?->toIso8601String(),
                'current_period_end' =>
                $subscription->current_period_end?->toIso8601String(),
                'cancel_at_period_end' =>
                $subscription->cancel_at_period_end,
                'canceled_at' =>
                $subscription->canceled_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Create a Stripe Customer Portal session for the logged-in owner.
     */
    public function createPortalSession(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->role !== 'owner') {
            return response()->json([
                'success' => false,
                'message' => 'Only store owners can manage subscriptions.',
            ], 403);
        }

        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->whereNotNull('stripe_customer_id')
            ->whereIn('status', [
                'active',
                'trialing',
                'past_due',
                'unpaid',
            ])
            ->latest('id')
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No manageable subscription found.',
            ], 404);
        }

        $stripeSecret = config('services.stripe.secret');

        if (!$stripeSecret) {
            Log::error('Stripe secret is not configured.');

            return response()->json([
                'success' => false,
                'message' => 'Billing service is not configured.',
            ], 503);
        }

        try {
            $stripe = new StripeClient($stripeSecret);

            $portalSession = $stripe->billingPortal->sessions->create([
                'customer' => $subscription->stripe_customer_id,
                'return_url' => rtrim(config('app.url'), '/') . '/dashboard',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stripe Customer Portal session created successfully.',
                'portal_url' => $portalSession->url,
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe Customer Portal session failed.', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to open billing portal. Please try again.',
            ], 502);
        }
    }
}
