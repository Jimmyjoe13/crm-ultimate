<?php

namespace Tests\Feature;

use App\Models\Pipeline;
use App\Models\User;
use App\Services\LlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AiTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_deal_returns_ai_brief(): void
    {
        $this->mock(LlmService::class, function ($mock) {
            $mock->shouldReceive('complete')
                ->once()
                ->andReturn('Brief IA : ce deal est en bonne progression. Relance recommandée cette semaine.');
        });

        $token = $this->adminToken();
        $pipeline = Pipeline::query()->create(['name' => 'Sales', 'is_default' => true]);
        $stage = $pipeline->stages()->create(['name' => 'Qualified', 'position' => 10, 'probability' => 30]);

        $deal = $this->withToken($token)->postJson('/api/v1/deals', [
            'name' => 'Test AI Deal',
            'amount' => 10000,
            'currency' => 'EUR',
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ])->assertCreated()->json('data');

        $this->withToken($token)
            ->postJson('/api/v1/ai/summarize/deal/'.$deal['id'])
            ->assertOk()
            ->assertJsonStructure(['data', 'cached', 'generated_at'])
            ->assertJsonPath('cached', false);
    }

    public function test_summarize_deal_returns_503_when_llm_not_configured(): void
    {
        $this->mock(LlmService::class, function ($mock) {
            $mock->shouldReceive('complete')
                ->once()
                ->andThrow(new \RuntimeException('LLM provider not configured.'));
        });

        $token = $this->adminToken();
        $pipeline = Pipeline::query()->create(['name' => 'Sales', 'is_default' => true]);
        $stage = $pipeline->stages()->create(['name' => 'Qualified', 'position' => 10, 'probability' => 30]);

        $deal = $this->withToken($token)->postJson('/api/v1/deals', [
            'name' => 'Test Deal 503',
            'amount' => 5000,
            'currency' => 'EUR',
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ])->assertCreated()->json('data');

        $this->withToken($token)
            ->postJson('/api/v1/ai/summarize/deal/'.$deal['id'])
            ->assertStatus(503);
    }

    public function test_dashboard_returns_real_kpis(): void
    {
        $token = $this->adminToken();

        $this->withToken($token)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'open_deals_count',
                'open_deals_value',
                'won_this_month',
                'lost_this_month',
                'conversion_rate_30d',
                'activities_due_count',
                'activities_overdue_count',
                'deals_by_stage',
            ]);
    }

    public function test_global_search_returns_grouped_results(): void
    {
        $token = $this->adminToken();

        $this->withToken($token)->postJson('/api/v1/companies', [
            'name' => 'Acme Searchable Corp',
            'domain' => 'acme-search.test',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/search?q=Acme')
            ->assertOk()
            ->assertJsonStructure(['companies', 'contacts', 'deals'])
            ->assertJsonCount(1, 'companies');
    }

    private function adminToken(): string
    {
        User::query()->create([
            'name' => 'CRM Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        return $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ])->json('access_token');
    }
}
