<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionLimitService
{
    public function getActivePlan(int $userId): ?Plan
    {
        $subscription = Subscription::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['active', 'trialing'])
            ->latest('id')
            ->first();

        if (!$subscription || !$subscription->isActive()) {
            return null;
        }

        return Plan::query()
            ->where('slug', $subscription->plan)
            ->where('is_active', true)
            ->first();
    }

    public function getOrderUsage(int $userId): array
    {
        $plan = $this->getActivePlan($userId);

        if (!$plan) {
            return [
                'allowed' => false,
                'message' => 'An active subscription is required.',
                'limit' => 0,
                'used' => 0,
                'remaining' => 0,
            ];
        }

        $now = now();

        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $used = Order::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [
                $monthStart,
                $monthEnd,
            ])
            ->count();

        $limit = (int) $plan->max_orders_per_month;

        $unlimited = $limit === -1;

        return [
            'allowed' => $unlimited || $used < $limit,
            'plan' => $plan->slug,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $unlimited
                ? null
                : max(0, $limit - $used),
            'unlimited' => $unlimited,
            'message' => $unlimited || $used < $limit
                ? 'Order limit available.'
                : 'Monthly order limit reached. Please upgrade your subscription.',
        ];
    }

    public function canCreateOrder(int $userId): bool
    {
        return $this->getOrderUsage($userId)['allowed'];
    }
}
