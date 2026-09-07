<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    // =========================
    // STUDENT REGISTRATION
    // =========================
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Create User (hashed cast auto-hashes password)
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'student',
        ]);

        // Create empty Student Profile
        Student::create([
            'user_id' => $user->id,
        ]);

        // Auto-login after registration
        Auth::login($user);

        // Create Sanctum token
        $token = $user
            ->createToken('learnspace-api-token', ['*'], now()->addDays(7))
            ->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
            'role' => $user->role,
        ], 201)->withCookie($this->buildAuthCookie($token));
    }


    // =========================
    // LOGIN
    // =========================
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Find User
        $user = User::where('email', $validated['email'])->first();

        // Verify Password
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password'
            ], 401);
        }

        Auth::login($user);

        $token = $user
            ->createToken('learnspace-api-token', ['*'], now()->addDays(7))
            ->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'role' => $user->role,
        ])->withCookie($this->buildAuthCookie($token));
    }


    // =========================
    // LOGOUT
    // =========================
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            $user->tokens()->delete();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logout successful'
        ])->withCookie($this->clearAuthCookie());
    }


    // =========================
    // FORGOT PASSWORD
    // =========================
    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'If this email is registered, a password reset link has been sent.'
            ]);
        }

        $status = Password::broker('users')->sendResetLink(
            ['email' => $validated['email']]
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'If this email is registered, a password reset link has been sent.'
            ]);
        }

        return response()->json([
            'message' => 'Unable to send reset link. Please try again later.'
        ], 500);
    }


    // =========================
    // RESET PASSWORD
    // =========================
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::broker('users')->reset(
            [
                'email'                 => $validated['email'],
                'token'                 => $validated['token'],
                'password'              => $validated['password'],
                'password_confirmation' => $validated['password'],
            ],
            function ($user, $password) {
                $user->forceFill([
                    'password'       => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password has been reset successfully'
            ]);
        }

        return response()->json([
            'message' => 'Invalid or expired reset token'
        ], 400);
    }


    // =========================
    // CURRENT USER
    // =========================
    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }

    // =========================
    // UPDATE PROFILE (Admin)
    // =========================
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'profile_image'  => 'sometimes|nullable|string|max:2048',
            'signature_image' => 'sometimes|nullable|string|max:2048',
        ]);

        $user = $request->user();
        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $user,
        ]);
    }

    private function buildAuthCookie(string $token): Cookie
    {
        $secure = request()->secure() || app()->environment('production');
        $sameSite = $secure ? 'none' : 'lax';

        return cookie(
            config('sanctum.token_cookie', 'learnspace_token'),
            $token,
            (int) config('sanctum.expiration', 10080),
            config('session.path', '/'),
            config('session.domain') ?: null,
            $secure,
            (bool) config('session.http_only', true),
            false,
            $sameSite,
        );
    }

    private function clearAuthCookie(): Cookie
    {
        $secure = request()->secure() || app()->environment('production');
        $sameSite = $secure ? 'none' : 'lax';

        return cookie(
            config('sanctum.token_cookie', 'learnspace_token'),
            '',
            -1,
            config('session.path', '/'),
            config('session.domain') ?: null,
            $secure,
            (bool) config('session.http_only', true),
            false,
            $sameSite,
        );
    }
}
