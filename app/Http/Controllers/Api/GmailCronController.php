
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GmailImapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class GmailCronController extends Controller
{
    public function sync(
        Request $request,
        GmailImapService $gmailService
    ): JsonResponse {
        $secret = config('services.gmail_cron.secret');

        $providedSecret = $request->header('X-Cron-Secret', '');

        if (
            !is_string($secret) ||
            strlen($secret) < 32 ||
            !hash_equals($secret, $providedSecret)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $lock = Cache::lock('gmail-cron-sync-lock', 300);

        if (!$lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'Gmail sync already running',
            ], 409);
        }

        try {
            $stats = $gmailService->syncCustomerReplies();

            Log::info('Production Gmail cron completed', [
                'stats' => $stats,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gmail sync completed',
                'stats' => $stats,
            ]);

        } catch (Throwable $e) {
            Log::error('Production Gmail cron failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gmail sync failed',
            ], 500);

        } finally {
            $lock->release();
        }
    }
}
