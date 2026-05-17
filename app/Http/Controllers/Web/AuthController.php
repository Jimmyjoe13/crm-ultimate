<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly JwtService $jwt) {}

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower($credentials['email']);
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'Identifiants invalides.'])->withInput();
        }

        $now = now();
        $expiresAt = $now->copy()->addMinutes(config('jwt.ttl_minutes', 1440));

        $token = $this->jwt->encode([
            'iss' => config('jwt.issuer'),
            'iat' => $now->timestamp,
            'exp' => $expiresAt->timestamp,
            'sub' => $user->id,
            'role' => $user->role,
        ]);

        return redirect()->route('dashboard')
            ->withCookie(cookie('crm_jwt', $token, config('jwt.ttl_minutes', 1440), '/', null, false, true));
    }

    public function logout()
    {
        return redirect()->route('login')->withCookie(cookie()->forget('crm_jwt'));
    }
}
