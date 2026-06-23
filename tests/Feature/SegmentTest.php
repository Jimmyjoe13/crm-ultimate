<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SegmentTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;
    private string $commercialToken;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::createWithRole([
            'name'     => 'Admin',
            'email'    => 'admin@example.com',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_ADMIN,
        ]);

        $commercial = User::createWithRole([
            'name'     => 'Commercial',
            'email'    => 'commercial@example.com',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_SALES,
        ]);

        $this->adminToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com', 'password' => 'password',
        ])->json('access_token');

        $this->commercialToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'commercial@example.com', 'password' => 'password',
        ])->json('access_token');
    }

    private function simpleRules(): array
    {
        return ['op' => 'AND', 'rules' => [['field' => 'lifecycle_stage', 'operator' => 'eq', 'value' => 'customer']]];
    }

    // ── CRUD permissions ──────────────────────────────────────────────────

    public function test_admin_can_create_segment(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/segments', [
                'name'        => 'Customers',
                'entity_type' => 'contact',
                'rules'       => $this->simpleRules(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Customers');

        $this->assertDatabaseHas('segments', ['name' => 'Customers', 'entity_type' => 'contact']);
    }

    public function test_commercial_cannot_create_segment(): void
    {
        $this->withToken($this->commercialToken)
            ->postJson('/api/v1/segments', [
                'name'        => 'Leak',
                'entity_type' => 'contact',
                'rules'       => $this->simpleRules(),
            ])
            ->assertStatus(403);
    }

    public function test_commercial_can_list_segments(): void
    {
        Segment::query()->create([
            'name' => 'Public Seg', 'entity_type' => 'contact',
            'rules' => $this->simpleRules(), 'created_by' => $this->admin->id,
        ]);

        $this->withToken($this->commercialToken)
            ->getJson('/api/v1/segments')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Public Seg');
    }

    public function test_admin_can_delete_segment(): void
    {
        $seg = Segment::query()->create([
            'name' => 'ToDelete', 'entity_type' => 'contact',
            'rules' => $this->simpleRules(), 'created_by' => $this->admin->id,
        ]);

        $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/segments/{$seg->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('segments', ['id' => $seg->id]);
    }

    public function test_422_on_invalid_operator(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/segments', [
                'name'        => 'Bad',
                'entity_type' => 'contact',
                'rules'       => ['op' => 'AND', 'rules' => [['field' => 'email', 'operator' => 'HACK', 'value' => 'x']]],
            ])
            ->assertStatus(422);
    }

    public function test_422_on_unknown_field(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/segments', [
                'name'        => 'Bad',
                'entity_type' => 'contact',
                'rules'       => ['op' => 'AND', 'rules' => [['field' => 'nonexistent', 'operator' => 'eq', 'value' => 'x']]],
            ])
            ->assertStatus(422);
    }

    public function test_422_on_unknown_custom_field(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/segments', [
                'name'        => 'Bad',
                'entity_type' => 'contact',
                'rules'       => ['op' => 'AND', 'rules' => [['field' => 'custom.nonexistent', 'operator' => 'eq', 'value' => 'x']]],
            ])
            ->assertStatus(422);
    }

    // ── Members & count ───────────────────────────────────────────────────

    public function test_members_returns_paginated_results_and_persists_count(): void
    {
        Contact::factory()->create(['lifecycle_stage' => 'customer']);
        Contact::factory()->create(['lifecycle_stage' => 'customer']);
        Contact::factory()->create(['lifecycle_stage' => 'lead']);

        $seg = Segment::query()->create([
            'name' => 'Customers', 'entity_type' => 'contact',
            'rules' => $this->simpleRules(), 'created_by' => $this->admin->id,
        ]);

        $resp = $this->withToken($this->adminToken)
            ->getJson("/api/v1/segments/{$seg->id}/members?per_page=25&page=1")
            ->assertOk();

        $this->assertEquals(2, $resp->json('total'));
        $this->assertCount(2, $resp->json('data'));

        $seg->refresh();
        $this->assertEquals(2, $seg->last_count);
        $this->assertNotNull($seg->last_computed_at);
    }

    public function test_refresh_count(): void
    {
        Contact::factory()->create(['lifecycle_stage' => 'customer']);

        $seg = Segment::query()->create([
            'name' => 'C', 'entity_type' => 'contact',
            'rules' => $this->simpleRules(), 'created_by' => $this->admin->id,
        ]);

        $resp = $this->withToken($this->adminToken)
            ->postJson("/api/v1/segments/{$seg->id}/refresh")
            ->assertOk();

        $this->assertEquals(1, $resp->json('count'));
        $this->assertNotNull($resp->json('computed_at'));
    }

    // ── Available fields ──────────────────────────────────────────────────

    public function test_fields_endpoint_returns_core_and_custom(): void
    {
        CustomField::query()->create(['entity_type' => 'contact', 'key' => 'tier', 'label' => 'Tier', 'field_type' => 'text']);

        $resp = $this->withToken($this->adminToken)
            ->getJson('/api/v1/segments/fields/contact')
            ->assertOk();

        $keys = array_column($resp->json('data'), 'key');
        $this->assertContains('email', $keys);
        $this->assertContains('lifecycle_stage', $keys);
        $this->assertContains('custom.tier', $keys);
        $this->assertContains('rel.deals.amount', $keys);
    }

    public function test_fields_endpoint_422_on_unknown_entity(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/v1/segments/fields/unknown')
            ->assertStatus(422);
    }

    // ── Preview (no persist) ──────────────────────────────────────────────

    public function test_preview_returns_count_without_persisting(): void
    {
        Contact::factory()->create(['lifecycle_stage' => 'customer']);

        $this->withToken($this->adminToken)
            ->postJson('/api/v1/segments/preview', [
                'entity_type' => 'contact',
                'rules'       => $this->simpleRules(),
            ])
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->assertDatabaseEmpty('segments');
    }

    // ── Cascade delete ────────────────────────────────────────────────────

    public function test_user_delete_nullifies_created_by(): void
    {
        $user = User::createWithRole([
            'name'     => 'Temp', 'email' => 'temp@example.com',
            'password' => Hash::make('password'), 'role' => User::ROLE_ADMIN,
        ]);
        $seg = Segment::query()->create([
            'name' => 'Orphan', 'entity_type' => 'contact',
            'rules' => $this->simpleRules(), 'created_by' => $user->id,
        ]);

        $user->delete();
        $seg->refresh();

        $this->assertNull($seg->created_by);
    }
}
