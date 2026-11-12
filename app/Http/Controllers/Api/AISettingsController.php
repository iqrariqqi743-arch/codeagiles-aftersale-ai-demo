<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreAiSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AISettingsController extends Controller
{
    /**
     * Default AI settings for a store.
     */
    private function defaultSettings(): array
    {
        return [
            'ai_enabled' => false,
            'reply_tone' => 'professional',
            'reply_language' => 'English',
            'return_window_days' => 7,
            'return_policy' => '',
            'refund_policy' => '',
            'custom_instructions' => '',
            'auto_reply_enabled' => false,
        ];
    }

    /**
     * GET /api/ai/settings/{storeId}
     *
     * Fetch AI settings for an owned store.
     */
    public function getAISettings(
        Request $request,
        $storeId
    ): JsonResponse {
        if (!ctype_digit((string) $storeId) || (int) $storeId < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid store ID',
            ], 400);
        }

        $store = Store::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($storeId)
            ->first();

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found',
            ], 404);
        }

        $settings = StoreAiSetting::query()
            ->where('store_id', $store->id)
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'AI settings fetched successfully',
            'store' => [
                'id' => $store->id,
                'store_name' => $store->store_name ?? $store->name,
            ],
            'settings' => $settings
                ? $settings->toArray()
                : array_merge(
                    $this->defaultSettings(),
                    [
                        'user_id' => $request->user()->id,
                        'store_id' => $store->id,
                    ]
                ),
        ]);
    }

    /**
     * PUT /api/ai/settings/{storeId}
     *
     * Create or update AI settings for an owned store.
     */
    public function updateAISettings(
        Request $request,
        $storeId
    ): JsonResponse {
        if (!ctype_digit((string) $storeId) || (int) $storeId < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid store ID',
            ], 400);
        }

        $store = Store::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($storeId)
            ->first();

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'ai_enabled' => ['sometimes', 'required', 'boolean'],

            'reply_tone' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'professional',
                    'friendly',
                    'empathetic',
                    'concise',
                ]),
            ],

            'reply_language' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                function ($attribute, $value, $fail) {
                    if (trim($value) === '') {
                        $fail('Reply language is required.');
                    }
                },
            ],

            'return_window_days' => [
                'sometimes',
                'required',
                'integer',
                'between:0,365',
            ],

            'return_policy' => [
                'sometimes',
                'required',
                'string',
                'max:10000',
            ],

            'refund_policy' => [
                'sometimes',
                'required',
                'string',
                'max:10000',
            ],

            'custom_instructions' => [
                'sometimes',
                'required',
                'string',
                'max:10000',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid AI settings',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $current = StoreAiSetting::query()
            ->where('store_id', $store->id)
            ->where('user_id', $request->user()->id)
            ->first();

        $settingsData = array_merge(
            $this->defaultSettings(),
            $current ? $current->only([
                'ai_enabled',
                'reply_tone',
                'reply_language',
                'return_window_days',
                'return_policy',
                'refund_policy',
                'custom_instructions',
            ]) : [],
            $validated
        );

        // Automatic sending is intentionally disabled.
        $settingsData['auto_reply_enabled'] = false;

        $settingsData['reply_language'] = trim(
            $settingsData['reply_language']
        );

        $settings = StoreAiSetting::updateOrCreate(
            [
                'store_id' => $store->id,
                'user_id' => $request->user()->id,
            ],
            $settingsData
        );

        return response()->json([
            'success' => true,
            'message' => 'AI settings updated successfully',
            'settings' => $settings->fresh(),
        ]);
    }
}
