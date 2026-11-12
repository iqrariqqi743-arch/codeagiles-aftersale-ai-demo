<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Get platform settings.
     */
    public function index()
    {
        try {
            if (!Schema::hasTable('platform_settings')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Platform settings table does not exist. Please run migrations.',
                ], 500);
            }

            $settings = DB::table('platform_settings')
                ->pluck('value', 'key')
                ->toArray();

            $defaults = [
                'platform_name' => 'CodeAgiles AfterSale AI',
                'support_email' => '',
                'support_phone' => '',
                'platform_description' => '',
                'maintenance_mode' => '0',
                'allow_registration' => '1',
            ];

            $settings = array_merge($defaults, $settings);

            $settings['maintenance_mode'] =
                filter_var($settings['maintenance_mode'], FILTER_VALIDATE_BOOLEAN);

            $settings['allow_registration'] =
                filter_var($settings['allow_registration'], FILTER_VALIDATE_BOOLEAN);

            return response()->json([
                'success' => true,
                'message' => 'Platform settings fetched successfully.',
                'settings' => $settings,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch platform settings.',
            ], 500);
        }
    }

    /**
     * Update platform settings.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'platform_name' => 'required|string|max:150',
            'support_email' => 'nullable|email|max:255',
            'support_phone' => 'nullable|string|max:30',
            'platform_description' => 'nullable|string|max:1000',
            'maintenance_mode' => 'required|boolean',
            'allow_registration' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if (!Schema::hasTable('platform_settings')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Platform settings table does not exist. Please run migrations.',
                ], 500);
            }

            $validated = $validator->validated();

            DB::transaction(function () use ($validated) {
                foreach ($validated as $key => $value) {
                    DB::table('platform_settings')->updateOrInsert(
                        ['key' => $key],
                        [
                            'value' => is_bool($value)
                                ? ($value ? '1' : '0')
                                : $value,
                            'updated_at' => now(),
                        ]
                    );
                }
            });

            return $this->index();
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update platform settings.',
            ], 500);
        }
    }
}
