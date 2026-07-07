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

class BulkActionsTest extends TestCase
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
            'name' => 'User '.$counter,
            'email' => 'user'.$counter.'@bulk.test',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    // ── Contacts ────────────────────────────────────────────────────────────────

    public function test_bulk_destroy_contacts(): void
    {
        $admin = $this->makeUser();
        $c1 = Contact::create(['first_name' => 'A', 'email' => 'a@test.com']);
        $c2 = Contact::create(['first_name' => 'B', 'email' => 'b@test.com']);
        $c3 = Contact::create(['first_name' => 'C', 'email' => 'c@test.com']);

        $response = $this->withAuth($admin)->post('/contacts/bulk-destroy', [
            'ids' => [$c1->id, $c2->id],
            '_token' => 'test',
        ]);

        $response->assertRedirect('/contacts');
        $this->assertSoftDeleted('contacts', ['id' => $c1->id]);
        $this->assertSoftDeleted('contacts', ['id' => $c2->id]);
        $this->assertDatabaseHas('contacts', ['id' => $c3->id, 'deleted_at' => null]);
    }

    public function test_viewer_cannot_bulk_destroy_contacts(): void
    {
        $viewer = $this->makeUser(User::ROLE_SALES);
        $c = Contact::create(['first_name' => 'D', 'email' => 'd@test.com']);

        $response = $this->withAuth($viewer)->post('/contacts/bulk-destroy', [
            'ids' => [$c->id],
            '_token' => 'test',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('contacts', ['id' => $c->id, 'deleted_at' => null]);
    }

    // ── Companies ───────────────────────────────────────────────────────────────

    public function test_bulk_destroy_companies(): void
    {
        $admin = $this->makeUser();
        $co1 = Company::create(['name' => 'Alpha']);
        $co2 = Company::create(['name' => 'Beta']);
        $co3 = Company::create(['name' => 'Gamma']);

        $response = $this->withAuth($admin)->post('/companies/bulk-destroy', [
            'ids' => [$co1->id, $co2->id],
            '_token' => 'test',
        ]);

        $response->assertRedirect('/companies');
        $this->assertSoftDeleted('companies', ['id' => $co1->id]);
        $this->assertSoftDeleted('companies', ['id' => $co2->id]);
        $this->assertDatabaseHas('companies', ['id' => $co3->id, 'deleted_at' => null]);
    }

    public function test_viewer_cannot_bulk_destroy_companies(): void
    {
        $viewer = $this->makeUser(User::ROLE_SALES);
        $co = Company::create(['name' => 'Protected']);

        $response = $this->withAuth($viewer)->post('/companies/bulk-destroy', [
            'ids' => [$co->id],
            '_token' => 'test',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('companies', ['id' => $co->id, 'deleted_at' => null]);
    }

    // ── Deals ───────────────────────────────────────────────────────────────────

    public function test_bulk_destroy_deals(): void
    {
        $admin = $this->makeUser();
        $pipeline = Pipeline::create(['name' => 'Test', 'is_default' => true]);
        $stage = $pipeline->stages()->create(['name' => 'Prospect', 'position' => 1, 'probability' => 10]);

        $d1 = Deal::create(['name' => 'Deal 1', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'status' => 'open', 'amount' => 0]);
        $d2 = Deal::create(['name' => 'Deal 2', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'status' => 'open', 'amount' => 0]);
        $d3 = Deal::create(['name' => 'Deal 3', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'status' => 'open', 'amount' => 0]);

        $response = $this->withAuth($admin)->post('/deals/bulk-destroy', [
            'ids' => [$d1->id, $d2->id],
            '_token' => 'test',
        ]);

        $response->assertRedirect('/deals');
        $this->assertSoftDeleted('deals', ['id' => $d1->id]);
        $this->assertSoftDeleted('deals', ['id' => $d2->id]);
        $this->assertDatabaseHas('deals', ['id' => $d3->id, 'deleted_at' => null]);
    }

    public function test_viewer_cannot_bulk_destroy_deals(): void
    {
        $viewer = $this->makeUser(User::ROLE_SALES);
        $pipeline = Pipeline::create(['name' => 'Test2', 'is_default' => false]);
        $stage = $pipeline->stages()->create(['name' => 'Prospect', 'position' => 1, 'probability' => 10]);
        $d = Deal::create(['name' => 'Prot', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id, 'status' => 'open', 'amount' => 0]);

        $response = $this->withAuth($viewer)->post('/deals/bulk-destroy', [
            'ids' => [$d->id],
            '_token' => 'test',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('deals', ['id' => $d->id, 'deleted_at' => null]);
    }

    public function test_bulk_destroy_requires_at_least_one_id(): void
    {
        $admin = $this->makeUser();

        $response = $this->withAuth($admin)->post('/contacts/bulk-destroy', [
            'ids' => [],
            '_token' => 'test',
        ]);

        $response->assertSessionHasErrors('ids');
    }
}
