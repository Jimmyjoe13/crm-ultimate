<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmeliaService
{
    private readonly array $config;

    public function __construct()
    {
        $this->config = config('services.emelia');

        if (empty($this->config['key'])) {
            throw new RuntimeException('Emelia API key not configured.');
        }
    }

    public function listCampaigns(): array
    {
        $response = $this->http()->get($this->url('/campaigns'));

        if ($response->failed()) {
            throw new RuntimeException('Emelia listCampaigns failed: '.$response->body());
        }

        return $response->json() ?? [];
    }

    public function addContactToCampaign(string $campaignId, array $payload): array
    {
        $response = $this->http()->post($this->url("/campaigns/{$campaignId}/contacts"), $payload);

        if ($response->failed()) {
            throw new RuntimeException('Emelia addContact failed: '.$response->body());
        }

        return $response->json() ?? [];
    }

    private function http()
    {
        return Http::withToken($this->config['key'])
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout($this->config['timeout'] ?? 15)
            ->retry(2, 200);
    }

    private function url(string $path): string
    {
        return rtrim($this->config['base_url'], '/') . $path;
    }
}
