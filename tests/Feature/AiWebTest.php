<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Contact;
use App\Models\Company;
use App\Models\Pipeline;
use App\Models\User;
use App\Services\LlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\JwtService;
use Tests\TestCase;

class AiWebTest extends TestCase
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

        return User::createWithRole([
            'name'     => 'User ' . $counter,
            'email'    => 'user' . $counter . '@ai.test',
            'password' => bcrypt('password'),
            'role'     => $role,
        ]);
    }

    private function mockAi(): void
    {
        $this->mock(LlmService::class, function ($mock) {
            $mock->shouldReceive('complete')->andReturn('Résumé IA simulé.');
        });
    }

    private function aiPost(string $url): \Illuminate\Testing\TestResponse
    {
        return $this->post($url, ['_token' => 'test'], ['Accept' => 'application/json']);
    }

    public function test_deal_summarize_returns_json(): void
    {
        $this->mockAi();
        $user = $this->makeUser();
        $pipeline = Pipeline::create(['name' => 'Test', 'is_default' => true]);
        $stage    = $pipeline->stages()->create(['name' => 'Prospect', 'position' => 1, 'probability' => 10]);
        $deal     = Deal::create(['name' => 'AI Deal', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'status' => 'open', 'amount' => 1000]);

        $response = $this->withAuth($user)->aiPost('/web/ai/deal/' . $deal->id . '/summarize');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'cached', 'generated_at']);
    }

    public function test_deal_next_action_returns_json(): void
    {
        $this->mockAi();
        $user = $this->makeUser();
        $pipeline = Pipeline::create(['name' => 'Test2', 'is_default' => false]);
        $stage    = $pipeline->stages()->create(['name' => 'Prospect', 'position' => 1, 'probability' => 10]);
        $deal     = Deal::create(['name' => 'AI Deal2', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'status' => 'open', 'amount' => 500]);

        $response = $this->withAuth($user)->aiPost('/web/ai/deal/' . $deal->id . '/next-action');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'cached']);
    }

    public function test_deal_score_returns_json(): void
    {
        $this->mock(LlmService::class, function ($mock) {
            $mock->shouldReceive('complete')->andReturn(json_encode([
                'score' => 85,
                'trend' => 'warming',
                'reasons' => ['Bonne activité'],
                'green_flags' => ['Contact engagé'],
                'red_flags' => ['Pas de décideur'],
                'recommendations' => ['Trouver le décideur']
            ]));
        });

        $user = $this->makeUser();
        $pipeline = Pipeline::create(['name' => 'Test3', 'is_default' => false]);
        $stage    = $pipeline->stages()->create(['name' => 'Prospect', 'position' => 1, 'probability' => 10]);
        $deal     = Deal::create(['name' => 'AI Deal3', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'status' => 'open', 'amount' => 200]);

        $response = $this->withAuth($user)->aiPost('/web/ai/deal/' . $deal->id . '/score');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'score',
                'trend',
                'reasons',
                'green_flags',
                'red_flags',
                'recommendations',
            ],
            'cached'
        ]);
        $response->assertJsonPath('data.score', 85);
        $response->assertJsonPath('data.trend', 'warming');
    }

    public function test_contact_summarize_returns_json(): void
    {
        $this->mockAi();
        $user    = $this->makeUser();
        $contact = Contact::create(['first_name' => 'AI', 'last_name' => 'User', 'email' => 'ai@contact.test']);

        $response = $this->withAuth($user)->aiPost('/web/ai/contact/' . $contact->id . '/summarize');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'cached']);
    }

    public function test_company_summarize_returns_json(): void
    {
        $this->mockAi();
        $user    = $this->makeUser();
        $company = Company::create(['name' => 'AI Corp']);

        $response = $this->withAuth($user)->aiPost('/web/ai/company/' . $company->id . '/summarize');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'cached']);
    }

    public function test_dashboard_suggestions_returns_json(): void
    {
        $this->mockAi();
        $user = $this->makeUser();

        $response = $this->withAuth($user)->aiPost('/web/ai/dashboard/suggestions');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'cached']);
    }

    public function test_unauthenticated_returns_redirect(): void
    {
        $response = $this->withSession(['_token' => 'test'])
                         ->post('/web/ai/dashboard/suggestions', ['_token' => 'test']);
        $response->assertRedirect('/login');
    }

    public function test_deal_unknown_action_returns_422(): void
    {
        $this->mockAi();
        $user = $this->makeUser();
        $pipeline = Pipeline::create(['name' => 'Test4', 'is_default' => false]);
        $stage    = $pipeline->stages()->create(['name' => 'Prospect', 'position' => 1, 'probability' => 10]);
        $deal     = Deal::create(['name' => 'AI Deal4', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'status' => 'open', 'amount' => 0]);

        $response = $this->withAuth($user)->aiPost('/web/ai/deal/' . $deal->id . '/unknown');

        $response->assertStatus(422);
    }
}
