<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Handle a new user registration request.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $attributes = Arr::except($request->validated(), ['device_name']);
        /** @var User $user */
        $user = User::create($attributes);

        $token = $user->createToken(
            $request->input('device_name', 'default')
        )->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user' => $user,
            'token_type' => 'Bearer',
            'access_token' => $token,
        ], 201);
    }

    /**
     * Handle an authentication attempt.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->getAuthPassword())) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 422);
        }

        $user->tokens()->where('name', $request->input('device_name', 'default'))->delete();

        $token = $user->createToken(
            $request->input('device_name', 'default')
        )->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user,
            'token_type' => 'Bearer',
            'access_token' => $token,
        ]);
    }

    /**
     * Destroy the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }

    /**
     * Get the authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }
}
