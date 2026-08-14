<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required',
        ]);

        $login = trim($request->login);
        $ip = $request->ip() ?? '127.0.0.1';
        $throttleKey = 'login|' . $login . '|' . $ip;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = ceil($seconds / 60);
            return response()->json([
                'message' => "Too many login attempts. For security, your account is locked for {$minutes} minutes."
            ], 429);
        }

        try {
            // Support login by email OR id_number
            $user = User::where('email', $login)
                        ->orWhere('id_number', $login)
                        ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                RateLimiter::hit($throttleKey, 1800); // 30 minutes lockout
                $attempts = RateLimiter::attempts($throttleKey);
                $remaining = max(0, 5 - $attempts);
                
                Log::warning("Failed login attempt #{$attempts} for {$login} from {$ip}");

                return response()->json([
                    'message' => 'Invalid credentials. You have ' . $remaining . ' attempts remaining before lockout.'
                ], 401);
            }

            if (!$user->is_active) {
                RateLimiter::clear($throttleKey);
                return response()->json([
                    'message' => 'Your account has been deactivated. Please contact the administrator.'
                ], 403);
            }

            RateLimiter::clear($throttleKey);
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $this->formatUser($user->load('roles', 'permissions', 'student', 'faculty')),
                'permissions' => $user->getAllPermissions()->pluck('name')
            ]);
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'message' => 'System error occurred during login',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return response()->json([
                'message' => 'Logged out successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json([
                'message' => 'System error occurred during logout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->formatUser($user->load('roles', 'permissions', 'student', 'faculty')),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        if (! in_array($user->role, ['student', 'faculty'], true)) {
            return response()->json(['message' => 'You are not allowed to change your password.'], 403);
        }

        $hasPassword = filled($user->password);

        $rules = [
            'password' => ['required', 'string', 'confirmed', new PasswordPolicy],
        ];

        if ($hasPassword) {
            $rules['current_password'] = ['required', 'string'];
        }

        $validated = $request->validate($rules);

        if ($hasPassword) {
            if (! Hash::check($validated['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['The current password is incorrect.'],
                ]);
            }

            if (Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'password' => ['The new password must be different from your current password.'],
                ]);
            }
        }

        $user->update(['password' => $validated['password']]);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    private function formatUser(User $user): array
    {
        $data = $user->toArray();
        $data['has_password'] = filled($user->password);

        return $data;
    }
}
