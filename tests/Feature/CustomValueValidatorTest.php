<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\User;
use App\Services\JwtService;
use App\Support\CustomValueValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CustomValueValidatorTest extends TestCase
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

    private function makeAdmin(): User
    {
        static $counter = 0;
        $counter++;

        return User::createWithRole([
            'name' => 'Admin '.$counter,
            'email' => 'admin'.$counter.'@cvv.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makeField(array $attrs): CustomField
    {
        Cache::forget('custom_fields.'.$attrs['entity_type']);

        return CustomField::create(array_merge([
            'is_required' => false,
            'position' => 0,
            'options' => null,
        ], $attrs));
    }

    // ── cast: number ─────────────────────────────────────────────────────────

    public function test_cast_number_string_to_float(): void
    {
        $this->makeField(['entity_type' => 'contact', 'key' => 'budget', 'label' => 'Budget', 'field_type' => 'number']);

        $result = CustomValueValidator::cast('contact', ['budget' => '5000']);

        $this->assertSame(5000.0, $result['budget']);
    }

    public function test_cast_number_decimal_string(): void
    {
        $this->makeField(['entity_type' => 'contact', 'key' => 'rate', 'label' => 'Rate', 'field_type' => 'number']);

        $result = CustomValueValidator::cast('contact', ['rate' => '12.75']);

        $this->assertSame(12.75, $result['rate']);
    }

    // ── cast: date ───────────────────────────────────────────────────────────

    public function test_cast_date_normalises_to_y_m_d(): void
    {
        $this->makeField(['entity_type' => 'contact', 'key' => 'birth', 'label' => 'Naissance', 'field_type' => 'date']);

        $result = CustomValueValidator::cast('contact', ['birth' => '2024-06-15']);

        $this->assertSame('2024-06-15', $result['birth']);
    }

    // ── cast: boolean ────────────────────────────────────────────────────────

    public function test_cast_boolean_one_to_true(): void
    {
        $this->makeField(['entity_type' => 'contact', 'key' => 'vip', 'label' => 'VIP', 'field_type' => 'boolean']);

        $result = CustomValueValidator::cast('contact', ['vip' => '1']);

        $this->assertTrue($result['vip']);
    }

    public function test_cast_boolean_zero_to_false(): void
    {
        $this->makeField(['entity_type' => 'contact', 'key' => 'vip', 'label' => 'VIP', 'field_type' => 'boolean']);

        $result = CustomValueValidator::cast('contact', ['vip' => '0']);

        $this->assertFalse($result['vip']);
    }

    public function test_cast_boolean_legacy_oui_to_true(): void
    {
        $this->makeField(['entity_type' => 'contact', 'key' => 'vip', 'label' => 'VIP', 'field_type' => 'boolean']);

        $result = CustomValueValidator::cast('contact', ['vip' => 'oui']);

        $this->assertTrue($result['vip']);
    }

    public function test_cast_boolean_legacy_non_to_false(): void
    {
        $this->makeField(['entity_type' => 'contact', 'key' => 'vip', 'label' => 'VIP', 'field_type' => 'boolean']);

        $result = CustomValueValidator::cast('contact', ['vip' => 'non']);

        $this->assertFalse($result['vip']);
    }

    // ── cast: text ───────────────────────────────────────────────────────────

    public function test_cast_text_trims_whitespace(): void
    {
        $this->makeField(['entity_type' => 'contact', 'key' => 'note', 'label' => 'Note', 'field_type' => 'text']);

        $result = CustomValueValidator::cast('contact', ['note' => '  hello  ']);

        $this->assertSame('hello', $result['note']);
    }

    // ── cast: empty / null ───────────────────────────────────────────────────

    public function test_cast_empty_string_becomes_null(): void
    {
        $this->makeField(['entity_type' => 'contact', 'key' => 'budget', 'label' => 'Budget', 'field_type' => 'number']);

        $result = CustomValueValidator::cast('contact', ['budget' => '']);

        $this->assertNull($result['budget']);
    }

    public function test_cast_null_stays_null(): void
    {
        $this->makeField(['entity_type' => 'contact', 'key' => 'budget', 'label' => 'Budget', 'field_type' => 'number']);

        $result = CustomValueValidator::cast('contact', ['budget' => null]);

        $this->assertNull($result['budget']);
    }

    // ── cast: unknown keys dropped ───────────────────────────────────────────

    public function test_cast_drops_unknown_keys(): void
    {
        $this->makeField(['entity_type' => 'contact', 'key' => 'budget', 'label' => 'Budget', 'field_type' => 'number']);

        $result = CustomValueValidator::cast('contact', [
            'budget' => '100',
            'unknown' => 'injected',
        ]);

        $this->assertArrayNotHasKey('unknown', $result);
        $this->assertSame(100.0, $result['budget']);
    }

    // ── validationRules ──────────────────────────────────────────────────────

    public function test_validation_rules_includes_per_field_rules(): void
    {
        $this->makeField(['entity_type' => 'contact', 'key' => 'budget', 'label' => 'Budget', 'field_type' => 'number']);
        $this->makeField(['entity_type' => 'contact', 'key' => 'active', 'label' => 'Actif', 'field_type' => 'boolean']);

        $rules = CustomValueValidator::validationRules('contact');

        $this->assertArrayHasKey('custom_values', $rules);
        $this->assertArrayHasKey('custom_values.budget', $rules);
        $this->assertArrayHasKey('custom_values.active', $rules);
        $this->assertContains('numeric', $rules['custom_values.budget']);
        $this->assertContains('in:0,1', $rules['custom_values.active']);
    }

    public function test_required_field_rule_has_required(): void
    {
        $this->makeField(['entity_type' => 'contact', 'key' => 'code', 'label' => 'Code', 'field_type' => 'text', 'is_required' => true]);

        $rules = CustomValueValidator::validationRules('contact');

        $this->assertContains('required', $rules['custom_values.code']);
    }

    public function test_validation_fails_non_numeric_for_number_field(): void
    {
        $admin = $this->makeAdmin();
        $this->makeField(['entity_type' => 'contact', 'key' => 'budget', 'label' => 'Budget', 'field_type' => 'number']);

        $response = $this->withAuth($admin)->post('/contacts', [
            'first_name' => 'Test',
            'custom_values' => ['budget' => 'not-a-number'],
            '_token' => 'test',
        ]);

        $response->assertSessionHasErrors('custom_values.budget');
    }

    public function test_validation_fails_invalid_date_for_date_field(): void
    {
        $admin = $this->makeAdmin();
        $this->makeField(['entity_type' => 'contact', 'key' => 'birth', 'label' => 'Naissance', 'field_type' => 'date']);

        $response = $this->withAuth($admin)->post('/contacts', [
            'first_name' => 'Test',
            'custom_values' => ['birth' => 'not-a-date'],
            '_token' => 'test',
        ]);

        $response->assertSessionHasErrors('custom_values.birth');
    }

    // ── Integration: stored as proper types ──────────────────────────────────

    public function test_number_stored_as_float_on_contact_create(): void
    {
        $admin = $this->makeAdmin();
        $this->makeField(['entity_type' => 'contact', 'key' => 'budget', 'label' => 'Budget', 'field_type' => 'number']);

        $this->withAuth($admin)->post('/contacts', [
            'first_name' => 'Typed',
            'email' => 'typed@test.com',
            'custom_values' => ['budget' => '7500'],
            '_token' => 'test',
        ]);

        $contact = Contact::where('email', 'typed@test.com')->first();
        $this->assertEquals(7500.0, $contact->custom_values['budget']);
    }

    public function test_boolean_stored_as_bool_on_contact_create(): void
    {
        $admin = $this->makeAdmin();
        $this->makeField(['entity_type' => 'contact', 'key' => 'vip', 'label' => 'VIP', 'field_type' => 'boolean']);

        $this->withAuth($admin)->post('/contacts', [
            'first_name' => 'VipUser',
            'email' => 'vip@test.com',
            'custom_values' => ['vip' => '1'],
            '_token' => 'test',
        ]);

        $contact = Contact::where('email', 'vip@test.com')->first();
        $this->assertTrue($contact->custom_values['vip']);
    }

    public function test_number_stored_as_float_on_company_update(): void
    {
        $admin = $this->makeAdmin();
        $this->makeField(['entity_type' => 'company', 'key' => 'employees', 'label' => 'Effectif', 'field_type' => 'number']);

        $company = Company::create(['name' => 'Acme']);

        $this->withAuth($admin)->put('/companies/'.$company->id, [
            'name' => 'Acme',
            'custom_values' => ['employees' => '250'],
            '_token' => 'test',
        ]);

        $this->assertEquals(250.0, $company->fresh()->custom_values['employees']);
    }

    public function test_boolean_true_persists_on_contact_update(): void
    {
        $admin = $this->makeAdmin();
        $this->makeField(['entity_type' => 'contact', 'key' => 'blacklist', 'label' => 'Blacklist', 'field_type' => 'boolean']);

        $contact = Contact::create(['first_name' => 'Test', 'email' => 'bl@test.com']);

        $this->withAuth($admin)->put('/contacts/'.$contact->id, [
            'first_name' => 'Test',
            'custom_values' => ['blacklist' => '1'],
            '_token' => 'test',
        ]);

        $this->assertTrue($contact->fresh()->custom_values['blacklist']);
    }

    public function test_boolean_false_persists_on_contact_update(): void
    {
        $admin = $this->makeAdmin();
        $this->makeField(['entity_type' => 'contact', 'key' => 'blacklist', 'label' => 'Blacklist', 'field_type' => 'boolean']);

        $contact = Contact::create([
            'first_name' => 'Test',
            'email' => 'bl2@test.com',
            'custom_values' => ['blacklist' => true],
        ]);

        $this->withAuth($admin)->put('/contacts/'.$contact->id, [
            'first_name' => 'Test',
            'custom_values' => ['blacklist' => '0'],
            '_token' => 'test',
        ]);

        $this->assertFalse($contact->fresh()->custom_values['blacklist']);
    }

    public function test_partial_update_preserves_existing_custom_values(): void
    {
        $admin = $this->makeAdmin();
        $this->makeField(['entity_type' => 'contact', 'key' => 'blacklist', 'label' => 'Blacklist', 'field_type' => 'boolean']);

        $contact = Contact::create([
            'first_name' => 'Test',
            'email' => 'bl3@test.com',
            'custom_values' => ['blacklist' => true],
        ]);

        // Mise à jour partielle sans custom_values (ex: dropdown lifecycle_stage)
        $this->withAuth($admin)->put('/contacts/'.$contact->id, [
            'lifecycle_stage' => 'customer',
            '_token' => 'test',
        ]);

        $this->assertTrue($contact->fresh()->custom_values['blacklist']);
    }
}
