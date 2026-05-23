<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\User;
use App\Services\LlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\JwtService;
use Tests\TestCase;

class AiInsightEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    private function withAuth(User $user): static
    {
        $jwt = app(JwtService::class)->encode([
            'sub' => $user->id,
            'exp' => time() + 3600,
        ]);

        return $this->withCookies(['crm_jwt' => $jwt])
                    ->withSession(['_token' => 'test']);
    }

    private function makeUser(string $role = User::ROLE_ADMIN): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'name'     => 'User ' . $counter,
            'email'    => 'user' . $counter . '@enrichment.test',
            'password' => bcrypt('password'),
            'role'     => $role,
        ]);
    }

    private function aiPost(string $url): \Illuminate\Testing\TestResponse
    {
        return $this->post($url, ['_token' => 'test'], ['Accept' => 'application/json']);
    }

    // ─── A1 : contactContext() enrichi avec données Emelia ───────────────────

    public function test_contact_summarize_includes_emelia_engagement_stats(): void
    {
        $capturedPrompt = null;
        $this->mock(LlmService::class, function ($mock) use (&$capturedPrompt) {
            $mock->shouldReceive('complete')
                 ->once()
                 ->withArgs(function ($system, $prompt) use (&$capturedPrompt) {
                     $capturedPrompt = $prompt;
                     return true;
                 })
                 ->andReturn('Résumé IA simulé.');
        });

        $user    = $this->makeUser();
        $contact = Contact::create([
            'first_name'           => 'Test',
            'last_name'            => 'Emelia',
            'email'                => 'test@emelia.test',
            'lifecycle_stage'      => 'mql',
            'emelia_campaign_id'   => 'camp-abc123',
            'emelia_campaign_name' => 'Acquisition Agence',
        ]);

        Activity::create([
            'type'         => Activity::TYPE_EMAIL_OPENED,
            'source'       => 'emelia',
            'title'        => 'Email ouvert',
            'status'       => 'done',
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'occurred_at'  => now()->subDays(3),
        ]);
        Activity::create([
            'type'         => Activity::TYPE_EMAIL_REPLIED,
            'source'       => 'emelia',
            'title'        => 'Réponse reçue',
            'status'       => 'done',
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'occurred_at'  => now()->subDay(),
        ]);

        $response = $this->withAuth($user)->aiPost('/web/ai/contact/' . $contact->id . '/summarize');

        $response->assertStatus(200);
        $this->assertNotNull($capturedPrompt, 'Le LLM devrait avoir été appelé.');
        $this->assertStringContainsString('Engagement email', $capturedPrompt);
        $this->assertStringContainsString('email_replied', $capturedPrompt);
        $this->assertStringContainsString('Acquisition Agence', $capturedPrompt);
    }

    public function test_contact_summarize_includes_lifecycle_stage(): void
    {
        $capturedPrompt = null;
        $this->mock(LlmService::class, function ($mock) use (&$capturedPrompt) {
            $mock->shouldReceive('complete')
                 ->once()
                 ->withArgs(function ($system, $prompt) use (&$capturedPrompt) {
                     $capturedPrompt = $prompt;
                     return true;
                 })
                 ->andReturn('Résumé IA simulé.');
        });

        $user    = $this->makeUser();
        $contact = Contact::create([
            'first_name'      => 'Lifecycle',
            'last_name'       => 'User',
            'email'           => 'lifecycle@test.test',
            'lifecycle_stage' => 'sql',
        ]);

        $response = $this->withAuth($user)->aiPost('/web/ai/contact/' . $contact->id . '/summarize');

        $response->assertStatus(200);
        $this->assertStringContainsString('sql', $capturedPrompt);
    }

    public function test_contact_without_emelia_does_not_include_emelia_section(): void
    {
        $capturedPrompt = null;
        $this->mock(LlmService::class, function ($mock) use (&$capturedPrompt) {
            $mock->shouldReceive('complete')
                 ->once()
                 ->withArgs(function ($system, $prompt) use (&$capturedPrompt) {
                     $capturedPrompt = $prompt;
                     return true;
                 })
                 ->andReturn('Résumé IA simulé.');
        });

        $user    = $this->makeUser();
        $contact = Contact::create([
            'first_name' => 'No',
            'last_name'  => 'Emelia',
            'email'      => 'noemelia@test.test',
        ]);

        $response = $this->withAuth($user)->aiPost('/web/ai/contact/' . $contact->id . '/summarize');

        $response->assertStatus(200);
        $this->assertStringNotContainsString('Engagement email', $capturedPrompt);
        $this->assertStringNotContainsString('Campagne Emelia', $capturedPrompt);
    }

    // ─── A2 : dailySuggestions() enrichi ─────────────────────────────────────

    public function test_daily_suggestions_returns_alerts_priorities_suggestions_keys(): void
    {
        $this->mock(LlmService::class, function ($mock) {
            $mock->shouldReceive('complete')->andReturn(json_encode([
                'suggestions' => ['Contacter le prospect stagnant'],
                'alerts'      => ['Deal à clôturer demain'],
                'priorities'  => ['Rappeler Jean Dupont'],
            ]));
        });

        $user = $this->makeUser();

        $response = $this->withAuth($user)->aiPost('/web/ai/dashboard/suggestions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['suggestions', 'alerts', 'priorities'],
            'cached',
        ]);
    }

    public function test_daily_suggestions_includes_closing_soon_deals_in_context(): void
    {
        $capturedPrompt = null;
        $this->mock(LlmService::class, function ($mock) use (&$capturedPrompt) {
            $mock->shouldReceive('complete')
                 ->once()
                 ->withArgs(function ($system, $prompt) use (&$capturedPrompt) {
                     $capturedPrompt = $prompt;
                     return true;
                 })
                 ->andReturn(json_encode(['suggestions' => [], 'alerts' => [], 'priorities' => []]));
        });

        $user     = $this->makeUser();
        $pipeline = Pipeline::create(['name' => 'Enrich', 'is_default' => true]);
        $stage    = $pipeline->stages()->create(['name' => 'Closing', 'position' => 1, 'probability' => 90]);

        Deal::create([
            'name'              => 'Deal Urgent',
            'pipeline_id'       => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'status'            => 'open',
            'amount'            => 5000,
            'close_date'        => now()->addDays(3),
        ]);

        $response = $this->withAuth($user)->aiPost('/web/ai/dashboard/suggestions');

        $response->assertStatus(200);
        $this->assertStringContainsString('closent dans les 7 prochains jours', $capturedPrompt);
        $this->assertStringContainsString('Deal Urgent', $capturedPrompt);
    }

    public function test_daily_suggestions_includes_overdue_tasks_in_context(): void
    {
        $capturedPrompt = null;
        $this->mock(LlmService::class, function ($mock) use (&$capturedPrompt) {
            $mock->shouldReceive('complete')
                 ->once()
                 ->withArgs(function ($system, $prompt) use (&$capturedPrompt) {
                     $capturedPrompt = $prompt;
                     return true;
                 })
                 ->andReturn(json_encode(['suggestions' => [], 'alerts' => [], 'priorities' => []]));
        });

        $user = $this->makeUser();

        Activity::create([
            'type'     => Activity::TYPE_TASK,
            'title'    => 'Rappel urgent overdue',
            'status'   => 'open',
            'owner_id' => $user->id,
            'due_at'   => now()->subDays(2),
            'subject_type' => Contact::class,
            'subject_id'   => 9999,
        ]);

        $response = $this->withAuth($user)->aiPost('/web/ai/dashboard/suggestions');

        $response->assertStatus(200);
        $this->assertStringContainsString('Tâches en retard', $capturedPrompt);
        $this->assertStringContainsString('Rappel urgent overdue', $capturedPrompt);
    }

    public function test_daily_suggestions_includes_recent_emelia_replies_in_context(): void
    {
        $capturedPrompt = null;
        $this->mock(LlmService::class, function ($mock) use (&$capturedPrompt) {
            $mock->shouldReceive('complete')
                 ->once()
                 ->withArgs(function ($system, $prompt) use (&$capturedPrompt) {
                     $capturedPrompt = $prompt;
                     return true;
                 })
                 ->andReturn(json_encode(['suggestions' => [], 'alerts' => [], 'priorities' => []]));
        });

        $user    = $this->makeUser();
        $contact = Contact::create([
            'first_name' => 'Marie',
            'last_name'  => 'Curie',
            'email'      => 'marie@curie.test',
        ]);

        Activity::create([
            'type'         => Activity::TYPE_EMAIL_REPLIED,
            'source'       => 'emelia',
            'title'        => 'Réponse Emelia',
            'status'       => 'done',
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
        ]);

        $response = $this->withAuth($user)->aiPost('/web/ai/dashboard/suggestions');

        $response->assertStatus(200);
        $this->assertStringContainsString('répondu à une campagne Emelia', $capturedPrompt);
        $this->assertStringContainsString('Marie Curie', $capturedPrompt);
    }

    public function test_daily_suggestions_empty_pipeline_returns_no_action_message(): void
    {
        $this->mock(LlmService::class, function ($mock) {
            $mock->shouldReceive('complete')->never();
        });

        $user = $this->makeUser();

        $response = $this->withAuth($user)->aiPost('/web/ai/dashboard/suggestions');

        $response->assertStatus(200);
        $response->assertJsonPath('data.suggestions.0', 'Aucune action urgente détectée. Bonne journée !');
        $response->assertJsonPath('data.alerts', []);
        $response->assertJsonPath('data.priorities', []);
    }
}
