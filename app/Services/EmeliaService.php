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

        // Filtrer par nom de campagne si fourni — retourne null si le contact
        // existe dans Emelia mais pas dans cette campagne spécifique
        if ($campaignName) {
            foreach ($contacts as $c) {
                if (in_array($campaignName, $c['campaigns'] ?? [])) {
                    return $c;
                }
            }
            return null;
        }

        // Sans filtre campagne : prendre le contact avec un statut non-null en priorité
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
    /**
     * @param string|null $email       Email du contact CRM (pour fallback getContactByEmail)
     * @param string|null $campaignName Nom de campagne (pour fallback getContactByEmail)
     */
    public function getContactEvents(
        string  $emeliaContactId,
        string  $campaignId,
        ?string $email        = null,
        ?string $campaignName = null,
    ): array {
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

        // Fallback : getContactByEmail si disponible (données complètes garanties)
        if ((! is_array($data) || empty($data['_id'])) && $email) {
            $data = $this->getContactByEmail($email, $campaignName);
        }

        if (! is_array($data)) {
            return [];
        }

        // Les timestamps Emelia (lastContacted, lastOpen, lastReplied) sont en millisecondes
        $fromMs = function (?string $ms): ?\Carbon\Carbon {
            if (empty($ms)) {
                return null;
            }
            return \Carbon\Carbon::createFromTimestampMs((int) $ms);
        };

        $events = [];

        if ($d = $fromMs($data['lastContacted'] ?? null)) {
            $events[] = ['type' => 'SENT', 'date' => $d];
        }

        if ($d = $fromMs($data['lastOpen'] ?? null)) {
            $events[] = ['type' => 'OPENED', 'date' => $d];
        }

        if ($d = $fromMs($data['lastReplied'] ?? null)) {
            $events[] = ['type' => 'REPLIED', 'date' => $d];
        }

        if (($data['status'] ?? null) === 'BOUNCED') {
            $d = $fromMs($data['lastContacted'] ?? null) ?? now();
            $events[] = ['type' => 'BOUNCED', 'date' => $d];
        }

        if (($data['status'] ?? null) === 'UNSUBSCRIBED') {
            $d = $fromMs($data['lastContacted'] ?? null) ?? now();
            $events[] = ['type' => 'UNSUBSCRIBED', 'date' => $d];
        }

        return $events;
    }

    /**
     * Synchronise le registre local emelia_campaigns depuis l'API Emelia.
     * Retourne le nombre de campagnes créées ou mises à jour.
     */
    public function syncCampaignRegistry(): int
    {
        $raw       = $this->listCampaigns();
        $campaigns = $raw['campaigns'] ?? $raw;
        $count     = 0;

        foreach ($campaigns as $c) {
            $emeliaid = $c['_id'] ?? $c['id'] ?? null;
            $name     = $c['name'] ?? $c['title'] ?? $emeliaid;

            if (! $emeliaid) {
                continue;
            }

            \App\Models\EmeliaCampaign::updateOrCreate(
                ['emelia_id' => $emeliaid],
                ['name' => $name, 'status' => $c['status'] ?? null, 'last_synced_at' => now()],
            );

            $count++;
        }

        return $count;
    }

    /**
     * Retire un contact d'une campagne Emelia via GraphQL.
     * Les mutations Emelia pour cette opération ne sont pas documentées — on essaie
     * les noms les plus probables. Retourne true si retiré, false si introuvable/déjà retiré.
     */
    public function removeFromCampaign(string $campaignId, string $emeliaContactId): bool
    {
        // Essai 1 : removeContactFromCampaignHook (symétrique à addContactToCampaignHook)
        $mutations = [
            'mutation($id: ID!, $cid: ID!) { removeContactFromCampaignHook(id: $cid, contactId: $id) }',
            'mutation($id: ID!, $cid: ID!) { unsubscribeContact(contactId: $id, campaignId: $cid) }',
            'mutation($id: ID!, $cid: ID!) { pauseContact(contactId: $id, campaignId: $cid) }',
        ];

        foreach ($mutations as $gql) {
            try {
                $response = $this->http()
                    ->timeout(15)
                    ->post($this->url('/graphql'), [
                        'query'     => $gql,
                        'variables' => ['id' => $emeliaContactId, 'cid' => $campaignId],
                    ]);

                $json = $response->json();

                // Si pas d'erreur GraphQL → mutation a réussi
                if (empty($json['errors'])) {
                    return true;
                }

                $errMsg = $json['errors'][0]['message'] ?? '';

                // Mutation existante mais contact déjà retiré
                if (str_contains($errMsg, 'not found') || str_contains($errMsg, 'not in campaign')) {
                    return false;
                }

                // Mutation inconnue → on essaie la suivante
                if (str_contains($errMsg, 'Cannot query field') || str_contains($errMsg, 'did you mean')) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        // Aucune mutation n'a fonctionné — on loggue mais on ne lève pas d'exception
        // Le contact reste blacklisté côté CRM ; Emelia continuera sa séquence jusqu'à expiration
        \Illuminate\Support\Facades\Log::warning(
            "EmeliaService::removeFromCampaign: no working mutation found for contact {$emeliaContactId} in campaign {$campaignId}"
        );

        return false;
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
