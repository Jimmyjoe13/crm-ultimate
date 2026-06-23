<?php

namespace Tests\Feature;

use App\Models\ConsoleRun;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConsoleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function withAuth(User $user): static
    {
        $jwt = app(JwtService::class)->encode(['sub' => $user->id, 'exp' => time() + 3600]);
        return $this->withCookies(['crm_jwt' => $jwt])->withSession(['_token' => 'test']);
    }

    private function postConsole(User $user, string $command): \Illuminate\Testing\TestResponse
    {
        return $this->withAuth($user)->post('/settings/console/run', [
            '_token'  => 'test',
            'command' => $command,
        ]);
    }

    private function makeAdmin(): User
    {
        return User::createWithRole(['name' => 'Admin', 'email' => 'a@test.com', 'password' => bcrypt('p'), 'role' => User::ROLE_ADMIN]);
    }

    private function makeManager(): User
    {
        return User::createWithRole(['name' => 'Manager', 'email' => 'm@test.com', 'password' => bcrypt('p'), 'role' => User::ROLE_MANAGER]);
    }

    private function makeSales(): User
    {
        return User::createWithRole(['name' => 'Sales', 'email' => 's@test.com', 'password' => bcrypt('p'), 'role' => User::ROLE_SALES]);
    }

    // T1 — admin accède à /settings/console
    public function test_admin_can_access_console(): void
    {
        $response = $this->withAuth($this->makeAdmin())->get('/settings/console');
        $response->assertOk();
    }

    // T2 — manager ne peut pas accéder (admin uniquement)
    public function test_manager_cannot_access_console(): void
    {
        $response = $this->withAuth($this->makeManager())->get('/settings/console');
        $response->assertForbidden();
    }

    // T3 — commercial ne peut pas accéder
    public function test_sales_cannot_access_console(): void
    {
        $response = $this->withAuth($this->makeSales())->get('/settings/console');
        $response->assertForbidden();
    }

    // T4 — commande inconnue → 422
    public function test_unknown_command_returns_422(): void
    {
        $response = $this->postConsole($this->makeAdmin(), 'rm -rf /');

        $response->assertStatus(422);
        $this->assertStringContainsString('non autoris', $response->json('message') ?? $response->getContent());
    }

    // T5 — commande async → job dispatché + run créé en base
    public function test_async_command_dispatches_job(): void
    {
        Queue::fake();

        $admin    = $this->makeAdmin();
        $response = $this->postConsole($admin, 'emelia-sync');

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('run_id', $data);
        $this->assertSame('pending', $data['status']);
        $this->assertTrue($data['async']);

        $this->assertDatabaseHas('console_runs', [
            'user_id'     => $admin->id,
            'command_key' => 'emelia-sync',
            'status'      => 'pending',
        ]);

        Queue::assertPushed(\App\Jobs\RunConsoleCommandJob::class);
    }

    // T6 — commande sync (cache:clear) → exécution immédiate
    public function test_sync_command_runs_immediately(): void
    {
        $admin    = $this->makeAdmin();
        $response = $this->postConsole($admin, 'cache-clear');

        $response->assertOk();
        $data = $response->json();
        $this->assertFalse($data['async']);
        $this->assertSame(0, $data['exit_code']);

        $this->assertDatabaseHas('console_runs', [
            'command_key' => 'cache-clear',
            'status'      => 'done',
        ]);
    }

    // T7 — GET /settings/console/run/{id} → JSON status
    public function test_status_endpoint_returns_run(): void
    {
        $admin = $this->makeAdmin();
        $run   = ConsoleRun::create([
            'user_id'       => $admin->id,
            'command_key'   => 'cache-clear',
            'command_label' => 'Vider le cache',
            'status'        => 'done',
            'output'        => 'Cache cleared.',
            'exit_code'     => 0,
            'started_at'    => now()->subSecond(),
            'finished_at'   => now(),
        ]);

        $response = $this->withAuth($admin)->get('/settings/console/run/' . $run->id);

        $response->assertOk();
        $data = $response->json();
        $this->assertSame('done', $data['status']);
        $this->assertArrayHasKey('duration_ms', $data);
    }

    // T8 — historique visible sur la page
    public function test_index_shows_recent_runs(): void
    {
        $admin = $this->makeAdmin();
        ConsoleRun::create([
            'user_id'       => $admin->id,
            'command_key'   => 'cache-clear',
            'command_label' => 'Vider le cache',
            'status'        => 'done',
            'exit_code'     => 0,
        ]);

        $response = $this->withAuth($admin)->get('/settings/console');
        $response->assertOk();
        $response->assertSeeText('Vider le cache');
    }

    // T9 — manager ne peut pas POST non plus
    public function test_manager_cannot_run_command(): void
    {
        $response = $this->postConsole($this->makeManager(), 'cache-clear');
        $response->assertForbidden();
    }
}
