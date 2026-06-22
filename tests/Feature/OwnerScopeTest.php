<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cloisonnement des données par owner (trait ScopesToOwner + CrudActions::scopedQuery).
 *
 * Règle implémentée (voir User::accessibleOwnerIds()) :
 *  - admin       : voit / modifie tout (aucun filtre).
 *  - manager     : son propre périmètre = lui-même + ses commerciaux (users.manager_id).
 *  - commercial  : strictement ses propres enregistrements (owner_id = son id).
 *
 * Sur l'API, un enregistrement hors périmètre renvoie 404 (findOrFail sur la requête
 * déjà scopée → ne leak pas l'existence) pour show/update/destroy, et il est simplement
 * absent de la liste index.
 */
class OwnerScopeTest extends TestCase
{
    use RefreshDatabase;

    private function withAuth(User $user): static
    {
        // API stateless : on passe le JWT en header Bearer (lu en priorité par
        // JwtMiddleware via bearerToken()). Le cookie `crm_jwt`, lui, est attendu
        // CHIFFRÉ (Crypt::decrypt) et ne convient donc pas à un JWT brut.
        $jwt = app(JwtService::class)->encode([
            'sub'  => $user->id,
            'role' => $user->role,
            'exp'  => time() + 3600,
        ]);

        return $this->withToken($jwt);
    }

    private function makeUser(string $role, ?int $managerId = null): User
    {
        static $seq = 0;
        $seq++;

        return User::create([
            'name'       => ucfirst($role) . " {$seq}",
            'email'      => "{$role}-{$seq}-scope@example.test",
            'password'   => bcrypt('password'),
            'role'       => $role,
            'manager_id' => $managerId,
        ]);
    }

    // ── Contact : index ──────────────────────────────────────────────────────

    public function test_sales_index_only_returns_own_contacts(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $other = $this->makeUser(User::ROLE_SALES);

        $mine    = Contact::factory()->create(['owner_id' => $sales->id]);
        $theirs  = Contact::factory()->create(['owner_id' => $other->id]);

        $response = $this->withAuth($sales)
            ->getJson('/api/v1/contacts')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_admin_index_returns_all_contacts(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $sales = $this->makeUser(User::ROLE_SALES);

        $a = Contact::factory()->create(['owner_id' => $admin->id]);
        $b = Contact::factory()->create(['owner_id' => $sales->id]);

        $response = $this->withAuth($admin)
            ->getJson('/api/v1/contacts')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
    }

    public function test_manager_index_returns_team_contacts_but_not_others(): void
    {
        $manager = $this->makeUser(User::ROLE_MANAGER);
        $sub     = $this->makeUser(User::ROLE_SALES, $manager->id);
        $outside = $this->makeUser(User::ROLE_SALES);

        $own     = Contact::factory()->create(['owner_id' => $manager->id]);
        $teamOne = Contact::factory()->create(['owner_id' => $sub->id]);
        $foreign = Contact::factory()->create(['owner_id' => $outside->id]);

        $response = $this->withAuth($manager)
            ->getJson('/api/v1/contacts')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertContains($teamOne->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    // ── Contact : show / update / destroy hors périmètre ──────────────────────

    public function test_sales_cannot_show_others_contact(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $other = $this->makeUser(User::ROLE_SALES);

        $theirs = Contact::factory()->create(['owner_id' => $other->id]);

        $this->withAuth($sales)
            ->getJson("/api/v1/contacts/{$theirs->id}")
            ->assertNotFound();
    }

    public function test_sales_can_show_own_contact(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $mine  = Contact::factory()->create(['owner_id' => $sales->id]);

        $this->withAuth($sales)
            ->getJson("/api/v1/contacts/{$mine->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id);
    }

    public function test_sales_cannot_update_others_contact(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $other = $this->makeUser(User::ROLE_SALES);

        $theirs = Contact::factory()->create(['owner_id' => $other->id, 'first_name' => 'Original']);

        $this->withAuth($sales)
            ->putJson("/api/v1/contacts/{$theirs->id}", ['first_name' => 'Hacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('contacts', [
            'id'         => $theirs->id,
            'first_name' => 'Original',
        ]);
    }

    public function test_sales_cannot_destroy_others_contact(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $other = $this->makeUser(User::ROLE_SALES);

        $theirs = Contact::factory()->create(['owner_id' => $other->id]);

        $this->withAuth($sales)
            ->deleteJson("/api/v1/contacts/{$theirs->id}")
            ->assertNotFound();

        // SoftDeletes : l'enregistrement existe toujours et n'est pas supprimé.
        $this->assertNull($theirs->fresh()->deleted_at);
    }

    public function test_admin_can_show_any_contact(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $sales = $this->makeUser(User::ROLE_SALES);

        $theirs = Contact::factory()->create(['owner_id' => $sales->id]);

        $this->withAuth($admin)
            ->getJson("/api/v1/contacts/{$theirs->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $theirs->id);
    }

    // ── Deal : applique le même trait ─────────────────────────────────────────

    public function test_sales_index_only_returns_own_deals(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $other = $this->makeUser(User::ROLE_SALES);

        $mine   = Deal::factory()->create(['owner_id' => $sales->id]);
        $theirs = Deal::factory()->create(['owner_id' => $other->id]);

        $response = $this->withAuth($sales)
            ->getJson('/api/v1/deals')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_sales_cannot_show_others_deal(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $other = $this->makeUser(User::ROLE_SALES);

        $theirs = Deal::factory()->create(['owner_id' => $other->id]);

        $this->withAuth($sales)
            ->getJson("/api/v1/deals/{$theirs->id}")
            ->assertNotFound();
    }

    public function test_manager_can_show_subordinate_deal(): void
    {
        $manager = $this->makeUser(User::ROLE_MANAGER);
        $sub     = $this->makeUser(User::ROLE_SALES, $manager->id);

        $deal = Deal::factory()->create(['owner_id' => $sub->id]);

        $this->withAuth($manager)
            ->getJson("/api/v1/deals/{$deal->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $deal->id);
    }

    // ── Company : applique le même trait ──────────────────────────────────────

    public function test_sales_index_only_returns_own_companies(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $other = $this->makeUser(User::ROLE_SALES);

        $mine   = Company::factory()->create(['owner_id' => $sales->id]);
        $theirs = Company::factory()->create(['owner_id' => $other->id]);

        $response = $this->withAuth($sales)
            ->getJson('/api/v1/companies')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_sales_cannot_destroy_others_company(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $other = $this->makeUser(User::ROLE_SALES);

        $theirs = Company::factory()->create(['owner_id' => $other->id]);

        $this->withAuth($sales)
            ->deleteJson("/api/v1/companies/{$theirs->id}")
            ->assertNotFound();

        $this->assertNull($theirs->fresh()->deleted_at);
    }
}
