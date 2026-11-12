<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // =====================================
            // PLATFORM STATISTICS
            // =====================================

            $totalUsers = DB::table('users')->count();

            $totalMerchants = DB::table('users')
                ->where('role', 'merchant')
                ->count();

            $totalSuperAdmins = DB::table('users')
                ->where('role', 'super_admin')
                ->count();

            $totalStores = Schema::hasTable('stores')
                ? DB::table('stores')->count()
                : 0;

            $totalOrders = Schema::hasTable('orders')
                ? DB::table('orders')->count()
                : 0;

            $totalReturns = Schema::hasTable('return_requests')
                ? DB::table('return_requests')->count()
                : 0;

            $totalTickets = Schema::hasTable('support_tickets')
                ? DB::table('support_tickets')->count()
                : 0;

            // =====================================
            // SUBSCRIPTIONS
            // =====================================

            $totalSubscriptions = Schema::hasTable('subscriptions')
                ? DB::table('subscriptions')->count()
                : 0;

            // =====================================
            // RECENT USERS
            // =====================================

            $recentUsers = DB::table('users')
                ->select('id', 'name', 'email', 'role', 'created_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            // =====================================
            // RECENT STORES
            // =====================================

            $recentStores = Schema::hasTable('stores')
                ? DB::table('stores')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                : collect();

            return response()->json([
                'success' => true,
                'message' => 'Super Admin dashboard fetched successfully.',

                'overview' => [
                    'total_users' => $totalUsers,
                    'total_merchants' => $totalMerchants,
                    'total_super_admins' => $totalSuperAdmins,
                    'total_stores' => $totalStores,
                    'total_orders' => $totalOrders,
                    'total_returns' => $totalReturns,
                    'total_tickets' => $totalTickets,
                    'total_subscriptions' => $totalSubscriptions,
                ],

                'recent_users' => $recentUsers,
                'recent_stores' => $recentStores,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch Super Admin dashboard.',
            ], 500);
        }
    }
}
