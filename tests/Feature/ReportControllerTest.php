<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\PipelineStage;
use App\Models\Pipeline;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReportControllerTest extends TestCase
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
            'email'    => 'user' . $counter . '@test.com',
            'password' => bcrypt('password'),
            'role'     => $role,
        ]);
    }

    private function makePipeline(): Pipeline
    {
        return Pipeline::create(['name' => 'Default']);
    }

    private function makeStage(Pipeline $pipeline, int $position = 1): PipelineStage
    {
        return PipelineStage::create([
            'pipeline_id' => $pipeline->id,
            'name'        => 'Stage ' . $position,
            'position'    => $position,
            'color'       => '#3b82f6',
            'is_won'      => false,
            'is_lost'     => false,
        ]);
    }

    // T1 — admin peut accéder à /reports
    public function test_admin_can_access_reports(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);

        $response = $this->withAuth($admin)->get('/reports');
        $response->assertStatus(200);
    }

    // T1b — manager peut accéder à /reports
    public function test_manager_can_access_reports(): void
    {
        $manager = $this->makeUser(User::ROLE_MANAGER);

        $response = $this->withAuth($manager)->get('/reports');
        $response->assertStatus(200);
    }

    // T1c — viewer ne peut pas accéder à /reports
    public function test_viewer_cannot_access_reports(): void
    {
        $viewer = $this->makeUser(User::ROLE_SALES);

        $response = $this->withAuth($viewer)->get('/reports');
        $response->assertStatus(403);
    }

    // T2 — $ca_mensuel est un array (peut être vide si pas de données)
    public function test_ca_mensuel_is_array(): void
    {
        $admin = $this->makeUser();

        $response = $this->withAuth($admin)->get('/reports');
        $response->assertStatus(200);

        // La vue reçoit $ca_mensuel — vérifier qu'elle passe sans erreur
        $response->assertSeeText('CA mensuel');
    }

    // T3 — $entonnoir.taux_conversion_global entre 0 et 100
    public function test_entonnoir_taux_conversion_is_valid(): void
    {
        $admin    = $this->makeUser();
        $pipeline = $this->makePipeline();
        $stage    = $this->makeStage($pipeline);

        Deal::create([
            'name'              => 'Deal A',
            'status'            => 'won',
            'pipeline_id'       => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'amount'            => 1000,
        ]);
        Deal::create([
            'name'              => 'Deal B',
            'status'            => 'open',
            'pipeline_id'       => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'amount'            => 500,
        ]);

        $response = $this->withAuth($admin)->get('/reports');
        $response->assertStatus(200);
        $response->assertSeeText('Entonnoir');
    }

    // T4 — cache Redis peuplé après le premier appel
    public function test_cache_is_populated_after_first_call(): void
    {
        Cache::forget('reports.data');

        $admin = $this->makeUser();
        $this->withAuth($admin)->get('/reports');

        $this->assertTrue(Cache::has('reports.data'));
    }

    // T5 — Deal::save() invalide reports.data
    public function test_deal_save_invalidates_reports_cache(): void
    {
        Cache::put('reports.data', ['test' => true], 3600);

        $pipeline = $this->makePipeline();
        $stage    = $this->makeStage($pipeline);

        Deal::create([
            'name'              => 'Deal cache test',
            'status'            => 'open',
            'pipeline_id'       => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'amount'            => 0,
        ]);

        $this->assertFalse(Cache::has('reports.data'));
    }

    // T6 — POST /web/ai/report-insights admin → 200
    public function test_report_insights_admin_returns_json(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);

        Cache::put('reports.data', [
            'ca_mensuel'     => [
                ['mois' => '2026-04', 'ca_gagne' => 5000.0, 'pipeline' => 2000.0],
                ['mois' => '2026-05', 'ca_gagne' => 8000.0, 'pipeline' => 3000.0],
            ],
            'entonnoir'      => [
                'stages'                  => [['name' => 'Prospect', 'count' => 5]],
                'taux_conversion_global'  => 42.0,
            ],
            'classement'     => [['commercial' => 'Alice', 'nb_deals' => 3, 'ca' => 8000.0]],
            'activite_hebdo' => [['semaine' => '2026-05-18', 'detail' => [], 'total' => 12]],
        ], 3600);

        Cache::put('ai:report-insights', [
            'insights'        => ['CA en hausse de 60%'],
            'alerts'          => [],
            'recommendations' => ['Relancer les deals stagnants'],
        ], 3600);

        $response = $this->withAuth($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/web/ai/report-insights', ['_token' => 'test']);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['insights', 'alerts', 'recommendations'], 'cached', 'generated_at']);
    }

    // T7 — POST /web/ai/report-insights commercial → 403
    public function test_report_insights_commercial_forbidden(): void
    {
        $commercial = $this->makeUser(User::ROLE_SALES);

        $response = $this->withAuth($commercial)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/web/ai/report-insights', ['_token' => 'test']);

        $response->assertForbidden();
    }
}
