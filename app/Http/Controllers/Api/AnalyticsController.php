<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Services\AiUsageLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    public function overview(
        Request $request,
        AiUsageLimitService $aiUsageLimitService
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'store_id' => [
                'required',
                'integer',
                Rule::exists('stores', 'id')
                    ->where('user_id', $user->id),
            ],
            'period' => [
                'nullable',
                Rule::in(['7d', '30d', '90d', '12m']),
            ],
            'include_demo' => ['nullable', 'boolean'],
        ]);

        $ownerId = (int) $user->id;
        $storeId = (int) $validated['store_id'];
        $period = $validated['period'] ?? '30d';
        $includeDemo = $request->boolean('include_demo', false);

        $store = DB::table('stores')
            ->where('id', $storeId)
            ->where('user_id', $ownerId)
            ->first([
                'id',
                'store_name',
                'platform',
                'status',
            ]);

        $end = Carbon::now();

        $start = match ($period) {
            '7d' => $end->copy()->subDays(7),
            '90d' => $end->copy()->subDays(90),
            '12m' => $end->copy()->subMonths(12),
            default => $end->copy()->subDays(30),
        };

        /*
        |--------------------------------------------------------------------------
        | BASE QUERY
        |--------------------------------------------------------------------------
        */

        $baseQuery = function (string $table) use (
            $ownerId,
            $storeId,
            $includeDemo
        ) {
            return DB::table($table)
                ->where('user_id', $ownerId)
                ->where('store_id', $storeId)
                ->when(
                    !$includeDemo,
                    fn($query) => $query->where('is_demo', 0)
                );
        };

        $orders = $baseQuery('orders')
            ->whereBetween('created_at', [$start, $end]);

        $returns = $baseQuery('return_requests')
            ->whereBetween('created_at', [$start, $end]);

        $tickets = $baseQuery('support_tickets')
            ->whereBetween('created_at', [$start, $end]);

        /*
        |--------------------------------------------------------------------------
        | ORDER STATISTICS
        |--------------------------------------------------------------------------
        */

        $orderStats = (clone $orders)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                "SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"
            )
            ->first();

        $revenueByCurrency = (clone $orders)
            ->select('currency')
            ->selectRaw('SUM(total_amount) as amount')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn($row) => [
                'currency' => $row->currency,
                'amount' => round((float) $row->amount, 2),
            ]);

        /*
        |--------------------------------------------------------------------------
        | RETURNS & EXCHANGES
        |--------------------------------------------------------------------------
        */

        $returnStats = (clone $returns)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                "SUM(CASE WHEN request_type = 'return' THEN 1 ELSE 0 END) as returns"
            )
            ->selectRaw(
                "SUM(CASE WHEN request_type = 'exchange' THEN 1 ELSE 0 END) as exchanges"
            )
            ->selectRaw(
                "SUM(CASE WHEN request_type = 'refund' THEN 1 ELSE 0 END) as refunds"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected"
            )
            ->first();

        $refundsByCurrency = (clone $returns)
            ->where('refund_status', 'completed')
            ->select('currency')
            ->selectRaw('SUM(refund_amount) as amount')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn($row) => [
                'currency' => $row->currency,
                'amount' => round((float) $row->amount, 2),
            ]);

        /*
        |--------------------------------------------------------------------------
        | SUPPORT TICKETS
        |--------------------------------------------------------------------------
        */

        $ticketStats = (clone $tickets)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                "SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as `open`"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as `in_progress`"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'waiting_customer' THEN 1 ELSE 0 END) as `waiting_customer`"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as `resolved`"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as `closed`"
            )
            ->selectRaw(
                "SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as `high_priority`"
            )
            ->selectRaw(
                "SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as `urgent`"
            )
            ->first();

        $ticketCategories = (clone $tickets)
            ->select('category')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn($row) => [
                'category' => $row->category,
                'total' => (int) $row->total,
            ]);

        $resolutionRows = (clone $tickets)
            ->whereNotNull('resolved_at')
            ->whereNotNull('created_at')
            ->whereColumn('resolved_at', '>=', 'created_at')
            ->selectRaw(
                'AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) as average_minutes'
            )
            ->first();

        /*
        |--------------------------------------------------------------------------
        | TICKET MESSAGES & EMAIL ACTIVITY
        |--------------------------------------------------------------------------
        */

        $messages = DB::table('ticket_messages as tm')
            ->join(
                'support_tickets as st',
                'st.id',
                '=',
                'tm.ticket_id'
            )
            ->where('st.user_id', $ownerId)
            ->where('st.store_id', $storeId)
            ->when(
                !$includeDemo,
                fn($query) => $query->where('st.is_demo', 0)
            )
            ->whereBetween('tm.created_at', [$start, $end]);

        $messageStats = (clone $messages)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                "SUM(CASE WHEN tm.sender_type = 'customer' THEN 1 ELSE 0 END) as customer"
            )
            ->selectRaw(
                "SUM(CASE WHEN tm.sender_type = 'owner' THEN 1 ELSE 0 END) as owner"
            )
            ->selectRaw(
                "SUM(CASE WHEN tm.sender_type = 'ai' THEN 1 ELSE 0 END) as ai"
            )
            ->selectRaw(
                'SUM(CASE WHEN tm.is_ai_generated = 1 THEN 1 ELSE 0 END) as ai_assisted'
            )
            ->selectRaw(
                "SUM(CASE WHEN tm.email_status = 'sent' THEN 1 ELSE 0 END) as emails_sent"
            )
            ->selectRaw(
                "SUM(CASE WHEN tm.email_status = 'failed' THEN 1 ELSE 0 END) as emails_failed"
            )
            ->first();

        /*
        |--------------------------------------------------------------------------
        | MONTHLY AI USAGE - SUBSCRIPTION LIMITS
        |--------------------------------------------------------------------------
        |
        | AI quota is owner-wide, across all stores.
        | It always uses the current calendar month.
        | Successful requests consume quota.
        | Reserved requests temporarily consume capacity.
        | Failed requests do not consume quota.
        |
        */

        $aiUsage = $aiUsageLimitService->getUsage($ownerId);

        $monthStart = Carbon::now()->startOfMonth();
        $nextMonth = $monthStart->copy()->addMonth();

        $aiLogs = AiUsageLog::query()
            ->where('user_id', $ownerId)
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<', $nextMonth);

        $aiStats = (clone $aiLogs)
            ->selectRaw(
                "SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"
            )
            ->selectRaw(
                "SUM(CASE WHEN feature = 'ai_reply' AND status = 'completed' THEN 1 ELSE 0 END) as ai_reply"
            )
            ->selectRaw(
                "SUM(CASE WHEN feature = 'kb_reply' AND status = 'completed' THEN 1 ELSE 0 END) as kb_reply"
            )
            ->selectRaw(
                "SUM(CASE WHEN feature = 'ticket_analysis' AND status = 'completed' THEN 1 ELSE 0 END) as ticket_analysis"
            )
            ->first();

        $aiMonthlyUsage = [
            'plan' => $aiUsage['plan'],
            'limit' => $aiUsage['limit'],
            'used' => $aiUsage['used'],
            'remaining' => $aiUsage['remaining'],
            'unlimited' => $aiUsage['unlimited'],
            'allowed' => $aiUsage['allowed'],
            'message' => $aiUsage['message'],

            'completed' => (int) ($aiStats->completed ?? 0),
            'reserved' => (int) ($aiStats->reserved ?? 0),
            'failed' => (int) ($aiStats->failed ?? 0),

            'features' => [
                'ai_reply' => (int) ($aiStats->ai_reply ?? 0),
                'kb_reply' => (int) ($aiStats->kb_reply ?? 0),
                'ticket_analysis' =>
                (int) ($aiStats->ticket_analysis ?? 0),
            ],

            'month' => $monthStart->format('Y-m'),
            'period_start' => $monthStart->toDateString(),
            'period_end' => $nextMonth
                ->copy()
                ->subDay()
                ->toDateString(),

            'scope' => 'owner',
        ];

        /*
        |--------------------------------------------------------------------------
        | ACTIVITY TRENDS
        |--------------------------------------------------------------------------
        */

        $ordersTrend = (clone $orders)
            ->selectRaw(
                "DATE_FORMAT(created_at, '%Y-%m-%d') as date"
            )
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw(
                "DATE_FORMAT(created_at, '%Y-%m-%d')"
            )
            ->orderBy('date')
            ->get();

        $returnsTrend = (clone $returns)
            ->selectRaw(
                "DATE_FORMAT(created_at, '%Y-%m-%d') as date"
            )
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw(
                "DATE_FORMAT(created_at, '%Y-%m-%d')"
            )
            ->orderBy('date')
            ->get();

        $ticketsTrend = (clone $tickets)
            ->selectRaw(
                "DATE_FORMAT(created_at, '%Y-%m-%d') as date"
            )
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw(
                "DATE_FORMAT(created_at, '%Y-%m-%d')"
            )
            ->orderBy('date')
            ->get();

        $trendMap = [];

        foreach (
            [
                'orders' => $ordersTrend,
                'returns' => $returnsTrend,
                'tickets' => $ticketsTrend,
            ] as $type => $rows
        ) {
            foreach ($rows as $row) {
                $date = $row->date;

                if (!isset($trendMap[$date])) {
                    $trendMap[$date] = [
                        'date' => $date,
                        'orders' => 0,
                        'returns' => 0,
                        'tickets' => 0,
                    ];
                }

                $trendMap[$date][$type] = (int) $row->total;
            }
        }

        ksort($trendMap);

        /*
        |--------------------------------------------------------------------------
        | API RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Analytics fetched successfully.',

            'filters' => [
                'store_id' => $storeId,
                'period' => $period,
                'include_demo' => $includeDemo,
                'start_date' => $start->toDateTimeString(),
                'end_date' => $end->toDateTimeString(),
            ],

            'store' => $store,

            'overview' => [
                'total_orders' => (int) $orderStats->total,
                'total_returns' => (int) $returnStats->total,
                'total_tickets' => (int) $ticketStats->total,
                'ai_assisted_messages' =>
                (int) $messageStats->ai_assisted,
                'emails_sent' => (int) $messageStats->emails_sent,
            ],

            'orders' => [
                'total' => (int) $orderStats->total,
                'pending' => (int) $orderStats->pending,
                'completed' => (int) $orderStats->completed,
                'amount_by_currency' => $revenueByCurrency,
            ],

            'returns' => [
                'total' => (int) $returnStats->total,
                'returns' => (int) $returnStats->returns,
                'exchanges' => (int) $returnStats->exchanges,
                'refund_requests' => (int) $returnStats->refunds,
                'pending' => (int) $returnStats->pending,
                'approved' => (int) $returnStats->approved,
                'completed' => (int) $returnStats->completed,
                'rejected' => (int) $returnStats->rejected,
                'completed_refunds_by_currency' => $refundsByCurrency,
            ],

            'tickets' => [
                'total' => (int) $ticketStats->total,
                'open' => (int) $ticketStats->open,
                'in_progress' => (int) $ticketStats->in_progress,
                'waiting_customer' =>
                (int) $ticketStats->waiting_customer,
                'resolved' => (int) $ticketStats->resolved,
                'closed' => (int) $ticketStats->closed,
                'high_priority' =>
                (int) $ticketStats->high_priority,
                'urgent' => (int) $ticketStats->urgent,
                'average_resolution_minutes' =>
                $resolutionRows->average_minutes === null
                    ? null
                    : round(
                        (float) $resolutionRows->average_minutes,
                        2
                    ),
                'categories' => $ticketCategories,
            ],

            'messages' => [
                'total' => (int) $messageStats->total,
                'customer' => (int) $messageStats->customer,
                'owner' => (int) $messageStats->owner,
                'ai' => (int) $messageStats->ai,
                'ai_assisted' =>
                (int) $messageStats->ai_assisted,
                'emails_sent' =>
                (int) $messageStats->emails_sent,
                'emails_failed' =>
                (int) $messageStats->emails_failed,
            ],

            // New subscription AI usage section.
            'ai_monthly_usage' => $aiMonthlyUsage,

            'trends' => array_values($trendMap),
        ]);
    }
}
