
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

        // Step 2: Only store owners can access merchant resources
        if ($user->role !== 'owner') {
            return response()->json([
                'success' => false,
                'message' => 'Only store owners can access this resource.',
            ], 403);
        }

        // Step 3: Find a valid paid subscription or free trial.
        // Check all eligible records so an expired trial
        // cannot hide an older, still-valid paid subscription.
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'trialing'])
            ->orderByDesc('id')
            ->get()
            ->first(function (Subscription $item) {
                return $item->isActive();
            });

        if (!$subscription) {
            // Check whether the merchant previously had a trial.
            $expiredTrial = Subscription::query()
                ->where('user_id', $user->id)
                ->where('status', 'trialing')
                ->whereNotNull('current_period_end')
                ->where('current_period_end', '<=', now())
                ->exists();

            return response()->json([
                'success' => false,
                'message' => $expiredTrial
                    ? 'Your 10-day free trial has expired. Please choose a subscription plan to continue.'
                    : 'An active subscription is required.',
                'subscription_required' => true,
                'trial_expired' => $expiredTrial,
            ], 403);
        }

        // Step 4: Share subscription with controllers
        $request->attributes->set(
            'active_subscription',
            $subscription
        );

        return $next($request);
    }
}
