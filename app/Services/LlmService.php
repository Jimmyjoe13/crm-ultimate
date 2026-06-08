<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class LlmService
{
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $config = config('services.openrouter');

        if (empty($config['key'])) {
            throw new RuntimeException('LLM provider not configured.');
        }

        $response = Http::withToken($config['key'])
            ->withHeaders([
                'HTTP-Referer' => config('app.url'),
                'X-Title' => 'CRM Ultimate',
            ])
            ->timeout($config['timeout'] ?? 30)
            ->retry(2, 500)
            ->post($config['base_url'].'/chat/completions', array_merge([
                'model' => $config['model'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 500,
            ], $options));

        if ($response->failed()) {
            throw new RuntimeException('LLM request failed: '.$response->body());
        }

        return $response->json('choices.0.message.content') ?? '';
    }
}
