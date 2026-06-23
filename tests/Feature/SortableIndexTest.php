<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SortableIndexTest extends TestCase
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
            'email'    => 'user' . $counter . '@sort.test',
            'password' => bcrypt('password'),
            'role'     => $role,
        ]);
    }

    // ── Contacts ─────────────────────────────────────────────────────────────

    public function test_contacts_default_sort_by_last_name_asc(): void
    {
        $user = $this->makeUser();
        Contact::create(['first_name' => 'Zara', 'last_name' => 'Zulu',  'email' => 'z@test.com']);
        Contact::create(['first_name' => 'Anna', 'last_name' => 'Alpha', 'email' => 'a@test.com']);

        $response = $this->withAuth($user)->get('/contacts');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Zulu'), strpos($content, 'Alpha'));
    }

    public function test_contacts_sort_by_last_name_desc(): void
    {
        $user = $this->makeUser();
        Contact::create(['first_name' => 'Anna', 'last_name' => 'Alpha', 'email' => 'a2@test.com']);
        Contact::create(['first_name' => 'Zara', 'last_name' => 'Zulu',  'email' => 'z2@test.com']);

        $response = $this->withAuth($user)->get('/contacts?sort=last_name&dir=desc');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Alpha'), strpos($content, 'Zulu'));
    }

    public function test_contacts_sort_by_email_asc(): void
    {
        $user = $this->makeUser();
        Contact::create(['first_name' => 'B', 'last_name' => 'B', 'email' => 'beta@test.com']);
        Contact::create(['first_name' => 'A', 'last_name' => 'A', 'email' => 'alpha@test.com']);

        $response = $this->withAuth($user)->get('/contacts?sort=email&dir=asc');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'beta@'), strpos($content, 'alpha@'));
    }

    public function test_contacts_invalid_sort_column_falls_back_to_default(): void
    {
        $user = $this->makeUser();
        Contact::create(['first_name' => 'Test', 'last_name' => 'User', 'email' => 'tu@test.com']);

        $response = $this->withAuth($user)->get('/contacts?sort=injected_col&dir=asc');

        $response->assertOk();
    }

    // ── Companies ─────────────────────────────────────────────────────────────

    public function test_companies_default_sort_by_name_asc(): void
    {
        $user = $this->makeUser();
        Company::create(['name' => 'Zeta Corp']);
        Company::create(['name' => 'Alpha Inc']);

        $response = $this->withAuth($user)->get('/companies');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Zeta Corp'), strpos($content, 'Alpha Inc'));
    }

    public function test_companies_sort_by_name_desc(): void
    {
        $user = $this->makeUser();
        Company::create(['name' => 'Alpha Inc']);
        Company::create(['name' => 'Zeta Corp']);

        $response = $this->withAuth($user)->get('/companies?sort=name&dir=desc');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Alpha Inc'), strpos($content, 'Zeta Corp'));
    }

    public function test_companies_sort_by_city(): void
    {
        $user = $this->makeUser();
        Company::create(['name' => 'Co Z', 'city' => 'Zurich']);
        Company::create(['name' => 'Co A', 'city' => 'Annecy']);

        $response = $this->withAuth($user)->get('/companies?sort=city&dir=asc');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Zurich'), strpos($content, 'Annecy'));
    }

    public function test_companies_invalid_sort_column_falls_back_to_default(): void
    {
        $user = $this->makeUser();
        Company::create(['name' => 'Safe Corp']);

        $response = $this->withAuth($user)->get('/companies?sort=DROP+TABLE&dir=asc');

        $response->assertOk();
    }

    // ── Deals ─────────────────────────────────────────────────────────────────

    private function makePipeline(): array
    {
        $pipeline = Pipeline::create(['name' => 'Test', 'is_default' => true]);
        $stage    = $pipeline->stages()->create(['name' => 'Prospect', 'position' => 1, 'probability' => 10]);
        return [$pipeline, $stage];
    }

    public function test_deals_sort_by_amount_asc(): void
    {
        $user = $this->makeUser();
        [$pipeline, $stage] = $this->makePipeline();

        Deal::create(['name' => 'Small', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'status' => 'open', 'amount' => 100]);
        Deal::create(['name' => 'Large', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'status' => 'open', 'amount' => 9999]);

        $response = $this->withAuth($user)->get('/deals?sort=amount&dir=asc');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Large'), strpos($content, 'Small'));
    }

    public function test_deals_sort_by_amount_desc(): void
    {
        $user = $this->makeUser();
        [$pipeline, $stage] = $this->makePipeline();

        Deal::create(['name' => 'Small', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'status' => 'open', 'amount' => 100]);
        Deal::create(['name' => 'Large', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'status' => 'open', 'amount' => 9999]);

        $response = $this->withAuth($user)->get('/deals?sort=amount&dir=desc');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Small'), strpos($content, 'Large'));
    }

    public function test_deals_default_sort_by_close_date(): void
    {
        $user = $this->makeUser();
        [$pipeline, $stage] = $this->makePipeline();

        Deal::create(['name' => 'Far',  'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'status' => 'open', 'amount' => 0, 'close_date' => '2099-12-31']);
        Deal::create(['name' => 'Near', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'status' => 'open', 'amount' => 0, 'close_date' => '2025-01-01']);

        $response = $this->withAuth($user)->get('/deals');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Far'), strpos($content, 'Near'));
    }

    public function test_deals_invalid_sort_column_falls_back_to_default(): void
    {
        $user = $this->makeUser();
        [$pipeline, $stage] = $this->makePipeline();
        Deal::create(['name' => 'Safe', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'status' => 'open', 'amount' => 0]);

        $response = $this->withAuth($user)->get('/deals?sort=evil&dir=asc');

        $response->assertOk();
    }
}
