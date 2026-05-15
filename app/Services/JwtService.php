<?php

namespace App\Services;

use RuntimeException;

class JwtService
{
    public function encode(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $this->secret(), true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public function decode(string $token): array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw new RuntimeException('Invalid token format.');
        }

        [$header, $payload, $signature] = $segments;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $header.'.'.$payload, $this->secret(), true));

        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid token signature.');
        }

        $decoded = json_decode($this->base64UrlDecode($payload), true, flags: JSON_THROW_ON_ERROR);

        if (($decoded['exp'] ?? 0) < time()) {
            throw new RuntimeException('Expired token.');
        }

        return $decoded;
    }

    private function secret(): string
    {
        $secret = config('jwt.secret');

        if (! $secret) {
            throw new RuntimeException('JWT secret is not configured.');
        }

        return $secret;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }
}
