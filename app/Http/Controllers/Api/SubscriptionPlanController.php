<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class SubscriptionPlanController extends Controller
{
    /**
     * Fetch active subscription plans for owners.
     *
     * GET /api/subscription/plans
     */
    public function index(): JsonResponse
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'slug',
                'description',
                'price',
                'currency',
                'billing_interval',
                'max_stores',
                'max_orders_per_month',
                'max_tickets_per_month',
                'ai_access',
                'analytics_access',
                'returns_enabled',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription plans fetched successfully.',
            'plans' => $plans,
        ]);
    }
}
