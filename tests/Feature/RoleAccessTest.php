<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
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

    private function makeUser(string $role): User
    {
        return User::createWithRole([
            'name' => ucfirst($role),
            'email' => $role.'@test.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    public function test_viewer_cannot_access_settings_fields(): void
    {
        $viewer = $this->makeUser(User::ROLE_SALES);
        $response = $this->withAuth($viewer)->get('/settings/fields');
        $response->assertStatus(403);
    }

    public function test_manager_can_access_settings_fields(): void
    {
        $manager = $this->makeUser(User::ROLE_MANAGER);
        $response = $this->withAuth($manager)->get('/settings/fields');
        $response->assertStatus(200);
    }

    public function test_admin_can_access_settings_fields(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $response = $this->withAuth($admin)->get('/settings/fields');
        $response->assertStatus(200);
    }

    public function test_viewer_cannot_access_settings_stages(): void
    {
        $viewer = $this->makeUser(User::ROLE_SALES);
        $response = $this->withAuth($viewer)->get('/settings/stages');
        $response->assertStatus(403);
    }

    public function test_viewer_cannot_access_imports(): void
    {
        $viewer = $this->makeUser(User::ROLE_SALES);
        $response = $this->withAuth($viewer)->get('/imports/contact/create');
        $response->assertStatus(403);
    }

    public function test_manager_can_access_imports(): void
    {
        $manager = $this->makeUser(User::ROLE_MANAGER);
        $response = $this->withAuth($manager)->get('/imports/contact/create');
        $response->assertStatus(200);
    }
}
