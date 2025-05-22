<?php

namespace App\Http\Controllers;
//
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\RateLimiter;
use App\Notifications\ResetPasswordNotification;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();
    
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'user',
        ]);
    
        $token = $user->createToken('auth_token')->plainTextToken;
    
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        if (RateLimiter::tooManyAttempts('login:'.$request->ip(), 5)) {
            return response()->json(['message' => 'Too many login attempts. Please try again later.'], 429);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            RateLimiter::hit('login:'.$request->ip(), 60);
            return response()->json(['message' => 'Invalid login details'], 401);
        }

        RateLimiter::clear('login:'.$request->ip());

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'role' => $request->user()->role
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        if (RateLimiter::tooManyAttempts('password-reset:'.$request->ip(), 5)) {
            return response()->json(['message' => 'Too many password reset attempts. Please try again later.'], 429);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            RateLimiter::hit('password-reset:'.$request->ip(), 60);
            return response()->json(['message' => 'If this email exists in our system, a reset link has been sent.']);
        }

        $token = Password::createToken($user);
        $resetUrl = config('app.frontend_url').'/reset-password?token='.$token.'&email='.urlencode($user->email);

        $user->notify(new ResetPasswordNotification($token, $resetUrl));

        RateLimiter::hit('password-reset:'.$request->ip(), 60);

        return response()->json(['message' => 'Password reset link sent to your email']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $throttleKey = 'password-reset:'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json(['message' => 'Too many password reset attempts. Please try again later.'], 429);
        }
        RateLimiter::hit($throttleKey, 60);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Your password has been reset successfully.']);
        }

        return response()->json(['message' => 'This password reset token is invalid or has expired.'], 400);
    }
}
