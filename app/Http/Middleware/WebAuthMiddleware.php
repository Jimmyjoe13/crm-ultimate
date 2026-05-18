<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WebAuthMiddleware
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('crm_jwt');

        if (! $token) {
            return redirect()->route('login');
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (\Throwable $e) {
            Log::error('JWT decode failed', ['error' => $e->getMessage()]);
            return redirect()->route('login')->withCookie(cookie()->forget('crm_jwt'));
        }

        $user = User::query()->find($payload['sub'] ?? null);

        if (! $user) {
            return redirect()->route('login')->withCookie(cookie()->forget('crm_jwt'));
        }

        Auth::setUser($user);

        return $next($request);
    }
}
