<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebDealControllerTest extends TestCase
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

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
        ]);
    }

    private function createDealWithStages(): array
    {
        $pipeline = Pipeline::create(['name' => 'Sales', 'is_default' => true]);
        $stage = $pipeline->stages()->create(['name' => 'Qualified', 'position' => 10, 'probability' => 30]);
        $wonStage = $pipeline->stages()->create(['name' => 'Won', 'position' => 90, 'probability' => 100, 'is_won' => true]);
        $lostStage = $pipeline->stages()->create(['name' => 'Lost', 'position' => 95, 'probability' => 0, 'is_lost' => true]);

        $admin = $this->createAdmin();
        $deal = Deal::create([
            'name' => 'Test Deal',
            'amount' => 5000,
            'status' => 'open',
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'owner_id' => $admin->id,
        ]);

        return compact('pipeline', 'stage', 'wonStage', 'lostStage', 'admin', 'deal');
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/deals');
        $response->assertRedirect(route('login'));
    }

    public function test_deal_index_shows_deals_list(): void
    {
        $ctx = $this->createDealWithStages();

        $response = $this->withAuth($ctx['admin'])->get('/deals');

        $response->assertOk();
        $response->assertSee('Test Deal');
    }

    public function test_deal_show_renders_drawer(): void
    {
        $ctx = $this->createDealWithStages();

        $response = $this->withAuth($ctx['admin'])->get('/deals/' . $ctx['deal']->id);

        $response->assertOk();
        $response->assertSee('Test Deal');
        $response->assertSee('Qualified');
    }

    public function test_mark_deal_won(): void
    {
        $ctx = $this->createDealWithStages();

        $response = $this->withAuth($ctx['admin'])
            ->post('/deals/' . $ctx['deal']->id . '/won', ['_token' => 'test']);

        $response->assertRedirect();
        $this->assertDatabaseHas('deals', [
            'id' => $ctx['deal']->id,
            'status' => 'won',
        ]);
    }

    public function test_mark_deal_lost(): void
    {
        $ctx = $this->createDealWithStages();

        $response = $this->withAuth($ctx['admin'])
            ->post('/deals/' . $ctx['deal']->id . '/lost', ['_token' => 'test']);

        $response->assertRedirect();
        $this->assertDatabaseHas('deals', [
            'id' => $ctx['deal']->id,
            'status' => 'lost',
        ]);
    }
}
