<?php

namespace Tests\Feature;

use App\Console\Commands\EmeliaSyncContactEvents;
use App\Models\Activity;
use App\Models\Contact;
use App\Services\EmeliaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EmeliaSyncContactEventsTest extends TestCase
{
    use RefreshDatabase;

    private function mockEmelia(array $events): void
    {
        $mock = $this->createMock(EmeliaService::class);
        $mock->method('getContactEvents')->willReturn($events);
        $this->app->instance(EmeliaService::class, $mock);
    }

    private function makeLinkedContact(array $attrs = []): Contact
    {
        return Contact::create(array_merge([
            'first_name'          => 'Bob',
            'email'               => 'bob@test.com',
            'emelia_contact_id'   => 'em_bob_001',
            'emelia_campaign_id'  => 'camp_123',
            'emelia_campaign_name' => 'test-campaign',
            'lifecycle_stage'     => 'lead',
        ], $attrs));
    }

    public function test_creates_activities_from_polling_events(): void
    {
        $contact = $this->makeLinkedContact();
        $openedAt = Carbon::parse('2026-05-10 09:00:00');

        $this->mockEmelia([
            ['type' => 'OPENED', 'date' => $openedAt],
            ['type' => 'REPLIED', 'date' => Carbon::parse('2026-05-11 15:00:00')],
        ]);

        $this->artisan('emelia:sync-contact-events --only-linked')
            ->assertExitCode(0);

        $this->assertEquals(
            Activity::TYPE_EMAIL_OPENED,
            Activity::where('subject_id', $contact->id)
                ->where('type', Activity::TYPE_EMAIL_OPENED)
                ->value('type')
        );

        $this->assertEquals(
            Activity::TYPE_EMAIL_REPLIED,
            Activity::where('subject_id', $contact->id)
                ->where('type', Activity::TYPE_EMAIL_REPLIED)
                ->value('type')
        );

        // REPLIED crée aussi une tâche
        $this->assertEquals(1, Activity::where('subject_id', $contact->id)
            ->where('type', Activity::TYPE_TASK)->count());
    }

    public function test_idempotent_on_second_run(): void
    {
        $contact = $this->makeLinkedContact();

        $this->mockEmelia([
            ['type' => 'OPENED', 'date' => Carbon::parse('2026-05-10 09:00:00')],
        ]);

        $this->artisan('emelia:sync-contact-events --only-linked')->assertExitCode(0);
        $countAfterFirst = Activity::where('subject_id', $contact->id)->count();

        $this->artisan('emelia:sync-contact-events --only-linked')->assertExitCode(0);
        $countAfterSecond = Activity::where('subject_id', $contact->id)->count();

        $this->assertEquals($countAfterFirst, $countAfterSecond, 'Le second run ne doit pas créer de doublons');
    }

    public function test_dry_run_creates_no_activities(): void
    {
        $this->makeLinkedContact();

        $this->mockEmelia([
            ['type' => 'OPENED', 'date' => Carbon::parse('2026-05-10 09:00:00')],
        ]);

        $this->artisan('emelia:sync-contact-events --only-linked --dry-run')
            ->assertExitCode(0);

        $this->assertEquals(0, Activity::count());
    }

    public function test_skips_contacts_without_emelia_id(): void
    {
        Contact::create([
            'first_name' => 'NoEmelia',
            'email'      => 'none@test.com',
        ]);

        $this->mockEmelia([['type' => 'OPENED', 'date' => now()]]);

        $this->artisan('emelia:sync-contact-events --only-linked')
            ->assertExitCode(0);

        $this->assertEquals(0, Activity::count());
    }

    public function test_targets_single_contact_with_contact_option(): void
    {
        $c1 = $this->makeLinkedContact();
        $c2 = Contact::create([
            'first_name'         => 'Carol',
            'email'              => 'carol@test.com',
            'emelia_contact_id'  => 'em_carol',
            'emelia_campaign_id' => 'camp_123',
        ]);

        $this->mockEmelia([
            ['type' => 'OPENED', 'date' => Carbon::parse('2026-05-10 09:00:00')],
        ]);

        $this->artisan("emelia:sync-contact-events --contact={$c1->id}")
            ->assertExitCode(0);

        // Seul c1 est ciblé
        $this->assertEquals(1, Activity::where('subject_id', $c1->id)->count());
        $this->assertEquals(0, Activity::where('subject_id', $c2->id)->count());
    }

    public function test_occurred_at_is_set_from_event_date(): void
    {
        $contact  = $this->makeLinkedContact();
        $eventDate = Carbon::parse('2026-04-01 12:00:00');

        $this->mockEmelia([
            ['type' => 'OPENED', 'date' => $eventDate],
        ]);

        $this->artisan('emelia:sync-contact-events --only-linked')->assertExitCode(0);

        $activity = Activity::where('subject_id', $contact->id)
            ->where('type', Activity::TYPE_EMAIL_OPENED)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals(
            $eventDate->toDateTimeString(),
            $activity->occurred_at->toDateTimeString()
        );
    }

    public function test_emelia_api_error_counts_as_error_but_does_not_crash(): void
    {
        $this->makeLinkedContact();

        $mock = $this->createMock(EmeliaService::class);
        $mock->method('getContactEvents')->willThrowException(new \RuntimeException('API timeout'));
        $this->app->instance(EmeliaService::class, $mock);

        $this->artisan('emelia:sync-contact-events --only-linked')
            ->assertExitCode(1);

        $this->assertEquals(0, Activity::count());
    }

    public function test_webhook_replied_also_stores_occurred_at(): void
    {
        $contact = Contact::create(['first_name' => 'Dave', 'email' => 'dave@test.com']);

        $payload = [
            'event'    => 'REPLIED',
            'event_id' => 'evt_wh_replied',
            'email'    => 'dave@test.com',
            'date'     => '2026-05-20T08:30:00Z',
        ];

        $body      = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'test-wh-secret');
        config(['services.emelia.webhook_secret' => 'test-wh-secret']);

        $this->call('POST', '/api/webhooks/emelia', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_EMELIA_SIGNATURE' => $signature],
            $body
        )->assertOk();

        $activity = Activity::where('external_id', 'evt_wh_replied')->first();
        $this->assertNotNull($activity);
        $this->assertEquals('2026-05-20 08:30:00', $activity->occurred_at->toDateTimeString());
    }
}
