<?php

namespace Tests\Feature;

use App\Jobs\RemoveFromEmeliaCampaign;
use App\Models\Activity;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmeliaIntentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.emelia.webhook_secret' => self::SECRET]);
        Queue::fake();
    }

    private function postIntent(array $payload, ?string $sig = null)
    {
        $body      = json_encode($payload);
        $signature = $sig ?? hash_hmac('sha256', $body, self::SECRET);

        return $this->call(
            'POST',
            '/api/webhooks/emelia-intent',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_EMELIA_SIGNATURE' => $signature],
            $body
        );
    }

    // T1 — POST valide → contact blacklisté + Activity créée + job dispatché
    public function test_stop_intent_blacklists_contact(): void
    {
        $contact = Contact::factory()->create([
            'email'             => 'lead@test.com',
            'emelia_contact_id' => 'emelia123',
            'emelia_campaign_id' => 'camp456',
        ]);

        $payload = [
            'intent'      => 'stop',
            'email'       => 'lead@test.com',
            'campaign_id' => 'camp456',
            'reply_text'  => 'STOP merci',
            'date'        => '2026-05-25T10:00:00.000Z',
            'event_id'    => 'lead@test.com_REPLIED_2026-05-25T10:00:00.000Z@stop',
        ];

        $this->postIntent($payload)
            ->assertStatus(200)
            ->assertJson(['status' => 'ok', 'action' => 'blacklisted']);

        $contact->refresh();
        $this->assertNotNull($contact->blacklisted_at);
        $this->assertSame('STOP via Emelia reply', $contact->blacklist_reason);

        $this->assertDatabaseHas('activities', [
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'type'         => Activity::TYPE_EMAIL_UNSUBSCRIBED,
            'source'       => 'emelia',
            'title'        => 'Désabonnement automatique (STOP)',
        ]);

        Queue::assertPushed(RemoveFromEmeliaCampaign::class, function ($job) use ($contact) {
            return $job->contact->id === $contact->id && $job->campaignId === 'camp456';
        });
    }

    // T2 — event_id déjà connu → duplicate, aucun side-effect
    public function test_duplicate_event_id_returns_duplicate(): void
    {
        $contact = Contact::factory()->create(['email' => 'lead@test.com']);

        Activity::create([
            'source'       => 'emelia',
            'external_id'  => 'dup-event-id@stop',
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'type'         => Activity::TYPE_EMAIL_UNSUBSCRIBED,
            'title'        => 'test',
        ]);

        $this->postIntent([
            'intent'   => 'stop',
            'email'    => 'lead@test.com',
            'event_id' => 'dup-event-id@stop',
        ])->assertStatus(200)->assertJson(['status' => 'duplicate']);

        $contact->refresh();
        $this->assertNull($contact->blacklisted_at);

        Queue::assertNotPushed(RemoveFromEmeliaCampaign::class);
    }

    // T3 — contact déjà blacklisté → duplicate
    public function test_already_blacklisted_returns_duplicate(): void
    {
        $contact = Contact::factory()->create([
            'email'            => 'already@test.com',
            'blacklisted_at'   => now()->subDay(),
            'blacklist_reason' => 'STOP via Emelia reply',
        ]);

        $this->postIntent([
            'intent'  => 'stop',
            'email'   => 'already@test.com',
            'event_id' => 'new-event-999@stop',
        ])->assertStatus(200)->assertJson(['status' => 'duplicate']);

        Queue::assertNotPushed(RemoveFromEmeliaCampaign::class);
    }

    // T4 — email inconnu → ignored
    public function test_unknown_contact_without_email_returns_ignored(): void
    {
        $this->postIntent(['intent' => 'stop'])
            ->assertStatus(200)
            ->assertJson(['status' => 'ignored', 'reason' => 'no email']);
    }

    // T4b — email fourni mais contact inexistant → ignored (pas de création de contact léger)
    public function test_unknown_contact_with_email_returns_ignored(): void
    {
        $this->postIntent(['intent' => 'stop', 'email' => 'ghost@nowhere.com'])
            ->assertStatus(200)
            ->assertJson(['status' => 'ignored', 'reason' => 'contact not found']);
    }

    // T5 — signature invalide → 401
    public function test_rejects_invalid_signature(): void
    {
        $contact = Contact::factory()->create(['email' => 'test@test.com']);

        $this->postIntent(['intent' => 'stop', 'email' => 'test@test.com'], 'badsig')
            ->assertStatus(401);
    }

    // T7 — Intent "interested" → tâche urgente + lifecycle bump lead→sql
    public function test_interested_intent_creates_urgent_task_and_bumps_lifecycle(): void
    {
        $contact = Contact::factory()->create([
            'email'           => 'hot@test.com',
            'first_name'      => 'Marie',
            'lifecycle_stage' => 'lead',
        ]);

        $this->postIntent([
            'intent'        => 'interested',
            'email'         => 'hot@test.com',
            'reply_text'    => 'Oui je suis intéressée, appelez-moi',
            'campaign_name' => 'acquisition-agence-marketing',
            'event_id'      => 'hot@test.com_REPLIED_2026-05-25@interested',
        ])->assertStatus(200)->assertJson(['status' => 'ok', 'action' => 'task_created_urgent']);

        $contact->refresh();
        $this->assertSame('sql', $contact->lifecycle_stage);

        $this->assertDatabaseHas('activities', [
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'type'         => Activity::TYPE_TASK,
            'source'       => 'emelia',
        ]);

        $task = Activity::where('subject_id', $contact->id)->where('type', Activity::TYPE_TASK)->first();
        $this->assertStringContainsString('URGENT', $task->title);
        $this->assertStringContainsString('Marie', $task->title);
        $this->assertTrue($task->due_at->gt(now()));
        $this->assertTrue($task->due_at->lt(now()->addHours(5)));
    }

    // T8 — Intent "not_interested" → note dans timeline + lifecycle mql→lead
    public function test_not_interested_intent_creates_note_and_downgrades_lifecycle(): void
    {
        $contact = Contact::factory()->create([
            'email'           => 'cold@test.com',
            'lifecycle_stage' => 'mql',
        ]);

        $this->postIntent([
            'intent'     => 'not_interested',
            'email'      => 'cold@test.com',
            'reply_text' => 'Non merci, pas pour moi',
            'event_id'   => 'cold@test.com_REPLIED_2026-05-25@not_interested',
        ])->assertStatus(200)->assertJson(['status' => 'ok', 'action' => 'note_created']);

        $contact->refresh();
        $this->assertSame('lead', $contact->lifecycle_stage);

        $this->assertDatabaseHas('activities', [
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'type'         => Activity::TYPE_NOTE,
            'source'       => 'emelia',
        ]);
    }

    // T9 — Intent "out_of_office" → tâche différée 7j
    public function test_out_of_office_intent_creates_deferred_task(): void
    {
        $contact = Contact::factory()->create(['email' => 'ooo@test.com', 'first_name' => 'Paul']);

        $this->postIntent([
            'intent'     => 'out_of_office',
            'email'      => 'ooo@test.com',
            'reply_text' => 'Je suis absent jusqu\'au 2 juin',
            'event_id'   => 'ooo@test.com_REPLIED_2026-05-25@ooo',
        ])->assertStatus(200)->assertJson(['status' => 'ok', 'action' => 'task_created_deferred']);

        $task = Activity::where('subject_id', $contact->id)->where('type', Activity::TYPE_TASK)->first();
        $this->assertNotNull($task);
        $this->assertStringContainsString('Paul', $task->title);
        $this->assertStringContainsString('absent', $task->title);
        // Due dans ~7 jours
        $this->assertTrue($task->due_at->gt(now()->addDays(6)));
    }

    // T10 — Intent inconnu → ignored
    public function test_unknown_intent_returns_ignored(): void
    {
        $contact = Contact::factory()->create(['email' => 'test@test.com']);

        $this->postIntent(['intent' => 'foo_unknown', 'email' => 'test@test.com'])
            ->assertStatus(200)
            ->assertJson(['status' => 'ignored', 'reason' => 'unknown intent']);
    }

    // T6 — scopeContactable exclut les blacklistés
    public function test_scope_contactable_excludes_blacklisted(): void
    {
        Contact::factory()->create(['email' => 'active@test.com', 'blacklisted_at' => null]);
        Contact::factory()->create(['email' => 'blacklisted@test.com', 'blacklisted_at' => now()]);

        $contactable = Contact::contactable()->get();
        $this->assertCount(1, $contactable);
        $this->assertSame('active@test.com', $contactable->first()->email);

        $blacklisted = Contact::blacklisted()->get();
        $this->assertCount(1, $blacklisted);
        $this->assertSame('blacklisted@test.com', $blacklisted->first()->email);
    }
}
