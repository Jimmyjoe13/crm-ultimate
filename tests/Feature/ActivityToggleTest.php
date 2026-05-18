<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityToggleTest extends TestCase
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
            'email'    => 'user' . $counter . '@toggle.test',
            'password' => bcrypt('password'),
            'role'     => $role,
        ]);
    }

    private function makeActivity(User $user, string $status = 'open'): Activity
    {
        return Activity::create([
            'type'     => 'task',
            'title'    => 'Test task',
            'status'   => $status,
            'owner_id' => $user->id,
        ]);
    }

    private function togglePost(Activity $activity): \Illuminate\Testing\TestResponse
    {
        return $this->post('/activities/' . $activity->id . '/toggle-done', ['_token' => 'test'], [
            'Accept' => 'application/json',
        ]);
    }

    public function test_owner_can_toggle_task_done(): void
    {
        $user     = $this->makeUser(User::ROLE_SALES);
        $activity = $this->makeActivity($user);

        $response = $this->withAuth($user)->togglePost($activity);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'completed']);
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'status' => 'completed']);
    }

    public function test_owner_can_toggle_task_back_to_open(): void
    {
        $user     = $this->makeUser(User::ROLE_SALES);
        $activity = $this->makeActivity($user, 'completed');

        $response = $this->withAuth($user)->togglePost($activity);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'open']);
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'status' => 'open']);
    }

    public function test_admin_can_toggle_others_task(): void
    {
        $owner    = $this->makeUser(User::ROLE_SALES);
        $admin    = $this->makeUser(User::ROLE_ADMIN);
        $activity = $this->makeActivity($owner);

        $response = $this->withAuth($admin)->togglePost($activity);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'completed']);
    }

    public function test_sales_cannot_toggle_others_task(): void
    {
        $owner    = $this->makeUser(User::ROLE_SALES);
        $other    = $this->makeUser(User::ROLE_SALES);
        $activity = $this->makeActivity($owner);

        $response = $this->withAuth($other)->togglePost($activity);

        $response->assertStatus(403);
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'status' => 'open']);
    }
}
