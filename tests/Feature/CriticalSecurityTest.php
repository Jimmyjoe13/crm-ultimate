<?php

namespace Tests\Feature;

use App\Models\ExportJob;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CriticalSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function withAuth(User $user): static
    {
        $jwt = app(JwtService::class)->encode([
            'sub' => $user->id,
            'role' => $user->role,
            'exp' => time() + 3600,
        ]);

        // Correction : ces tests appellent l'API stateless. Le JWT doit passer en
        // header Bearer (lu en priorité par JwtMiddleware). Le cookie `crm_jwt` est
        // attendu CHIFFRÉ (Crypt::decrypt) côté middleware ; y mettre un JWT brut
        // échouait silencieusement → toutes les requêtes renvoyaient 401.
        return $this->withToken($jwt);
    }

    private function makeUser(string $role): User
    {
        // Correction : email rendu unique. L'ancienne version utilisait un email fixe
        // par rôle, ce qui provoquait une UniqueConstraintViolation dès qu'un test
        // créait deux utilisateurs du même rôle (ex. deux commerciaux).
        static $seq = 0;
        $seq++;

        return User::create([
            'name' => ucfirst($role),
            'email' => "{$role}-{$seq}-critical@example.test",
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    public function test_login_route_is_throttled(): void
    {
        User::create([
            'name' => 'Login Target',
            'email' => 'login-target@example.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'login-target@example.test',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'login-target@example.test',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_emelia_webhooks_require_signature_when_secret_configured(): void
    {
        Config::set('services.emelia.webhook_secret', 'secret');

        $this->postJson('/api/webhooks/emelia', [
            'event_id' => 'evt_1',
            'event' => 'opened',
            'contact_id' => 'emelia_contact',
            'email' => 'lead@example.test',
        ])->assertStatus(401);

        $this->postJson('/api/webhooks/emelia-intent', [
            'event_id' => 'evt_2',
            'intent' => 'stop',
            'emelia_contact_id' => 'emelia_contact',
            'email' => 'lead@example.test',
        ])->assertStatus(401);

        $this->withHeaders([
            'X-Emelia-Signature' => 'bad-signature',
        ])->postJson('/api/webhooks/emelia', [
            'event_id' => 'evt_3',
            'event' => 'opened',
            'contact_id' => 'emelia_contact',
            'email' => 'lead@example.test',
        ])->assertStatus(401);
    }

    public function test_sales_user_cannot_assign_owner_to_another_user(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);
        $other = $this->makeUser(User::ROLE_SALES);

        $response = $this->withAuth($sales)->postJson('/api/v1/contacts', [
            'first_name' => 'Lead',
            'last_name' => 'Unassigned',
            'email' => 'lead-owner@example.test',
            'owner_id' => $other->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('contacts', ['email' => 'lead-owner@example.test']);
    }

    public function test_sales_user_can_assign_owner_to_themselves(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);

        $response = $this->withAuth($sales)->postJson('/api/v1/contacts', [
            'first_name' => 'Lead',
            'last_name' => 'Self',
            'email' => 'lead-self@example.test',
            'owner_id' => $sales->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('contacts', [
            'email' => 'lead-self@example.test',
            'owner_id' => $sales->id,
        ]);
    }

    public function test_manager_can_assign_owner_to_another_user(): void
    {
        $manager = $this->makeUser(User::ROLE_MANAGER);
        $sales = $this->makeUser(User::ROLE_SALES);

        $response = $this->withAuth($manager)->postJson('/api/v1/contacts', [
            'first_name' => 'Lead',
            'last_name' => 'ManagerAssigned',
            'email' => 'lead-manager@example.test',
            'owner_id' => $sales->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('contacts', [
            'email' => 'lead-manager@example.test',
            'owner_id' => $sales->id,
        ]);
    }

    public function test_export_routes_are_forbidden_to_sales(): void
    {
        // Les routes /exports sont sous middleware role:admin,manager.
        // Un commercial est donc rejeté en amont (403), avant tout scope owner.
        $sales = $this->makeUser(User::ROLE_SALES);

        $otherJob = ExportJob::create([
            'user_id' => $this->makeUser(User::ROLE_MANAGER)->id,
            'entity_type' => 'contact',
            'status' => 'completed',
            'filters' => [],
            'file_path' => 'exports/other.csv',
        ]);

        $this->withAuth($sales)->getJson("/api/v1/exports/{$otherJob->id}")->assertForbidden();
        $this->withAuth($sales)->getJson("/api/v1/exports/{$otherJob->id}/download")->assertForbidden();
        $this->withAuth($sales)->getJson('/api/v1/exports')->assertForbidden();
    }

    public function test_export_show_and_download_are_owner_scoped(): void
    {
        // Entre deux utilisateurs AUTORISÉS (managers), un export reste cloisonné par
        // user_id : l'export d'autrui renvoie 404 (ownedJobOrFail → findOrFail scopé).
        $manager = $this->makeUser(User::ROLE_MANAGER);
        $other   = $this->makeUser(User::ROLE_MANAGER);

        $otherJob = ExportJob::create([
            'user_id' => $other->id,
            'entity_type' => 'contact',
            'status' => 'completed',
            'filters' => [],
            'file_path' => 'exports/other.csv',
        ]);

        $this->withAuth($manager)
            ->getJson("/api/v1/exports/{$otherJob->id}")
            ->assertNotFound();

        $this->withAuth($manager)
            ->getJson("/api/v1/exports/{$otherJob->id}/download")
            ->assertNotFound();

        $this->withAuth($manager)
            ->getJson('/api/v1/exports')
            ->assertOk()
            ->assertJsonMissingPath('data.0.id');
    }

    public function test_contact_company_and_deal_are_created_with_owner_rules(): void
    {
        $sales = $this->makeUser(User::ROLE_SALES);

        $company = $this->withAuth($sales)->postJson('/api/v1/companies', [
            'name' => 'Company Self',
            'domain' => 'company-self.test',
            'owner_id' => $sales->id,
        ]);

        $company->assertCreated();

        $pipeline = Pipeline::create(['name' => 'Default', 'is_default' => true]);
        $stage = PipelineStage::create([
            'pipeline_id' => $pipeline->id,
            'name' => 'Prospecting',
            'position' => 1,
            'probability' => 20,
        ]);

        $deal = $this->withAuth($sales)->postJson('/api/v1/deals', [
            'name' => 'Deal Self',
            'amount' => 1000,
            'currency' => 'EUR',
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'owner_id' => $sales->id,
        ]);

        $deal->assertCreated();
    }
}
