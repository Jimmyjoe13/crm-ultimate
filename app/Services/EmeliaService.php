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

    /**
     * Retourne les données Emelia d'un contact via son email.
     * Utilise contacts(query) GraphQL → filtre par nom de campagne.
     * Résultat mis en cache 10 min pour ne pas surcharger l'API.
     */
    public function getContactByEmail(string $email, ?string $campaignName = null): ?array
    {
        $gql = 'query($q: String!) { contacts(query: $q) { _id email status mailsSent lastOpen lastContacted lastReplied campaigns } }';

        $response = $this->http()
            ->timeout(10)
            ->post($this->url('/graphql'), [
                'query'     => $gql,
                'variables' => ['q' => $email],
            ]);

        if ($response->failed()) {
            return null;
        }

        $contacts = $response->json('data.contacts') ?? [];

        if (empty($contacts)) {
            return null;
        }

        // Filtrer par nom de campagne si fourni
        if ($campaignName) {
            foreach ($contacts as $c) {
                if (in_array($campaignName, $c['campaigns'] ?? [])) {
                    return $c;
                }
            }
        }

        // Prendre le contact avec un statut non-null en priorité
        foreach ($contacts as $c) {
            if ($c['status'] !== null) {
                return $c;
            }
        }

        return $contacts[0];
    }

    /**
     * Retourne les données Emelia d'un contact via son ID Emelia (plus rapide).
     */
    public function getContactById(string $emeliaid, string $campaignId): ?array
    {
        $gql = 'query($id: ID!, $cid: ID!) { contact(id: $id, campaignId: $cid) { _id email status mailsSent lastOpen lastContacted lastReplied } }';

        $response = $this->http()
            ->timeout(10)
            ->post($this->url('/graphql'), [
                'query'     => $gql,
                'variables' => ['id' => $emeliaid, 'campaignId' => $campaignId],
            ]);

        if ($response->failed()) {
            return null;
        }

        return $response->json('data.contact');
    }

    /**
     * Retourne la liste des events synthétiques d'un contact Emelia.
     *
     * Tente d'abord une requête GraphQL enrichie avec un champ `activities`.
     * Si Emelia ne supporte pas ce champ, fall back sur les agrégats
     * (lastContacted, lastOpen, lastReplied, status) pour dériver des events.
     *
     * Chaque event retourné : ['type' => 'OPENED|REPLIED|SENT|BOUNCED|UNSUBSCRIBED', 'date' => Carbon]
     */
    public function getContactEvents(string $emeliaContactId, string $campaignId): array
    {
        // Tentative GraphQL enrichie (champ activities non documenté, présent dans certaines versions)
        $gql = 'query($id: ID!, $cid: ID!) {
            contact(id: $id, campaignId: $cid) {
                _id email status mailsSent lastOpen lastContacted lastReplied
                activities { type date }
            }
        }';

        $response = $this->http()
            ->timeout(10)
            ->post($this->url('/graphql'), [
                'query'     => $gql,
                'variables' => ['id' => $emeliaContactId, 'campaignId' => $campaignId],
            ]);

        $data = $response->json('data.contact');

        // Si Emelia retourne le champ activities (liste d'events horodatés), on les utilise
        if (is_array($data) && isset($data['activities']) && is_array($data['activities'])) {
            return array_map(fn($a) => [
                'type' => $a['type'],
                'date' => \Carbon\Carbon::parse($a['date']),
            ], array_filter($data['activities'], fn($a) => isset($a['type'], $a['date'])));
        }

        // Fallback : dériver des events depuis les agrégats
        if (! is_array($data)) {
            // Retenter avec la query simple existante (sans activities)
            $gql2 = 'query($id: ID!, $cid: ID!) { contact(id: $id, campaignId: $cid) { _id email status mailsSent lastOpen lastContacted lastReplied } }';
            $r2   = $this->http()->timeout(10)->post($this->url('/graphql'), [
                'query'     => $gql2,
                'variables' => ['id' => $emeliaContactId, 'campaignId' => $campaignId],
            ]);
            $data = $r2->json('data.contact');
        }

        if (! is_array($data)) {
            return [];
        }

        $events = [];

        if (! empty($data['lastContacted'])) {
            $events[] = ['type' => 'SENT', 'date' => \Carbon\Carbon::parse($data['lastContacted'])];
        }

        if (! empty($data['lastOpen'])) {
            $events[] = ['type' => 'OPENED', 'date' => \Carbon\Carbon::parse($data['lastOpen'])];
        }

        if (! empty($data['lastReplied'])) {
            $events[] = ['type' => 'REPLIED', 'date' => \Carbon\Carbon::parse($data['lastReplied'])];
        }

        if (($data['status'] ?? null) === 'BOUNCED') {
            $events[] = ['type' => 'BOUNCED', 'date' => \Carbon\Carbon::parse($data['lastContacted'] ?? now())];
        }

        if (($data['status'] ?? null) === 'UNSUBSCRIBED') {
            $events[] = ['type' => 'UNSUBSCRIBED', 'date' => \Carbon\Carbon::parse($data['lastContacted'] ?? now())];
        }

        return $events;
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
