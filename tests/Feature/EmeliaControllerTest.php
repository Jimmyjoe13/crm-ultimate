<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use App\Services\EmeliaService;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmeliaControllerTest extends TestCase
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
            'name'     => 'User '.$counter,
            'email'    => 'emelia_ctrl_'.$counter.'@test.com',
            'password' => bcrypt('password'),
            'role'     => $role,
        ]);
    }

    public function test_campaigns_returns_json_list(): void
    {
        $this->mock(EmeliaService::class, function ($mock) {
            $mock->shouldReceive('listCampaigns')
                ->once()
                ->andReturn([['id' => 'camp_1', 'name' => 'Ma campagne']]);
        });

        $admin = $this->makeUser();
        $this->withAuth($admin)
            ->get('/emelia/campaigns')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Ma campagne']);
    }

    public function test_add_contact_sets_emelia_contact_id(): void
    {
        $this->mock(EmeliaService::class, function ($mock) {
            $mock->shouldReceive('listCampaigns')
                ->once()
                ->andReturn([['_id' => 'camp_1', 'name' => 'Test Campaign']]);
            $mock->shouldReceive('addContactToCampaign')
                ->once()
                ->andReturn(['id' => 'em_42']);
        });

        $admin = $this->makeUser();
        $contact = Contact::create(['email' => 'alice@test.com', 'first_name' => 'Alice', 'last_name' => 'D']);

        $this->withAuth($admin)
            ->post('/contacts/'.$contact->id.'/emelia', ['campaign_id' => 'camp_1', '_token' => 'test'])
            ->assertRedirect();

        $this->assertEquals('em_42', $contact->fresh()->emelia_contact_id);
    }

    public function test_viewer_cannot_add_contact_to_campaign(): void
    {
        $viewer = $this->makeUser(User::ROLE_SALES);
        $contact = Contact::create(['first_name' => 'Bob', 'email' => 'bob@test.com']);

        $this->withAuth($viewer)
            ->post('/contacts/'.$contact->id.'/emelia', ['campaign_id' => 'camp_1', '_token' => 'test'])
            ->assertStatus(403);
    }
}
