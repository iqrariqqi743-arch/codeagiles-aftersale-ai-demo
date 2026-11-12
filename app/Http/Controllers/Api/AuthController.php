<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new merchant owner.
     */
    public function register(Request $request)
    {
        // Registration setting must be checked on the backend.
        if (!Schema::hasTable('platform_settings')) {
            return response()->json([
                'success' => false,
                'message' => 'Registration is temporarily unavailable.',
            ], 503);
        }

        $registrationSetting = DB::table('platform_settings')
            ->where('key', 'allow_registration')
            ->value('value');

        if (
            $registrationSetting !== null &&
            !filter_var($registrationSetting, FILTER_VALIDATE_BOOLEAN)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'New merchant registration is currently disabled.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8),
            ],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'owner',
            'status' => 'active',
        ]);

        // Registration does not automatically activate a paid subscription.
        // The merchant must select a plan through Stripe checkout.
        $token = $user->createToken('aftersale-ai-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Merchant account created successfully.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'owner',
            ],
        ], 201);
    }

    /**
     * Login owner or super admin.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (
            !$user ||
            !Hash::check($validated['password'], $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        // Check the actual user's status.
        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact support admin.',
            ], 403);
        }

        $role = strtolower(
            str_replace([' ', '-'], '_', $user->role ?? '')
        );

        if (!in_array($role, ['owner', 'super_admin', 'superadmin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Your account does not have dashboard access.',
            ], 403);
        }

        $token = $user->createToken('aftersale-ai-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
            ],
        ]);
    }

    /**
     * Get authenticated user.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Logout current token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Send password reset email.
     */
    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Laravel password broker handles reset token creation
        // and sends a reset notification via configured mail.
        Password::broker()->sendResetLink([
            'email' => $validated['email'],
        ]);

        // Generic response avoids exposing registered email addresses.
        return response()->json([
            'success' => true,
            'message' => 'If this email is registered, a password reset link will be sent.',
        ]);
    }

    /**
     * Reset password using email token.
     */
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8),
            ],
        ]);

        $status = Password::broker()->reset(
            $validated,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke existing API sessions after password reset.
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => 'The password reset link is invalid or has expired.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. Please log in.',
        ]);
    }

    /**
     * Change password for an authenticated user.
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8),
            ],
        ]);

        $user = $request->user();

        if (!Hash::check(
            $validated['current_password'],
            $user->password
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
                'errors' => [
                    'current_password' => [
                        'Current password is incorrect.',
                    ],
                ],
            ], 422);
        }

        if (Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'New password must be different from the current password.',
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        // Revoke other sessions but keep the current session active.
        $currentTokenId = $user->currentAccessToken()?->id;

        if ($currentTokenId) {
            $user->tokens()
                ->where('id', '!=', $currentTokenId)
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }
}
