<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmeliaWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.emelia.webhook_secret' => self::SECRET]);
    }

    private function sign(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), self::SECRET);
    }

    private function postWebhook(array $payload, ?string $sig = null)
    {
        $body = json_encode($payload);
        $signature = $sig ?? hash_hmac('sha256', $body, self::SECRET);

        return $this->call(
            'POST',
            '/api/webhooks/emelia',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_EMELIA_SIGNATURE' => $signature],
            $body
        );
    }

    public function test_rejects_invalid_signature(): void
    {
        $this->postWebhook(['event' => 'email_opened', 'event_id' => 'evt_1', 'email' => 'a@b.com'], 'badsig')
            ->assertStatus(401);
    }

    public function test_creates_activity_for_existing_contact(): void
    {
        $contact = Contact::create(['email' => 'alice@test.com', 'first_name' => 'Alice', 'last_name' => 'Dupont']);

        $payload = [
            'event'      => 'email_opened',
            'event_id'   => 'evt_open_1',
            'email'      => 'alice@test.com',
            'contact_id' => 'em_42',
            'subject'    => 'Hello Alice',
        ];

        $this->postWebhook($payload)->assertOk()->assertJson(['status' => 'ok']);

        $activity = Activity::where('external_id', 'evt_open_1')->first();
        $this->assertNotNull($activity);
        $this->assertEquals(Activity::TYPE_EMAIL_OPENED, $activity->type);
        $this->assertEquals('emelia', $activity->source);
        $this->assertEquals('contact', $activity->subject_type);
        $this->assertEquals($contact->id, $activity->subject_id);
        $this->assertEquals('Hello Alice', $activity->title);
    }

    public function test_creates_light_contact_for_orphan_email(): void
    {
        $payload = [
            'event'      => 'email_sent',
            'event_id'   => 'evt_sent_1',
            'email'      => 'orphan@new.com',
            'contact_id' => 'em_99',
        ];

        $this->postWebhook($payload)->assertOk()->assertJson(['status' => 'ok']);

        $contact = Contact::where('email', 'orphan@new.com')->first();
        $this->assertNotNull($contact);
        $this->assertEquals('lead', $contact->lifecycle_stage);
        $this->assertEquals('em_99', $contact->emelia_contact_id);

        $this->assertEquals(1, Activity::where('external_id', 'evt_sent_1')->count());
    }

    public function test_idempotent_on_duplicate_event_id(): void
    {
        Contact::create(['first_name' => 'Bob', 'email' => 'bob@test.com']);

        $payload = [
            'event'    => 'email_clicked',
            'event_id' => 'evt_click_dup',
            'email'    => 'bob@test.com',
        ];

        $this->postWebhook($payload)->assertOk()->assertJson(['status' => 'ok']);
        $this->postWebhook($payload)->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertEquals(1, Activity::where('external_id', 'evt_click_dup')->count());
    }

    #[DataProvider('eventProvider')]
    public function test_handles_all_six_event_types(string $event, string $expectedType): void
    {
        Contact::create(['first_name' => 'Eve', 'email' => 'eve@test.com']);

        $payload = [
            'event'    => $event,
            'event_id' => 'evt_'.$event,
            'email'    => 'eve@test.com',
        ];

        $this->postWebhook($payload)->assertOk()->assertJson(['status' => 'ok']);

        $this->assertEquals(
            $expectedType,
            Activity::where('external_id', 'evt_'.$event)->value('type')
        );
    }

    public static function eventProvider(): array
    {
        return [
            'sent'          => ['email_sent',           Activity::TYPE_EMAIL_SENT],
            'opened'        => ['email_opened',          Activity::TYPE_EMAIL_OPENED],
            'clicked'       => ['email_clicked',         Activity::TYPE_EMAIL_CLICKED],
            'replied'       => ['email_replied',         Activity::TYPE_EMAIL_REPLIED],
            'bounced'       => ['email_bounced',         Activity::TYPE_EMAIL_BOUNCED],
            'unsubscribed'  => ['contact_unsubscribed',  Activity::TYPE_EMAIL_UNSUBSCRIBED],
        ];
    }

    public function test_ignores_missing_email_for_orphan(): void
    {
        $payload = [
            'event'    => 'email_opened',
            'event_id' => 'evt_noemail',
            'email'    => '',
        ];

        $this->postWebhook($payload)->assertOk()->assertJson(['status' => 'ignored']);
        $this->assertEquals(0, Activity::where('external_id', 'evt_noemail')->count());
    }

    public function test_ignores_unknown_event_type(): void
    {
        Contact::create(['first_name' => 'Unknown', 'email' => 'unknown@test.com']);

        $payload = [
            'event'    => 'email_spammed',
            'event_id' => 'evt_unknown',
            'email'    => 'unknown@test.com',
        ];

        $this->postWebhook($payload)->assertOk()->assertJson(['status' => 'ignored']);
        $this->assertEquals(0, Activity::where('external_id', 'evt_unknown')->count());
    }
}
