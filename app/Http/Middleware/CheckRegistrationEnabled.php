<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CheckRegistrationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        // Fail closed if settings are unavailable.
        try {
            if (!Schema::hasTable('platform_settings')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration is temporarily unavailable.',
                ], 503);
            }

            $setting = DB::table('platform_settings')
                ->where('key', 'allow_registration')
                ->first();

            // Default registration to enabled when no setting exists.
            $registrationEnabled = $setting === null
                ? true
                : filter_var(
                    $setting->value,
                    FILTER_VALIDATE_BOOLEAN
                );

            if (!$registrationEnabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'New account registration is currently disabled. Please contact support.',
                ], 403);
            }

            return $next($request);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Registration is temporarily unavailable.',
            ], 503);
        }
    }
}
