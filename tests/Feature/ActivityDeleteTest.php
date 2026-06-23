<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityDeleteTest extends TestCase
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
            'email'    => 'user' . $counter . '@delete.test',
            'password' => bcrypt('password'),
            'role'     => $role,
        ]);
    }

    private function makeActivity(User $user): Activity
    {
        return Activity::create([
            'type'     => 'task',
            'title'    => 'Test task',
            'status'   => 'open',
            'owner_id' => $user->id,
        ]);
    }

    private function deleteRequest(Activity $activity): \Illuminate\Testing\TestResponse
    {
        return $this->delete('/activities/' . $activity->id, ['_token' => 'test']);
    }

    public function test_owner_can_delete_activity(): void
    {
        $user     = $this->makeUser(User::ROLE_SALES);
        $activity = $this->makeActivity($user);

        $response = $this->withAuth($user)->deleteRequest($activity);

        $response->assertRedirect();
        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
    }

    public function test_admin_can_delete_others_activity(): void
    {
        $owner    = $this->makeUser(User::ROLE_SALES);
        $admin    = $this->makeUser(User::ROLE_ADMIN);
        $activity = $this->makeActivity($owner);

        $response = $this->withAuth($admin)->deleteRequest($activity);

        $response->assertRedirect();
        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
    }

    public function test_sales_cannot_delete_others_activity(): void
    {
        $owner    = $this->makeUser(User::ROLE_SALES);
        $other    = $this->makeUser(User::ROLE_SALES);
        $activity = $this->makeActivity($owner);

        $response = $this->withAuth($other)->deleteRequest($activity);

        $response->assertStatus(403);
        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
    }
}
