<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Segment;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebSegmentControllerTest extends TestCase
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

    public function test_segment_index_page_loads(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withAuth($admin)->get('/segments');

        $response->assertOk();
    }

    public function test_segment_create_page_loads(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withAuth($admin)->get('/segments/create');

        $response->assertOk();
    }

    public function test_store_segment_with_valid_rules(): void
    {
        $admin = $this->createAdmin();
        $rules = ['op' => 'AND', 'rules' => [
            ['field' => 'email', 'operator' => 'is_not_null', 'value' => null],
        ]];

        $response = $this->withAuth($admin)->post('/segments', [
            '_token' => 'test',
            'name' => 'Active contacts',
            'entity_type' => 'contact',
            'rules' => json_encode($rules),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('segments', [
            'name' => 'Active contacts',
            'entity_type' => 'contact',
            'created_by' => $admin->id,
        ]);
    }

    public function test_store_segment_with_invalid_rules_returns_back(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withAuth($admin)->post('/segments', [
            '_token' => 'test',
            'name' => 'Bad segment',
            'entity_type' => 'contact',
            'rules' => json_encode(['op' => 'INVALID']),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('segments', ['name' => 'Bad segment']);
    }

    public function test_show_segment_displays_members(): void
    {
        $admin = $this->createAdmin();

        Contact::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@test.com',
            'owner_id' => $admin->id,
        ]);

        $segment = Segment::create([
            'name' => 'Has email',
            'entity_type' => 'contact',
            'rules' => ['op' => 'AND', 'rules' => [
                ['field' => 'email', 'operator' => 'is_not_null', 'value' => null],
            ]],
            'created_by' => $admin->id,
        ]);

        $response = $this->withAuth($admin)->get('/segments/' . $segment->id);

        $response->assertOk();
        $response->assertSee('jane@test.com');
    }

    public function test_update_segment(): void
    {
        $admin = $this->createAdmin();
        $segment = Segment::create([
            'name' => 'Old name',
            'entity_type' => 'contact',
            'rules' => ['op' => 'AND', 'rules' => [
                ['field' => 'email', 'operator' => 'is_not_null', 'value' => null],
            ]],
            'created_by' => $admin->id,
        ]);

        $response = $this->withAuth($admin)->put('/segments/' . $segment->id, [
            '_token' => 'test',
            'name' => 'New name',
            'entity_type' => 'contact',
            'rules' => json_encode(['op' => 'AND', 'rules' => [
                ['field' => 'email', 'operator' => 'is_not_null', 'value' => null],
            ]]),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('segments', ['id' => $segment->id, 'name' => 'New name']);
    }

    public function test_delete_segment(): void
    {
        $admin = $this->createAdmin();
        $segment = Segment::create([
            'name' => 'To delete',
            'entity_type' => 'contact',
            'rules' => ['op' => 'AND', 'rules' => []],
            'created_by' => $admin->id,
        ]);

        $response = $this->withAuth($admin)->delete('/segments/' . $segment->id, ['_token' => 'test']);

        $response->assertRedirect();
        $this->assertDatabaseMissing('segments', ['id' => $segment->id]);
    }

    public function test_preview_returns_count_and_sample(): void
    {
        $admin = $this->createAdmin();

        Contact::create([
            'first_name' => 'Bob',
            'last_name' => 'Test',
            'email' => 'bob@test.com',
            'owner_id' => $admin->id,
        ]);

        $response = $this->withAuth($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/segments/preview', [
                '_token' => 'test',
                'entity_type' => 'contact',
                'rules' => ['op' => 'AND', 'rules' => [
                    ['field' => 'email', 'operator' => 'is_not_null', 'value' => null],
                ]],
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['count', 'sample']);
        $response->assertJsonPath('count', 1);
    }

    public function test_export_contact_segment_returns_csv(): void
    {
        $admin = $this->createAdmin();

        Contact::create([
            'first_name' => 'Export',
            'last_name'  => 'Test',
            'email'      => 'export@test.com',
            'phone'      => '0600000000',
            'owner_id'   => $admin->id,
        ]);

        $segment = Segment::create([
            'name'        => 'Export contacts',
            'entity_type' => 'contact',
            'rules'       => ['op' => 'AND', 'rules' => [
                ['field' => 'email', 'operator' => 'is_not_null', 'value' => null],
            ]],
            'created_by'  => $admin->id,
        ]);

        $response = $this->withAuth($admin)->get('/segments/' . $segment->id . '/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('export@test.com', $response->streamedContent());
    }

    public function test_export_company_segment_returns_csv(): void
    {
        $admin = $this->createAdmin();

        Company::create([
            'name'     => 'Acme Corp',
            'domain'   => 'acme.com',
            'industry' => 'Tech',
            'owner_id' => $admin->id,
        ]);

        $segment = Segment::create([
            'name'        => 'All companies',
            'entity_type' => 'company',
            'rules'       => ['op' => 'AND', 'rules' => []],
            'created_by'  => $admin->id,
        ]);

        $response = $this->withAuth($admin)->get('/segments/' . $segment->id . '/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Acme Corp', $response->streamedContent());
    }

    public function test_export_deal_segment_returns_csv(): void
    {
        $admin    = $this->createAdmin();
        $pipeline = Pipeline::create(['name' => 'Test Pipeline', 'is_default' => true]);
        $stage    = $pipeline->stages()->create(['name' => 'Prospect', 'position' => 1, 'probability' => 10]);

        Deal::create([
            'name'              => 'Big Deal',
            'amount'            => 5000,
            'status'            => 'open',
            'pipeline_id'       => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'owner_id'          => $admin->id,
        ]);

        $segment = Segment::create([
            'name'        => 'Open deals',
            'entity_type' => 'deal',
            'rules'       => ['op' => 'AND', 'rules' => [
                ['field' => 'status', 'operator' => 'eq', 'value' => 'open'],
            ]],
            'created_by'  => $admin->id,
        ]);

        $response = $this->withAuth($admin)->get('/segments/' . $segment->id . '/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Big Deal', $response->streamedContent());
    }
}
