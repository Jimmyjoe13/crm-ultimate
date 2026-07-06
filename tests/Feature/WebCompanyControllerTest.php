<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebCompanyControllerTest extends TestCase
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
            'email' => 'user'.$counter.'@test.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    public function test_index_shows_companies(): void
    {
        $admin = $this->makeUser();
        Company::create(['name' => 'Acme Corp']);

        $response = $this->withAuth($admin)->get('/companies');
        $response->assertStatus(200)->assertSee('Acme Corp');
    }

    public function test_create_page_loads(): void
    {
        $admin = $this->makeUser();
        $response = $this->withAuth($admin)->get('/companies/create');
        $response->assertStatus(200)->assertSee('Nouvelle entreprise');
    }

    public function test_store_creates_company_and_redirects(): void
    {
        $admin = $this->makeUser();

        $response = $this->withAuth($admin)->post('/companies', [
            'name' => 'StartupX',
            '_token' => 'test',
        ]);

        $company = Company::where('name', 'StartupX')->first();
        $this->assertNotNull($company);
        $response->assertRedirect('/companies/'.$company->id);
    }

    public function test_store_fails_without_name(): void
    {
        $admin = $this->makeUser();

        $response = $this->withAuth($admin)->post('/companies', [
            'domain' => 'nope.com',
            '_token' => 'test',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_show_renders_company(): void
    {
        $admin = $this->makeUser();
        $company = Company::create(['name' => 'BigCo']);

        $response = $this->withAuth($admin)->get('/companies/'.$company->id);
        $response->assertStatus(200)->assertSee('BigCo');
    }

    public function test_edit_page_loads(): void
    {
        $admin = $this->makeUser();
        $company = Company::create(['name' => 'OldName']);

        $response = $this->withAuth($admin)->get('/companies/'.$company->id.'/edit');
        $response->assertStatus(200)->assertSeeText("Modifier l'entreprise");
    }

    public function test_update_modifies_company(): void
    {
        $admin = $this->makeUser();
        $company = Company::create(['name' => 'OldName']);

        $response = $this->withAuth($admin)->put('/companies/'.$company->id, [
            'name' => 'NewName',
            '_token' => 'test',
        ]);

        $response->assertRedirect('/companies/'.$company->id);
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'NewName']);
    }

    public function test_destroy_soft_deletes_company(): void
    {
        $admin = $this->makeUser();
        $company = Company::create(['name' => 'ToDelete']);

        $response = $this->withAuth($admin)->delete('/companies/'.$company->id, ['_token' => 'test']);

        $response->assertRedirect('/companies');
        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }

    public function test_viewer_cannot_delete_company(): void
    {
        $viewer = $this->makeUser(User::ROLE_SALES);
        $company = Company::create(['name' => 'Protected']);

        $response = $this->withAuth($viewer)->delete('/companies/'.$company->id, ['_token' => 'test']);

        $response->assertStatus(403);
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'deleted_at' => null]);
    }
}
