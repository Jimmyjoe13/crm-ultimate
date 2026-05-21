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

    public function findCampaign(string $id): ?array
    {
        $all = $this->listCampaigns();
        $campaigns = $all['campaigns'] ?? $all;

        foreach ($campaigns as $c) {
            if (($c['_id'] ?? $c['id'] ?? '') === $id) {
                return $c;
            }
        }

        return null;
    }

    public function addContactToCampaign(string $campaignId, array $payload): array
    {
        $response = $this->http()
            ->timeout(30)
            ->retry(2, 1000)
            ->post($this->url('/graphql'), [
                'query' => 'mutation AddContact($id: ID!, $contact: JSON!) { addContactToCampaignHook(id: $id, contact: $contact) }',
                'variables' => ['id' => $campaignId, 'contact' => $payload],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Emelia addContact failed: '.$response->body());
        }

        $json = $response->json();
        if (! empty($json['errors'])) {
            throw new RuntimeException('Emelia addContact failed: '.$json['errors'][0]['message']);
        }

        return ['id' => $json['data']['addContactToCampaignHook'] ?? null];
    }

    private function http()
    {
        return Http::withToken($this->config['key'])
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout($this->config['timeout'] ?? 15);
    }

    private function url(string $path): string
    {
        return rtrim($this->config['base_url'], '/') . $path;
    }
}
