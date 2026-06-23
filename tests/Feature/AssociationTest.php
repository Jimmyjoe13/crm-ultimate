<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AssociationTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private function makeDealAndContact(): array
    {
        $pipeline = Pipeline::query()->create(['name' => 'Test Pipeline', 'is_default' => true]);
        $stage = $pipeline->stages()->create(['name' => 'Q', 'position' => 10, 'probability' => 30]);
        $deal = Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);
        $contact = Contact::factory()->create();

        return [$deal, $contact];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::createWithRole([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $this->token = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('access_token');
    }

    // ── contact ↔ company ──────────────────────────────────────────────────

    public function test_attach_contact_to_company(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->create();

        $this->withToken($this->token)
            ->postJson("/api/v1/companies/{$company->id}/contacts", [
                'contact_id' => $contact->id,
                'role' => 'decision_maker',
                'is_primary' => true,
            ])
            ->assertOk()
            ->assertJsonFragment(['id' => $contact->id]);

        $this->assertDatabaseHas('contact_company', [
            'contact_id' => $contact->id,
            'company_id' => $company->id,
            'role' => 'decision_maker',
            'is_primary' => true,
        ]);
    }

    public function test_attach_company_to_contact_via_contact_endpoint(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->create();

        $this->withToken($this->token)
            ->postJson("/api/v1/contacts/{$contact->id}/companies", [
                'company_id' => $company->id,
                'role' => 'employee',
                'is_primary' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('contact_company', [
            'contact_id' => $contact->id,
            'company_id' => $company->id,
            'is_primary' => true,
        ]);
    }

    public function test_update_contact_company_pivot_role(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->create();

        $company->contacts()->attach($contact->id, ['role' => 'employee', 'is_primary' => false]);

        $this->withToken($this->token)
            ->patchJson("/api/v1/companies/{$company->id}/contacts/{$contact->id}", [
                'role' => 'influencer',
                'is_primary' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('contact_company', [
            'contact_id' => $contact->id,
            'company_id' => $company->id,
            'role' => 'influencer',
            'is_primary' => true,
        ]);
    }

    public function test_detach_contact_from_company(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->create();

        $company->contacts()->attach($contact->id, ['role' => 'employee', 'is_primary' => true]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/companies/{$company->id}/contacts/{$contact->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('contact_company', [
            'contact_id' => $contact->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_attach_creates_audit_log(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->create();

        $this->withToken($this->token)
            ->postJson("/api/v1/companies/{$company->id}/contacts", [
                'contact_id' => $contact->id,
                'role' => 'employee',
            ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'associated',
            'auditable_type' => Company::class,
            'auditable_id' => $company->id,
        ]);
    }

    public function test_detach_creates_audit_log(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->create();

        $company->contacts()->attach($contact->id, ['role' => 'employee', 'is_primary' => false]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/companies/{$company->id}/contacts/{$contact->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'dissociated',
            'auditable_type' => Company::class,
            'auditable_id' => $company->id,
        ]);
    }

    // ── deal ↔ contact ─────────────────────────────────────────────────────

    public function test_attach_contact_to_deal(): void
    {
        [$deal, $contact] = $this->makeDealAndContact();

        $this->withToken($this->token)
            ->postJson("/api/v1/deals/{$deal->id}/contacts", [
                'contact_id' => $contact->id,
                'role' => 'technical',
            ])
            ->assertOk()
            ->assertJsonFragment(['id' => $contact->id]);

        $this->assertDatabaseHas('deal_contact', [
            'deal_id' => $deal->id,
            'contact_id' => $contact->id,
            'role' => 'technical',
        ]);
    }

    public function test_detach_contact_from_deal(): void
    {
        [$deal, $contact] = $this->makeDealAndContact();

        $deal->contacts()->attach($contact->id, ['role' => 'primary']);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/deals/{$deal->id}/contacts/{$contact->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('deal_contact', [
            'deal_id' => $deal->id,
            'contact_id' => $contact->id,
        ]);
    }

    // ── deal ↔ company ─────────────────────────────────────────────────────

    public function test_attach_company_to_deal_with_is_primary(): void
    {
        [$deal] = $this->makeDealAndContact();
        $company = Company::factory()->create();

        $this->withToken($this->token)
            ->postJson("/api/v1/deals/{$deal->id}/companies", [
                'company_id' => $company->id,
                'role' => 'customer',
                'is_primary' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('deal_company', [
            'deal_id' => $deal->id,
            'company_id' => $company->id,
            'role' => 'customer',
            'is_primary' => true,
        ]);
    }

    public function test_contact_show_returns_companies_array(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->create();

        $contact->companies()->attach($company->id, ['role' => 'employee', 'is_primary' => true]);

        $this->withToken($this->token)
            ->getJson("/api/v1/contacts/{$contact->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'first_name', 'companies']])
            ->assertJsonCount(1, 'data.companies');
    }

    public function test_deal_show_returns_companies_and_contacts_arrays(): void
    {
        [$deal, $contact] = $this->makeDealAndContact();
        $company = Company::factory()->create();

        $deal->companies()->attach($company->id, ['role' => 'customer', 'is_primary' => true]);
        $deal->contacts()->attach($contact->id, ['role' => 'primary']);

        $this->withToken($this->token)
            ->getJson("/api/v1/deals/{$deal->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'companies', 'contacts']])
            ->assertJsonCount(1, 'data.companies')
            ->assertJsonCount(1, 'data.contacts');
    }
}
