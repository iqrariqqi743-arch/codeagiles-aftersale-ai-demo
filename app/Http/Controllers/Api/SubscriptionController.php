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

        $subscriptions = Subscription::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();

        /*
         * Prefer a valid Stripe subscription over a free trial.
         * This prevents the trial from hiding a paid plan.
         */
        $subscription = $subscriptions->first(
            fn (Subscription $item) =>
                !empty($item->stripe_subscription_id)
                && $item->isActive()
        );

        if (!$subscription) {
            $subscription = $subscriptions->first(
                fn (Subscription $item) => $item->isActive()
            );
        }

        if (!$subscription) {
            $subscription = $subscriptions->first();
        }

        if (!$subscription) {
            return response()->json([
                'success' => true,
                'message' => 'No subscription found.',
                'has_subscription' => false,
                'subscription' => null,
                'is_free_trial' => false,
                'has_stripe_subscription' => false,
                'trial' => [
                    'is_trialing' => false,
                    'is_expired' => false,
                    'days_remaining' => 0,
                    'ends_at' => null,
                ],
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

        $hasStripeSubscription =
            !empty($subscription->stripe_subscription_id);

        $isFreeTrial =
            !$hasStripeSubscription
            && $subscription->status === 'trialing';

        $isTrialExpired = $isFreeTrial
            && $subscription->isTrialExpired();

        $trialDaysRemaining = $isFreeTrial
            ? $subscription->trialDaysRemaining()
            : 0;

        return response()->json([
            'success' => true,
            'message' => 'Subscription fetched successfully.',
            'has_subscription' => true,

            'is_free_trial' => $isFreeTrial,
            'has_stripe_subscription' => $hasStripeSubscription,

            'subscription' => [
                'id' => $subscription->id,
                'plan' => $subscription->plan,
                'plan_name' => $plan['name'],
                'price' => $plan['price'],
                'currency' => 'USD',
                'billing_interval' => 'month',
                'status' => $subscription->status,
                'is_active' => $subscription->isActive(),

                'is_free_trial' => $isFreeTrial,
                'has_stripe_subscription' =>
                    $hasStripeSubscription,

                'current_period_start' =>
                    $subscription->current_period_start
                        ?->toIso8601String(),

                'current_period_end' =>
                    $subscription->current_period_end
                        ?->toIso8601String(),

                'cancel_at_period_end' =>
                    $subscription->cancel_at_period_end,

                'canceled_at' =>
                    $subscription->canceled_at
                        ?->toIso8601String(),
            ],

            'trial' => [
                'is_trialing' => $isFreeTrial && !$isTrialExpired,
                'is_expired' => $isTrialExpired,
                'days_remaining' => $trialDaysRemaining,
                'ends_at' => $isFreeTrial
                    ? $subscription->current_period_end
                        ?->toIso8601String()
                    : null,
            ],
        ]);
    }

    /**
     * Create a Stripe Customer Portal session.
     */
    public function createPortalSession(
        Request $request
    ): JsonResponse {
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
                'message' =>
                    'Only store owners can manage subscriptions.',
            ], 403);
        }

        /*
         * Only real Stripe subscriptions can open
         * the billing management portal.
         *
         * Local free trials must not be treated
         * as paid Stripe subscriptions.
         */
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->whereNotNull('stripe_customer_id')
            ->whereNotNull('stripe_subscription_id')
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
                'message' =>
                    'No Stripe subscription found. Please choose a plan first.',
            ], 404);
        }

        $stripeSecret = config('services.stripe.secret');

        if (!$stripeSecret) {
            Log::error('Stripe secret is not configured.');

            return response()->json([
                'success' => false,
                'message' =>
                    'Billing service is not configured.',
            ], 503);
        }

        try {
            $stripe = new StripeClient($stripeSecret);

            $portalSession =
                $stripe->billingPortal->sessions->create([
                    'customer' =>
                        $subscription->stripe_customer_id,

                    'return_url' =>
                        rtrim(config('app.url'), '/')
                        . '/dashboard/billing',
                ]);

            return response()->json([
                'success' => true,
                'message' =>
                    'Stripe Customer Portal session created successfully.',
                'portal_url' => $portalSession->url,
            ]);

        } catch (\Throwable $e) {
            Log::error(
                'Stripe Customer Portal session failed.',
                [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to open billing portal. Please try again.',
            ], 502);
        }
    }
}
