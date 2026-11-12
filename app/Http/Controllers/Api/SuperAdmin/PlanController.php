<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    /**
     * Get all subscription plans.
     *
     * GET /api/super-admin/plans
     */
    public function index(): JsonResponse
    {
        $plans = Plan::orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Subscription plans fetched successfully.',
            'plans' => $plans,
        ]);
    }

    /**
     * Get a single subscription plan.
     *
     * GET /api/super-admin/plans/{id}
     */
    public function show(int $id): JsonResponse
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription plan not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription plan fetched successfully.',
            'plan' => $plan,
        ]);
    }

    /**
     * Update plan limits and feature access.
     *
     * PUT /api/super-admin/plans/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription plan not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
            'max_stores' => [
                'sometimes',
                'required',
                'integer',
                'min:-1',
            ],
            'max_orders_per_month' => [
                'sometimes',
                'required',
                'integer',
                'min:-1',
            ],
            'max_tickets_per_month' => [
                'sometimes',
                'required',
                'integer',
                'min:-1',
            ],
            'ai_access' => [
                'sometimes',
                'required',
                Rule::in(['none', 'basic', 'full']),
            ],
            'analytics_access' => [
                'sometimes',
                'required',
                Rule::in(['basic', 'advanced']),
            ],
            'returns_enabled' => [
                'sometimes',
                'required',
                'boolean',
            ],
            'is_active' => [
                'sometimes',
                'required',
                'boolean',
            ],
            'sort_order' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid plan fields provided.',
            ], 422);
        }

        /*
         * Intentionally protected:
         *
         * slug
         * price
         * currency
         * billing_interval
         * stripe_price_id
         *
         * Stripe pricing requires a separate,
         * carefully synchronized workflow.
         */

        $plan->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Subscription plan updated successfully.',
            'plan' => $plan->fresh(),
        ]);
    }
}
