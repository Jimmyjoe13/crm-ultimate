<?php

namespace Tests\Feature;

use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrmApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_company_contact_pipeline_and_deal(): void
    {
        $token = $this->adminToken();

        $company = $this->withToken($token)->postJson('/api/v1/companies', [
            'name' => 'Acme Corp',
            'domain' => 'acme.test',
            'custom_values' => ['segment' => 'enterprise'],
        ])->assertCreated()->json('data');

        $contact = $this->withToken($token)->postJson('/api/v1/contacts', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@acme.test',
        ])->assertCreated()->json('data');

        // Attach contact to company via pivot
        $this->withToken($token)->postJson("/api/v1/contacts/{$contact['id']}/companies", [
            'company_id' => $company['id'],
            'role' => 'employee',
            'is_primary' => true,
        ])->assertOk();

        $pipeline = Pipeline::query()->create(['name' => 'Sales', 'is_default' => true]);
        $stage = $pipeline->stages()->create(['name' => 'Qualified', 'position' => 10, 'probability' => 30]);

        $deal = $this->withToken($token)->postJson('/api/v1/deals', [
            'name' => 'Acme Expansion',
            'amount' => 25000,
            'currency' => 'EUR',
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ])->assertCreated()->assertJsonPath('data.status', 'open')->json('data');

        // Associate company and contact to deal
        $this->withToken($token)->postJson("/api/v1/deals/{$deal['id']}/companies", [
            'company_id' => $company['id'],
            'role' => 'customer',
            'is_primary' => true,
        ])->assertOk();

        $this->withToken($token)->postJson("/api/v1/deals/{$deal['id']}/contacts", [
            'contact_id' => $contact['id'],
            'role' => 'primary',
        ])->assertOk();
    }

    public function test_deal_board_and_detail_include_related_context(): void
    {
        $token = $this->adminToken();
        $pipeline = Pipeline::query()->create(['name' => 'Sales', 'is_default' => true]);
        $stage = $pipeline->stages()->create(['name' => 'Qualified', 'position' => 10, 'probability' => 30]);
        $wonStage = $pipeline->stages()->create(['name' => 'Won', 'position' => 20, 'probability' => 100, 'is_won' => true]);

        $deal = $this->withToken($token)->postJson('/api/v1/deals', [
            'name' => 'Board Deal',
            'amount' => 12500,
            'currency' => 'EUR',
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ])->assertCreated()->json('data');

        $this->withToken($token)
            ->getJson('/api/v1/deals/board')
            ->assertOk()
            ->assertJsonPath('pipeline.id', $pipeline->id)
            ->assertJsonCount(2, 'columns');

        $this->withToken($token)
            ->postJson('/api/v1/deals/'.$deal['id'].'/move', [
                'pipeline_stage_id' => $wonStage->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'won');

        $this->withToken($token)
            ->getJson('/api/v1/deals/'.$deal['id'])
            ->assertOk()
            ->assertJsonStructure(['data', 'activities', 'audit_logs']);
    }

    private function adminToken(): string
    {
        User::query()->create([
            'name' => 'CRM Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        return $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('access_token');
    }
}
