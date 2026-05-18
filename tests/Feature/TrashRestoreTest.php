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

class TrashRestoreTest extends TestCase
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
            'email'    => 'user' . $counter . '@trash.test',
            'password' => bcrypt('password'),
            'role'     => $role,
        ]);
    }

    // ── Page corbeille ────────────────────────────────────────────────────────

    public function test_admin_can_view_trash(): void
    {
        $admin = $this->makeUser();
        $c = Contact::create(['first_name' => 'Deleted', 'email' => 'del@test.com']);
        $c->delete();

        $response = $this->withAuth($admin)->get('/trash');
        $response->assertOk();
        $response->assertSeeText('Deleted');
    }

    public function test_viewer_cannot_view_trash(): void
    {
        $viewer = $this->makeUser(User::ROLE_SALES);
        $response = $this->withAuth($viewer)->get('/trash');
        $response->assertStatus(403);
    }

    // ── Restore contact ───────────────────────────────────────────────────────

    public function test_admin_can_restore_contact(): void
    {
        $admin = $this->makeUser();
        $c = Contact::create(['first_name' => 'Soft', 'email' => 'soft@test.com']);
        $c->delete();

        $this->assertSoftDeleted('contacts', ['id' => $c->id]);

        $response = $this->withAuth($admin)->post("/contacts/{$c->id}/restore", [
            '_token' => 'test',
        ]);

        $response->assertRedirect('/trash');
        $this->assertDatabaseHas('contacts', ['id' => $c->id, 'deleted_at' => null]);
    }

    public function test_manager_can_restore_contact(): void
    {
        $manager = $this->makeUser(User::ROLE_MANAGER);
        $c = Contact::create(['first_name' => 'Mgr', 'email' => 'mgr@test.com']);
        $c->delete();

        $response = $this->withAuth($manager)->post("/contacts/{$c->id}/restore", [
            '_token' => 'test',
        ]);

        $response->assertRedirect('/trash');
        $this->assertDatabaseHas('contacts', ['id' => $c->id, 'deleted_at' => null]);
    }

    public function test_viewer_cannot_restore_contact(): void
    {
        $viewer = $this->makeUser(User::ROLE_SALES);
        $c = Contact::create(['first_name' => 'NoRestore', 'email' => 'nr@test.com']);
        $c->delete();

        $response = $this->withAuth($viewer)->post("/contacts/{$c->id}/restore", [
            '_token' => 'test',
        ]);

        $response->assertStatus(403);
        $this->assertSoftDeleted('contacts', ['id' => $c->id]);
    }

    // ── Restore company ───────────────────────────────────────────────────────

    public function test_admin_can_restore_company(): void
    {
        $admin = $this->makeUser();
        $co = Company::create(['name' => 'OldCorp']);
        $co->delete();

        $this->assertSoftDeleted('companies', ['id' => $co->id]);

        $response = $this->withAuth($admin)->post("/companies/{$co->id}/restore", [
            '_token' => 'test',
        ]);

        $response->assertRedirect('/trash');
        $this->assertDatabaseHas('companies', ['id' => $co->id, 'deleted_at' => null]);
    }

    public function test_viewer_cannot_restore_company(): void
    {
        $viewer = $this->makeUser(User::ROLE_SALES);
        $co = Company::create(['name' => 'ProtectedCorp']);
        $co->delete();

        $response = $this->withAuth($viewer)->post("/companies/{$co->id}/restore", [
            '_token' => 'test',
        ]);

        $response->assertStatus(403);
        $this->assertSoftDeleted('companies', ['id' => $co->id]);
    }

    // ── Restore deal ──────────────────────────────────────────────────────────

    public function test_admin_can_restore_deal(): void
    {
        $admin    = $this->makeUser();
        $pipeline = Pipeline::create(['name' => 'TrashPipe', 'is_default' => true]);
        $stage    = $pipeline->stages()->create(['name' => 'Prospect', 'position' => 1, 'probability' => 10]);

        $d = Deal::create([
            'name'              => 'Lost Deal',
            'pipeline_id'       => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'status'            => 'open',
            'amount'            => 0,
        ]);
        $d->delete();

        $this->assertSoftDeleted('deals', ['id' => $d->id]);

        $response = $this->withAuth($admin)->post("/deals/{$d->id}/restore", [
            '_token' => 'test',
        ]);

        $response->assertRedirect('/trash');
        $this->assertDatabaseHas('deals', ['id' => $d->id, 'deleted_at' => null]);
    }

    public function test_viewer_cannot_restore_deal(): void
    {
        $viewer   = $this->makeUser(User::ROLE_SALES);
        $pipeline = Pipeline::create(['name' => 'TrashPipe2', 'is_default' => false]);
        $stage    = $pipeline->stages()->create(['name' => 'Prospect', 'position' => 1, 'probability' => 10]);

        $d = Deal::create([
            'name'              => 'Prot Deal',
            'pipeline_id'       => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'status'            => 'open',
            'amount'            => 0,
        ]);
        $d->delete();

        $response = $this->withAuth($viewer)->post("/deals/{$d->id}/restore", [
            '_token' => 'test',
        ]);

        $response->assertStatus(403);
        $this->assertSoftDeleted('deals', ['id' => $d->id]);
    }

    // ── Page vide ─────────────────────────────────────────────────────────────

    public function test_trash_page_empty_state(): void
    {
        $admin = $this->makeUser();
        $response = $this->withAuth($admin)->get('/trash');
        $response->assertOk();
        $response->assertSeeText('Aucun contact en corbeille.');
    }
}
