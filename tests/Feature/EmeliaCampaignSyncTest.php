<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\EmeliaCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmeliaCampaignSyncTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        // Le webhook exige désormais une signature HMAC (durcissement sécurité).
        // On fixe un secret de test connu pour pouvoir signer les payloads, sans
        // dépendre d'un éventuel EMELIA_WEBHOOK_SECRET injecté par l'environnement.
        config(['services.emelia.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    private function postWebhook(array $payload)
    {
        // Signe le corps exactement comme le contrôleur le recalcule
        // (hash_hmac sha256 sur le JSON brut transmis).
        $body      = json_encode($payload);
        $signature = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        return $this->call(
            'POST',
            '/api/webhooks/emelia',
            [],
            [],
            [],
            [
                'CONTENT_TYPE'           => 'application/json',
                'HTTP_ACCEPT'            => 'application/json',
                'HTTP_X_EMELIA_SIGNATURE' => $signature,
            ],
            $body
        );
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'event'         => 'OPENED',
            'email'         => 'test@example.com',
            'event_id'      => 'test@example.com_OPENED_2026-05-24T10:00:00.000Z',
            'date'          => '2026-05-24T10:00:00.000Z',
            'campaign_id'   => 'emeliaid_campaign_abc',
            'campaign_name' => 'campagne-test',
            'first_name'    => 'Test',
            'last_name'     => 'User',
        ], $overrides);
    }

    public function test_webhook_cree_emelia_campaign_et_pivot_si_inconnus(): void
    {
        $this->assertDatabaseCount('emelia_campaigns', 0);
        $this->assertDatabaseCount('contact_emelia_campaign', 0);

        $response = $this->postWebhook($this->basePayload());

        $response->assertOk()->assertJson(['status' => 'ok']);

        // La campagne a été créée
        $this->assertDatabaseHas('emelia_campaigns', [
            'emelia_id' => 'emeliaid_campaign_abc',
            'name'      => 'campagne-test',
        ]);

        // Le contact a été créé et lié à la campagne
        $contact = Contact::where('email', 'test@example.com')->firstOrFail();
        $this->assertEquals(1, $contact->emeliaCampaigns()->count());
        $this->assertEquals('emeliaid_campaign_abc', $contact->emeliaCampaigns()->first()->emelia_id);

        // Les colonnes legacy sont aussi mises à jour
        $this->assertEquals('emeliaid_campaign_abc', $contact->emelia_campaign_id);
        $this->assertEquals('campagne-test', $contact->emelia_campaign_name);
    }

    public function test_activity_porte_emelia_campaign_id(): void
    {
        $this->postWebhook($this->basePayload());

        $campaign = EmeliaCampaign::where('emelia_id', 'emeliaid_campaign_abc')->firstOrFail();
        $activity = Activity::where('source', 'emelia')->firstOrFail();

        $this->assertEquals($campaign->id, $activity->emelia_campaign_id);
    }

    public function test_webhook_lie_contact_a_plusieurs_campagnes(): void
    {
        // Premier event — campagne A
        $this->postWebhook($this->basePayload([
            'event_id'      => 'test@example.com_OPENED_2026-05-24T10:00:00.000Z',
            'campaign_id'   => 'emeliaid_campaign_abc',
            'campaign_name' => 'campagne-a',
        ]));

        // Deuxième event — campagne B (même contact)
        $this->postWebhook($this->basePayload([
            'event_id'      => 'test@example.com_SENT_2026-05-24T11:00:00.000Z',
            'event'         => 'SENT',
            'date'          => '2026-05-24T11:00:00.000Z',
            'campaign_id'   => 'emeliaid_campaign_xyz',
            'campaign_name' => 'campagne-b',
        ]));

        $contact = Contact::where('email', 'test@example.com')->firstOrFail();

        // Le contact est lié à 2 campagnes
        $this->assertEquals(2, $contact->emeliaCampaigns()->count());
        $this->assertDatabaseCount('emelia_campaigns', 2);

        // 2 activities, chacune avec sa campaign
        $this->assertEquals(2, Activity::where('source', 'emelia')->where('subject_id', $contact->id)->count());
    }

    public function test_idempotence_webhook_double_envoi(): void
    {
        $payload = $this->basePayload();

        $this->postWebhook($payload)->assertJson(['status' => 'ok']);
        $this->postWebhook($payload)->assertJson(['status' => 'duplicate']);

        // Une seule Activity créée
        $this->assertEquals(1, Activity::where('source', 'emelia')->count());

        // Une seule ligne dans le pivot
        $this->assertDatabaseCount('contact_emelia_campaign', 1);
    }

    public function test_pivot_ne_duplique_pas_sur_event_different_meme_campagne(): void
    {
        // Event 1 — OPENED
        $this->postWebhook($this->basePayload([
            'event_id' => 'test@example.com_OPENED_2026-05-24T10:00:00.000Z',
            'event'    => 'OPENED',
            'date'     => '2026-05-24T10:00:00.000Z',
        ]));

        // Event 2 — REPLIED, même campagne
        $this->postWebhook($this->basePayload([
            'event_id' => 'test@example.com_REPLIED_2026-05-24T12:00:00.000Z',
            'event'    => 'REPLIED',
            'date'     => '2026-05-24T12:00:00.000Z',
        ]));

        // Toujours 1 seul pivot (même campagne) ; 2 activités email + 1 tâche auto-créée par handleReply
        $this->assertDatabaseCount('contact_emelia_campaign', 1);
        $this->assertEquals(2, Activity::where('source', 'emelia')->whereIn('type', ['email_opened', 'email_replied', 'email_sent', 'email_clicked', 'email_bounced', 'email_unsubscribed'])->count());

        // Le pivot a bien son last_event_at mis à jour (REPLIED = le plus récent)
        $contact  = Contact::where('email', 'test@example.com')->firstOrFail();
        $campaign = EmeliaCampaign::where('emelia_id', 'emeliaid_campaign_abc')->firstOrFail();
        $pivot    = $contact->emeliaCampaigns()->where('emelia_campaign_id', $campaign->id)->first();

        $this->assertEquals('REPLIED', $pivot->pivot->status);
    }

    public function test_webhook_sans_campaign_id_ne_cree_pas_de_pivot(): void
    {
        $payload = $this->basePayload();
        unset($payload['campaign_id'], $payload['campaign_name']);

        $this->postWebhook($payload)->assertJson(['status' => 'ok']);

        $this->assertDatabaseCount('emelia_campaigns', 0);
        $this->assertDatabaseCount('contact_emelia_campaign', 0);

        // L'Activity est quand même créée (sans campagne)
        $this->assertEquals(1, Activity::where('source', 'emelia')->count());
        $this->assertNull(Activity::where('source', 'emelia')->first()->emelia_campaign_id);
    }
}
