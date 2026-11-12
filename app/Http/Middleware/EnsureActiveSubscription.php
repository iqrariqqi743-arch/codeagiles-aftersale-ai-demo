<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        // Step 1: Check authentication
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Step 2: Only store owners need subscriptions
        if ($user->role !== 'owner') {
            return response()->json([
                'success' => false,
                'message' => 'Only store owners can access this resource.',
            ], 403);
        }

        // Step 3: Find active subscription
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'trialing'])
            ->latest('id')
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'An active subscription is required.',
                'subscription_required' => true,
            ], 403);
        }

        // Step 4: Check subscription expiration
        if (
            $subscription->current_period_end &&
            $subscription->current_period_end->isPast()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription billing period has expired.',
                'subscription_required' => true,
            ], 403);
        }

        // Step 5: Make subscription available to controllers
        $request->attributes->set(
            'active_subscription',
            $subscription
        );

        return $next($request);
    }
}
