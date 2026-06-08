<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Services\AiInsightService;
use App\Services\LlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPrecomputeTest extends TestCase
{
    use RefreshDatabase;

    private function makeDeal(string $status = 'open', int $amount = 1000): Deal
    {
        static $n = 0; $n++;
        $pipeline = Pipeline::firstOrCreate(['name' => 'Default']);
        $stageName = match($status) {
            'won'   => 'Won',
            'lost'  => 'Lost',
            default => 'New',
        };
        $stage = PipelineStage::firstOrCreate(
            ['pipeline_id' => $pipeline->id, 'name' => $stageName],
            ['position' => match($status) { 'won' => 90, 'lost' => 91, default => 1 },
             'is_won'   => $status === 'won',
             'is_lost'  => $status === 'lost',
            ]
        );
        return Deal::create([
            'name'              => "Deal {$n}",
            'amount'            => $amount,
            'pipeline_id'       => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);
    }

    private function mockLlm(string $response = '{"summary":"ok"}', int $times = 0): void
    {
        $mock = $this->mock(LlmService::class);
        if ($times > 0) {
            $mock->shouldReceive('complete')->times($times)->andReturn($response);
        } else {
            $mock->shouldReceive('complete')->andReturn($response);
        }
    }

    // ─── Commande listée ──────────────────────────────────────────────────────

    public function test_command_is_registered(): void
    {
        $this->artisan('ai:precompute --help')->assertSuccessful();
    }

    // ─── dry-run ──────────────────────────────────────────────────────────────

    public function test_dry_run_exits_successfully_without_calling_llm(): void
    {
        $this->mock(LlmService::class, fn($m) => $m->shouldReceive('complete')->never());

        $this->makeDeal('open', 5000);

        $this->artisan('ai:precompute --dry-run')->assertSuccessful();
    }

    // ─── Fonctionnement nominal ───────────────────────────────────────────────

    public function test_command_calls_three_ai_methods_per_open_deal(): void
    {
        // 3 calls per deal: summarizeDeal, scoreDeal, nextActionDeal
        $this->mockLlm('{"summary":"ok"}');

        $service = $this->mock(AiInsightService::class);
        $service->shouldReceive('summarizeDeal')->once()->andReturn(['data' => [], 'cached' => false]);
        $service->shouldReceive('scoreDeal')->once()->andReturn(['data' => [], 'cached' => false]);
        $service->shouldReceive('nextActionDeal')->once()->andReturn(['data' => [], 'cached' => false]);

        $this->makeDeal('open');

        $this->artisan('ai:precompute --limit=10')->assertSuccessful();
    }

    public function test_command_ignores_closed_deals(): void
    {
        $service = $this->mock(AiInsightService::class);
        $service->shouldReceive('summarizeDeal')->never();
        $service->shouldReceive('scoreDeal')->never();
        $service->shouldReceive('nextActionDeal')->never();

        $this->makeDeal('won');
        $this->makeDeal('lost');

        $this->artisan('ai:precompute --limit=10')->assertSuccessful();
    }

    public function test_limit_option_caps_number_of_deals(): void
    {
        $service = $this->mock(AiInsightService::class);
        $service->shouldReceive('summarizeDeal')->times(2)->andReturn(['data' => [], 'cached' => false]);
        $service->shouldReceive('scoreDeal')->times(2)->andReturn(['data' => [], 'cached' => false]);
        $service->shouldReceive('nextActionDeal')->times(2)->andReturn(['data' => [], 'cached' => false]);

        foreach (range(1, 5) as $i) {
            $this->makeDeal('open', $i * 1000);
        }

        $this->artisan('ai:precompute --limit=2')->assertSuccessful();
    }

    // ─── Option --contacts ────────────────────────────────────────────────────

    public function test_contacts_option_calls_summarize_contact_for_emelia_contacts(): void
    {
        $service = $this->mock(AiInsightService::class);
        $service->shouldReceive('summarizeDeal')->andReturn(['data' => [], 'cached' => false]);
        $service->shouldReceive('scoreDeal')->andReturn(['data' => [], 'cached' => false]);
        $service->shouldReceive('nextActionDeal')->andReturn(['data' => [], 'cached' => false]);
        $service->shouldReceive('summarizeContact')->once()->andReturn(['data' => [], 'cached' => false]);

        Contact::create([
            'first_name' => 'Emelia',
            'last_name'  => 'Contact',
            'email'      => 'emelia@precompute.test',
            'emelia_campaign_id' => 'camp-precompute',
        ]);

        $this->artisan('ai:precompute --contacts --limit=10')->assertSuccessful();
    }

    public function test_without_contacts_option_summarize_contact_not_called(): void
    {
        $service = $this->mock(AiInsightService::class);
        $service->shouldReceive('summarizeDeal')->andReturn(['data' => [], 'cached' => false]);
        $service->shouldReceive('scoreDeal')->andReturn(['data' => [], 'cached' => false]);
        $service->shouldReceive('nextActionDeal')->andReturn(['data' => [], 'cached' => false]);
        $service->shouldReceive('summarizeContact')->never();

        Contact::create([
            'first_name' => 'Emelia',
            'last_name'  => 'Ignored',
            'email'      => 'ignored@precompute.test',
            'emelia_campaign_id' => 'camp-x',
        ]);

        $this->artisan('ai:precompute --limit=10')->assertSuccessful();
    }

    // ─── Résistance aux erreurs ───────────────────────────────────────────────

    public function test_command_continues_after_individual_deal_error(): void
    {
        $service = $this->mock(AiInsightService::class);
        $service->shouldReceive('summarizeDeal')
            ->twice()
            ->andThrow(new \Exception('LLM error'))
            ->andReturn(['data' => [], 'cached' => false]);
        $service->shouldReceive('scoreDeal')->andReturn(['data' => [], 'cached' => false]);
        $service->shouldReceive('nextActionDeal')->andReturn(['data' => [], 'cached' => false]);

        $this->makeDeal('open', 2000);
        $this->makeDeal('open', 1000);

        $this->artisan('ai:precompute --limit=10')->assertSuccessful();
    }

    // ─── Scheduler ────────────────────────────────────────────────────────────

    public function test_schedule_includes_precompute_at_3am(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events   = $schedule->events();

        $found = collect($events)->first(
            fn($e) => str_contains($e->command ?? '', 'ai:precompute')
        );

        $this->assertNotNull($found, 'ai:precompute doit être planifié');
        $this->assertEquals('0 3 * * *', $found->expression, 'Doit tourner à 03:00 (cron 0 3 * * *)');
    }
}
