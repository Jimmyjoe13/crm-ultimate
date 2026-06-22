<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cloisonnement par owner côté WEB (route-model binding + AuthorizesOwnerAccess::ensureVisible()).
 *
 * Le binding charge l'enregistrement par id sans filtre de périmètre ; ensureVisible()
 * vérifie a posteriori et renvoie 404 si l'enregistrement est hors du scope de l'utilisateur
 * (ne leak pas l'existence), de manière cohérente avec ContactController.
 *
 * Auth Web : cookie chiffré `crm_jwt` + jeton CSRF de session (calqué sur les tests Web
 * existants WebDealControllerTest / WebCompanyControllerTest).
 *
 * Note rôle : la suppression (destroy) est réservée par middleware `role:admin,manager`.
 * Un commercial y reçoit donc 403 avant d'atteindre le contrôleur ; le 404 de scope sur
 * destroy est donc prouvé avec un MANAGER (rôle autorisé) ciblant un enregistrement
 * hors de son équipe.
 */
class WebOwnerScopeTest extends TestCase
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

    private function makeUser(string $role, ?int $managerId = null): User
    {
        static $seq = 0;
        $seq++;

        return User::create([
            'name'       => ucfirst($role) . " {$seq}",
            'email'      => "{$role}-{$seq}-webscope@example.test",
            'password'   => bcrypt('password'),
            'role'       => $role,
            'manager_id' => $managerId,
        ]);
    }

    private function makeStage(): PipelineStage
    {
        $pipeline = Pipeline::create(['name' => 'Sales', 'is_default' => true]);

        return $pipeline->stages()->create([
            'name'        => 'Qualified',
            'position'    => 10,
            'probability' => 30,
        ]);
    }

    private function makeDeal(int $ownerId): Deal
    {
        $stage = $this->makeStage();

        return Deal::create([
            'name'              => 'Deal ' . uniqid(),
            'amount'            => 5000,
            'status'            => 'open',
            'pipeline_id'       => $stage->pipeline_id,
            'pipeline_stage_id' => $stage->id,
            'owner_id'          => $ownerId,
        ]);
    }

    // ── Deal : routes Web hors / dans périmètre ───────────────────────────────

    public function test_sales_gets_404_on_others_deal_show(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $other = $this->makeUser(User::ROLE_SALES);
        $deal  = $this->makeDeal($other->id);

        $this->withAuth($sales)
            ->get('/deals/' . $deal->id)
            ->assertNotFound();
    }

    public function test_sales_gets_200_on_own_deal_show(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $deal  = $this->makeDeal($sales->id);

        $this->withAuth($sales)
            ->get('/deals/' . $deal->id)
            ->assertOk();
    }

    public function test_sales_gets_404_on_others_deal_edit(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $other = $this->makeUser(User::ROLE_SALES);
        $deal  = $this->makeDeal($other->id);

        $this->withAuth($sales)
            ->get('/deals/' . $deal->id . '/edit')
            ->assertNotFound();
    }

    public function test_sales_gets_200_on_own_deal_edit(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $deal  = $this->makeDeal($sales->id);

        $this->withAuth($sales)
            ->get('/deals/' . $deal->id . '/edit')
            ->assertOk();
    }

    public function test_sales_gets_404_on_others_deal_update(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $other = $this->makeUser(User::ROLE_SALES);
        $deal  = $this->makeDeal($other->id);

        $this->withAuth($sales)
            ->put('/deals/' . $deal->id, [
                'name'              => 'Hacked',
                'amount'            => 1,
                'pipeline_stage_id' => $deal->pipeline_stage_id,
                '_token'            => 'test',
            ])
            ->assertNotFound();

        // L'enregistrement n'a pas été modifié.
        $this->assertDatabaseHas('deals', ['id' => $deal->id, 'name' => $deal->name]);
    }

    public function test_sales_gets_200_on_own_deal_update(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $deal  = $this->makeDeal($sales->id);

        $this->withAuth($sales)
            ->put('/deals/' . $deal->id, [
                'name'              => 'Updated',
                'amount'            => 9999,
                'pipeline_stage_id' => $deal->pipeline_stage_id,
                '_token'            => 'test',
            ])
            ->assertRedirect('/deals/' . $deal->id);

        $this->assertDatabaseHas('deals', ['id' => $deal->id, 'name' => 'Updated']);
    }

    public function test_manager_gets_404_on_foreign_deal_destroy(): void
    {
        // Manager autorisé à destroy (role:admin,manager) mais le deal appartient
        // à un commercial hors de son équipe → 404 de scope, pas de suppression.
        $manager = $this->makeUser(User::ROLE_MANAGER);
        $outside = $this->makeUser(User::ROLE_SALES);
        $deal    = $this->makeDeal($outside->id);

        $this->withAuth($manager)
            ->delete('/deals/' . $deal->id, ['_token' => 'test'])
            ->assertNotFound();

        $this->assertNull($deal->fresh()->deleted_at);
    }

    public function test_manager_gets_200_on_own_deal_destroy(): void
    {
        $manager = $this->makeUser(User::ROLE_MANAGER);
        $deal    = $this->makeDeal($manager->id);

        $this->withAuth($manager)
            ->delete('/deals/' . $deal->id, ['_token' => 'test'])
            ->assertRedirect('/deals');

        $this->assertSoftDeleted('deals', ['id' => $deal->id]);
    }

    // ── Company : routes Web hors / dans périmètre ────────────────────────────

    public function test_sales_gets_404_on_others_company_show(): void
    {
        $sales   = $this->makeUser(User::ROLE_SALES);
        $other   = $this->makeUser(User::ROLE_SALES);
        $company = Company::create(['name' => 'Foreign Co', 'owner_id' => $other->id]);

        $this->withAuth($sales)
            ->get('/companies/' . $company->id)
            ->assertNotFound();
    }

    public function test_sales_gets_200_on_own_company_show(): void
    {
        $sales   = $this->makeUser(User::ROLE_SALES);
        $company = Company::create(['name' => 'My Co', 'owner_id' => $sales->id]);

        $this->withAuth($sales)
            ->get('/companies/' . $company->id)
            ->assertOk();
    }

    public function test_sales_gets_404_on_others_company_edit(): void
    {
        $sales   = $this->makeUser(User::ROLE_SALES);
        $other   = $this->makeUser(User::ROLE_SALES);
        $company = Company::create(['name' => 'Foreign Co', 'owner_id' => $other->id]);

        $this->withAuth($sales)
            ->get('/companies/' . $company->id . '/edit')
            ->assertNotFound();
    }

    public function test_sales_gets_200_on_own_company_edit(): void
    {
        $sales   = $this->makeUser(User::ROLE_SALES);
        $company = Company::create(['name' => 'My Co', 'owner_id' => $sales->id]);

        $this->withAuth($sales)
            ->get('/companies/' . $company->id . '/edit')
            ->assertOk();
    }

    public function test_sales_gets_404_on_others_company_update(): void
    {
        $sales   = $this->makeUser(User::ROLE_SALES);
        $other   = $this->makeUser(User::ROLE_SALES);
        $company = Company::create(['name' => 'Original Co', 'owner_id' => $other->id]);

        $this->withAuth($sales)
            ->put('/companies/' . $company->id, [
                'name'   => 'Hacked Co',
                '_token' => 'test',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'Original Co']);
    }

    public function test_sales_gets_200_on_own_company_update(): void
    {
        $sales   = $this->makeUser(User::ROLE_SALES);
        $company = Company::create(['name' => 'Original Co', 'owner_id' => $sales->id]);

        $this->withAuth($sales)
            ->put('/companies/' . $company->id, [
                'name'   => 'Updated Co',
                '_token' => 'test',
            ])
            ->assertRedirect('/companies/' . $company->id);

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'Updated Co']);
    }

    public function test_manager_gets_404_on_foreign_company_destroy(): void
    {
        $manager = $this->makeUser(User::ROLE_MANAGER);
        $outside = $this->makeUser(User::ROLE_SALES);
        $company = Company::create(['name' => 'Foreign Co', 'owner_id' => $outside->id]);

        $this->withAuth($manager)
            ->delete('/companies/' . $company->id, ['_token' => 'test'])
            ->assertNotFound();

        $this->assertNull($company->fresh()->deleted_at);
    }

    public function test_manager_gets_200_on_own_company_destroy(): void
    {
        $manager = $this->makeUser(User::ROLE_MANAGER);
        $company = Company::create(['name' => 'My Co', 'owner_id' => $manager->id]);

        $this->withAuth($manager)
            ->delete('/companies/' . $company->id, ['_token' => 'test'])
            ->assertRedirect('/companies');

        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }
}
