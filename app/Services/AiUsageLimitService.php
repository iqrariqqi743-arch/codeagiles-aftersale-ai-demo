<?php

namespace App\Services;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AiUsageLimitService
{
    public function __construct(
        private SubscriptionLimitService $subscriptionLimits
    ) {}

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
                ? 'AI requests available.'
                : 'Monthly AI request limit reached. Please upgrade your subscription.',
        ];
    }

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
            // Serialize AI reservations for the same owner.
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

    public function complete(AiUsageLog $log): void
    {
        $log->update(['status' => 'completed']);
    }

    public function fail(AiUsageLog $log): void
    {
        $log->update(['status' => 'failed']);
    }
}

class AiUsageLimitException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly array $usage
    ) {
        parent::__construct($message);
    }
}
