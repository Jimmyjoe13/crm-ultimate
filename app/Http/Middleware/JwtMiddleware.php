<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->cookie('crm_jwt');

        if (! $token) {
            return response()->json(['message' => 'Missing bearer token.'], 401);
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (\Throwable) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        $user = User::query()->find($payload['sub'] ?? null);

        if (! $user) {
            return response()->json(['message' => 'User not found.'], 401);
        }

        Auth::setUser($user);

        return $next($request);
    }
}
