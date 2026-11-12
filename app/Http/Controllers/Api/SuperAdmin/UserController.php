<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class UserController extends Controller
{
    // ==========================================
    // GET ALL USERS
    // ==========================================

    public function index(Request $request): JsonResponse
    {
        try {
            $search = trim((string) $request->query('search', ''));
            $role = $request->query('role', '');
            $status = $request->query('status', '');

            $perPage = min(
                max((int) $request->query('per_page', 10), 1),
                100
            );

            $query = User::query()
                ->select(
                    'id',
                    'name',
                    'email',
                    'role',
                    'status',
                    'created_at'
                );

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
            }

            if (in_array($role, ['owner', 'super_admin'], true)) {
                $query->where('role', $role);
            }

            if (in_array($status, ['active', 'inactive'], true)) {
                $query->where('status', $status);
            }

            $users = $query
                ->orderByDesc('id')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Users fetched successfully.',
                'stats' => [
                    'total_users' => User::count(),
                    'total_owners' => User::where('role', 'owner')->count(),
                    'total_super_admins' => User::where('role', 'super_admin')->count(),
                    'total_active' => User::where('status', 'active')->count(),
                    'total_inactive' => User::where('status', 'inactive')->count(),
                ],
                'data' => $users,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users.',
            ], 500);
        }
    }

    // ==========================================
    // GET SINGLE USER
    // ==========================================

    public function show(int $id): JsonResponse
    {
        $user = User::query()
            ->select(
                'id',
                'name',
                'email',
                'role',
                'status',
                'created_at'
            )
            ->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $totalStores = 0;

        if ($user->role === 'owner') {
            $totalStores = DB::table('stores')
                ->where('user_id', $user->id)
                ->count();
        }

        return response()->json([
            'success' => true,
            'message' => 'User details fetched successfully.',
            'user' => $user,
            'total_stores' => $totalStores,
        ]);
    }

    // ==========================================
    // CREATE USER
    // ==========================================

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => ['required', 'string', 'min:8'],
            'role' => [
                'required',
                Rule::in(['owner', 'super_admin']),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();

            $user = new User();

            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->password = Hash::make($data['password']);
            $user->role = $data['role'];
            $user->status = 'active';

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'User created successfully.',
                'user' => $user->only([
                    'id',
                    'name',
                    'email',
                    'role',
                    'status',
                    'created_at',
                ]),
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create user.',
            ], 500);
        }
    }

    // ==========================================
    // UPDATE USER
    // ==========================================

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => [
                'required',
                Rule::in(['owner', 'super_admin']),
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

        if (
            (int) $request->user()->id === (int) $user->id &&
            $data['role'] !== $user->role
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot change your own role.',
            ], 403);
        }

        try {
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->role = $data['role'];

            if (!empty($data['password'])) {
                $user->password = Hash::make($data['password']);

                // Revoke existing API tokens after password change.
                $user->tokens()->delete();
            }

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully.',
                'user' => $user->only([
                    'id',
                    'name',
                    'email',
                    'role',
                    'status',
                    'created_at',
                ]),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user.',
            ], 500);
        }
    }

    // ==========================================
    // ACTIVATE / DEACTIVATE USER
    // ==========================================

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => [
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newStatus = $validator->validated()['status'];

        if (
            (int) $request->user()->id === (int) $user->id &&
            $newStatus === 'inactive'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot deactivate your own account.',
            ], 403);
        }

        if (
            $user->role === 'super_admin' &&
            $user->status === 'active' &&
            $newStatus === 'inactive' &&
            User::where('role', 'super_admin')
            ->where('status', 'active')
            ->count() <= 1
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot deactivate the last active Super Admin.',
            ], 403);
        }

        try {
            $user->status = $newStatus;
            $user->save();

            if ($newStatus === 'inactive') {
                $user->tokens()->delete();
            }

            return response()->json([
                'success' => true,
                'message' => $newStatus === 'active'
                    ? 'User activated successfully.'
                    : 'User deactivated successfully.',
                'user' => $user->only([
                    'id',
                    'name',
                    'email',
                    'role',
                    'status',
                ]),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status.',
            ], 500);
        }
    }

    // ==========================================
    // DELETE USER
    // ==========================================

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 403);
        }

        if (
            $user->role === 'super_admin' &&
            User::where('role', 'super_admin')->count() <= 1
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete the last Super Admin.',
            ], 403);
        }

        // Prevent deleting an owner who still has stores.
        // Stores may have orders, tickets, returns, etc.
        if (
            $user->role === 'owner' &&
            DB::table('stores')->where('user_id', $user->id)->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'This owner has stores. Remove or transfer their stores before deleting the account.',
            ], 409);
        }

        try {
            $user->tokens()->delete();
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully.',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user. The account may have related records.',
            ], 500);
        }
    }
}
