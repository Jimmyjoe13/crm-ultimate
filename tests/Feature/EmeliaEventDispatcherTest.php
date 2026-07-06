<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\User;
use App\Support\EmeliaEventDispatcher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmeliaEventDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private function makeContact(array $attrs = []): Contact
    {
        return Contact::create(array_merge([
            'first_name' => 'Alice',
            'last_name' => 'Test',
            'email' => 'alice@test.com',
            'lifecycle_stage' => 'lead',
        ], $attrs));
    }

    public function test_dispatch_creates_activity_with_occurred_at(): void
    {
        $contact = $this->makeContact();
        $occurredAt = Carbon::parse('2026-05-10 14:30:00');

        $activity = EmeliaEventDispatcher::dispatch(
            contact: $contact,
            type: Activity::TYPE_EMAIL_OPENED,
            payload: ['campaign' => 'test'],
            occurredAt: $occurredAt,
            externalId: 'evt_open_001',
        );

        $this->assertNotNull($activity);
        $this->assertEquals(Activity::TYPE_EMAIL_OPENED, $activity->type);
        $this->assertEquals('emelia', $activity->source);
        $this->assertEquals('evt_open_001', $activity->external_id);
        $this->assertEquals($occurredAt->toDateTimeString(), $activity->occurred_at->toDateTimeString());
        $this->assertEquals(Contact::class, $activity->subject_type);
        $this->assertEquals($contact->id, $activity->subject_id);
    }

    public function test_dispatch_idempotent_on_same_external_id(): void
    {
        $contact = $this->makeContact();

        $first = EmeliaEventDispatcher::dispatch($contact, Activity::TYPE_EMAIL_OPENED, [], null, 'evt_dup');
        $second = EmeliaEventDispatcher::dispatch($contact, Activity::TYPE_EMAIL_OPENED, [], null, 'evt_dup');

        $this->assertNotNull($first);
        $this->assertNull($second, 'Le second dispatch avec le même external_id doit retourner null');
        $this->assertEquals(1, Activity::where('external_id', 'evt_dup')->count());
    }

    public function test_replied_creates_followup_task(): void
    {
        $owner = User::createWithRole(['name' => 'Bob', 'email' => 'bob@crm.com', 'password' => 'x', 'role' => 'commercial']);
        $contact = $this->makeContact(['owner_id' => $owner->id, 'emelia_campaign_name' => 'ma-campagne']);

        EmeliaEventDispatcher::dispatch(
            contact: $contact,
            type: Activity::TYPE_EMAIL_REPLIED,
            payload: [],
            occurredAt: now(),
            externalId: 'evt_reply_001',
        );

        // 1 activité reply + 1 tâche follow-up
        $this->assertEquals(2, Activity::where('subject_id', $contact->id)->count());

        $task = Activity::where('type', Activity::TYPE_TASK)
            ->where('subject_id', $contact->id)
            ->first();

        $this->assertNotNull($task);
        $this->assertStringContainsString('Alice', $task->title);
        $this->assertStringContainsString('ma-campagne', $task->title);
        $this->assertEquals($owner->id, $task->owner_id);
        $this->assertNotNull($task->due_at);
        $this->assertEquals('open', $task->status);
    }

    public function test_replied_bumps_lifecycle_lead_to_mql(): void
    {
        $contact = $this->makeContact(['lifecycle_stage' => 'lead']);

        EmeliaEventDispatcher::dispatch(
            contact: $contact,
            type: Activity::TYPE_EMAIL_REPLIED,
            payload: [],
            occurredAt: now(),
            externalId: 'evt_reply_bump',
        );

        $this->assertEquals('mql', $contact->fresh()->lifecycle_stage);
    }

    public function test_replied_does_not_bump_if_already_mql(): void
    {
        $contact = $this->makeContact(['lifecycle_stage' => 'mql']);

        EmeliaEventDispatcher::dispatch(
            contact: $contact,
            type: Activity::TYPE_EMAIL_REPLIED,
            payload: [],
            occurredAt: now(),
            externalId: 'evt_reply_nomql',
        );

        $this->assertEquals('mql', $contact->fresh()->lifecycle_stage);
    }

    public function test_opened_does_not_create_task(): void
    {
        $contact = $this->makeContact();

        EmeliaEventDispatcher::dispatch(
            contact: $contact,
            type: Activity::TYPE_EMAIL_OPENED,
            payload: [],
            occurredAt: now(),
            externalId: 'evt_open_notask',
        );

        $tasks = Activity::where('type', Activity::TYPE_TASK)
            ->where('subject_id', $contact->id)
            ->count();

        $this->assertEquals(0, $tasks);
    }

    public function test_replied_idempotent_does_not_create_duplicate_task(): void
    {
        $contact = $this->makeContact();

        EmeliaEventDispatcher::dispatch($contact, Activity::TYPE_EMAIL_REPLIED, [], null, 'evt_reply_idem');
        // Rejouer avec un external_id différent (cas polling qui resoumettrait le même event)
        EmeliaEventDispatcher::dispatch($contact, Activity::TYPE_EMAIL_REPLIED, [], null, 'evt_reply_idem');

        // La 2ème passe est un duplicate → 0 nouvelle tâche créée
        $taskCount = Activity::where('type', Activity::TYPE_TASK)
            ->where('subject_id', $contact->id)
            ->count();
        $this->assertEquals(1, $taskCount);
    }

    public function test_type_from_emelia_event_maps_all_six(): void
    {
        $cases = [
            'SENT' => Activity::TYPE_EMAIL_SENT,
            'OPENED' => Activity::TYPE_EMAIL_OPENED,
            'FIRST_OPEN' => Activity::TYPE_EMAIL_OPENED,
            'CLICKED' => Activity::TYPE_EMAIL_CLICKED,
            'REPLIED' => Activity::TYPE_EMAIL_REPLIED,
            'BOUNCED' => Activity::TYPE_EMAIL_BOUNCED,
            'UNSUBSCRIBED' => Activity::TYPE_EMAIL_UNSUBSCRIBED,
            'CONTACT_UNSUBSCRIBED' => Activity::TYPE_EMAIL_UNSUBSCRIBED,
            'unknown_event' => null,
        ];

        foreach ($cases as $input => $expected) {
            $this->assertEquals($expected, EmeliaEventDispatcher::typeFromEmeliaEvent($input), "Failed for $input");
        }
    }
}
