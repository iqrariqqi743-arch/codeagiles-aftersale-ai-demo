<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SubscriptionController extends Controller
{
    /**
     * GET /api/super-admin/subscriptions
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min(
                max((int) $request->query('per_page', 10), 1),
                100
            );

            $search = trim((string) $request->query('search', ''));
            $plan = strtolower(trim((string) $request->query('plan', '')));
            $status = strtolower(trim((string) $request->query('status', '')));

            if (!Schema::hasTable('subscriptions')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscriptions table not found.',
                ], 500);
            }

            $query = DB::table('subscriptions')
                ->leftJoin(
                    'users',
                    'subscriptions.user_id',
                    '=',
                    'users.id'
                )
                ->select(
                    'subscriptions.*',
                    'users.name as merchant_name',
                    'users.email as merchant_email',
                    'users.role as merchant_role'
                );

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $q->orWhere(
                            'subscriptions.id',
                            (int) $search
                        );
                    }
                });
            }

            if ($plan !== '') {
                $query->where('subscriptions.plan', $plan);
            }

            if ($status !== '') {
                $query->where('subscriptions.status', $status);
            }

            $subscriptions = $query
                ->orderByDesc('subscriptions.id')
                ->paginate($perPage);

            $stats = [
                'total_subscriptions' => DB::table('subscriptions')->count(),

                'active_subscriptions' => DB::table('subscriptions')
                    ->where('status', 'active')
                    ->count(),

                'trialing_subscriptions' => DB::table('subscriptions')
                    ->where('status', 'trialing')
                    ->count(),

                'canceled_subscriptions' => DB::table('subscriptions')
                    ->whereIn('status', ['canceled', 'cancelled'])
                    ->count(),

                'past_due_subscriptions' => DB::table('subscriptions')
                    ->where('status', 'past_due')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Subscriptions fetched successfully.',
                'stats' => $stats,
                'data' => $subscriptions,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscriptions.',
            ], 500);
        }
    }

    /**
     * GET /api/super-admin/subscriptions/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $subscription = DB::table('subscriptions')
                ->leftJoin(
                    'users',
                    'subscriptions.user_id',
                    '=',
                    'users.id'
                )
                ->select(
                    'subscriptions.*',
                    'users.name as merchant_name',
                    'users.email as merchant_email',
                    'users.role as merchant_role'
                )
                ->where('subscriptions.id', $id)
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found.',
                ], 404);
            }

            $plan = DB::table('plans')
                ->where('slug', $subscription->plan)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Subscription details fetched successfully.',
                'subscription' => $subscription,
                'plan_details' => $plan,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscription details.',
            ], 500);
        }
    }
}
