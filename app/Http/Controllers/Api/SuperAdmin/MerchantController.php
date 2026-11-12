<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MerchantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $search = trim((string) $request->query('search', ''));

            $perPage = min(
                max((int) $request->query('per_page', 10), 1),
                100
            );

            $merchants = DB::table('users')
                ->where('users.role', 'owner')
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.role',
                    'users.created_at'
                )
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('users.name', 'like', "%{$search}%")
                            ->orWhere('users.email', 'like', "%{$search}%");
                    });
                });

            // Count stores owned by each merchant
            if (Schema::hasTable('stores')) {
                $merchants->addSelect(
                    DB::raw(
                        '(SELECT COUNT(*) FROM stores
                          WHERE stores.user_id = users.id) as total_stores'
                    )
                );
            }

            $result = $merchants
                ->orderByDesc('users.id')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Merchants fetched successfully.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch merchants.',
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $merchant = DB::table('users')
                ->where('id', $id)
                ->where('role', 'owner')
                ->select(
                    'id',
                    'name',
                    'email',
                    'role',
                    'created_at'
                )
                ->first();

            if (!$merchant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Merchant not found.',
                ], 404);
            }

            $stores = Schema::hasTable('stores')
                ? DB::table('stores')
                ->where('user_id', $id)
                ->select(
                    'id',
                    'store_name',
                    'store_url',
                    'platform',
                    'status',
                    'connected_at',
                    'last_synced_at',
                    'created_at'
                )
                ->orderByDesc('id')
                ->get()
                : collect();

            return response()->json([
                'success' => true,
                'message' => 'Merchant details fetched successfully.',
                'merchant' => $merchant,
                'stores' => $stores,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch merchant details.',
            ], 500);
        }
    }
}
