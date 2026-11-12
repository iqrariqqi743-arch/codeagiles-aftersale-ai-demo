<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Stripe\StripeClient;

class StripeCheckoutController extends Controller
{
    public function checkout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan' => 'required|in:starter,growth,pro',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid subscription plan.',
                'errors' => $validator->errors(),
            ], 422);
        }

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
                'message' => 'Only store owners can subscribe.',
            ], 403);
        }

        $plan = $validator->validated()['plan'];
        $priceId = config("services.stripe.prices.{$plan}");
        $secret = config('services.stripe.secret');

        if (!$priceId || !$secret) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe configuration is missing.',
            ], 500);
        }

        // Prevent duplicate active subscriptions.
        $existing = Subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                'active',
                'trialing',
                'past_due',
                'unpaid',
            ])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a subscription. Use the billing management flow to change plans.',
            ], 409);
        }

        try {
            $stripe = new StripeClient($secret);

            $customerId = Subscription::query()
                ->where('user_id', $user->id)
                ->whereNotNull('stripe_customer_id')
                ->latest('id')
                ->value('stripe_customer_id');

            // Reuse an existing Stripe customer when possible.
            if (!$customerId) {
                $customer = $stripe->customers->create([
                    'email' => $user->email,
                    'name' => $user->name ?? '',
                    'metadata' => [
                        'user_id' => (string) $user->id,
                    ],
                ]);

                $customerId = $customer->id;
            }

            $frontendUrl = rtrim(config('app.url'), '/');

            $session = $stripe->checkout->sessions->create([
                'mode' => 'subscription',
                'customer' => $customerId,
                'line_items' => [
                    [
                        'price' => $priceId,
                        'quantity' => 1,
                    ],
                ],
                'client_reference_id' => (string) $user->id,
                'subscription_data' => [
                    'metadata' => [
                        'user_id' => (string) $user->id,
                        'plan' => $plan,
                    ],
                ],
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'plan' => $plan,
                ],
                'success_url' => $frontendUrl
                    . '/dashboard/billing?payment=success&session_id={CHECKOUT_SESSION_ID}',

                'cancel_url' => $frontendUrl
                    . '/dashboard/billing?payment=cancelled',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stripe Checkout session created.',
                'checkout_url' => $session->url,
                'session_id' => $session->id,
                'plan' => $plan,
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe checkout failed', [
                'user_id' => $user->id,
                'plan' => $plan,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create Stripe Checkout session.',
            ], 500);
        }
    }
}
