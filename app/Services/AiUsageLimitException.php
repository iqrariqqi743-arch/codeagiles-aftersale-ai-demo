<?php

namespace App\Services;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AiUsageLimitService
{
    public function __construct(
        private SubscriptionLimitService $subscriptionLimits
    ) {
    }

    /**
     * Get monthly AI usage for the owner.
     */
    public function getUsage(int $userId): array
    {
        $plan = $this->subscriptionLimits->getActivePlan($userId);

        if (!$plan) {
            return [
                'allowed' => false,
                'plan' => null,
                'limit' => 0,
                'used' => 0,
                'remaining' => 0,
                'unlimited' => false,
                'message' => 'An active subscription is required.',
            ];
        }

        $limit = (int) $plan->max_ai_requests_per_month;

        $used = AiUsageLog::query()
            ->forUser($userId)
            ->currentMonth()
            ->whereIn('status', ['reserved', 'completed'])
            ->count();

        $unlimited = $limit === -1;
        $allowed = $unlimited || $used < $limit;

        return [
            'allowed' => $allowed,
            'plan' => $plan->slug,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $unlimited
                ? null
                : max(0, $limit - $used),
            'unlimited' => $unlimited,
            'message' => $allowed
                ? 'AI requests available.'
                : 'Monthly AI request limit reached. Please upgrade your subscription.',
        ];
    }

    /**
     * Reserve one AI request before generating.
     */
    public function reserve(
        int $userId,
        ?int $ticketId,
        string $feature
    ): AiUsageLog {
        return DB::transaction(function () use (
            $userId,
            $ticketId,
            $feature
        ) {
            // Lock the owner to prevent concurrent quota bypass.
            $owner = DB::table('users')
                ->where('id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$owner) {
                throw new RuntimeException('Owner not found.');
            }

            $usage = $this->getUsage($userId);

            if (!$usage['allowed']) {
                throw new AiUsageLimitException(
                    $usage['message'],
                    $usage
                );
            }

            return AiUsageLog::create([
                'user_id' => $userId,
                'ticket_id' => $ticketId,
                'feature' => $feature,
                'status' => 'reserved',
            ]);
        }, 3);
    }

    /**
     * Mark a successful AI request.
     */
    public function complete(AiUsageLog $log): void
    {
        $log->update([
            'status' => 'completed',
        ]);
    }

    /**
     * Release quota for a failed AI request.
     */
    public function fail(AiUsageLog $log): void
    {
        $log->update([
            'status' => 'failed',
        ]);
    }
}