<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly JwtService $jwt) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        return response()->json($this->tokenResponse($user));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }

    public function refresh(Request $request): JsonResponse
    {
        return response()->json($this->tokenResponse($request->user()));
    }

    public function logout(): JsonResponse
    {
        return response()->json(['message' => 'Token discarded client-side.']);
    }

    private function tokenResponse(User $user): array
    {
        $now = now();
        $expiresAt = $now->copy()->addMinutes(config('jwt.ttl_minutes'));

        $token = $this->jwt->encode([
            'iss' => config('jwt.issuer'),
            'iat' => $now->timestamp,
            'exp' => $expiresAt->timestamp,
            'sub' => $user->id,
            'role' => $user->role,
        ]);

        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => $user,
        ];
    }
}
