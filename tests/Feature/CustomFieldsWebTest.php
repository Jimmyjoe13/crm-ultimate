<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\CustomField;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CustomFieldsWebTest extends TestCase
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

        return User::create([
            'name'     => 'Admin ' . $counter,
            'email'    => 'admin' . $counter . '@cf.test',
            'password' => bcrypt('password'),
            'role'     => User::ROLE_ADMIN,
        ]);
    }

    public function test_admin_can_create_custom_field(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->withAuth($admin)->post('/settings/fields', [
            'entity_type' => 'contact',
            'label'       => 'Budget',
            'key'         => 'budget',
            'field_type'  => 'number',
            '_token'      => 'test',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('custom_fields', ['key' => 'budget', 'entity_type' => 'contact']);
    }

    public function test_contact_create_form_shows_custom_field(): void
    {
        $admin = $this->makeAdmin();
        CustomField::create([
            'entity_type' => 'contact',
            'label'       => 'Budget',
            'key'         => 'budget',
            'field_type'  => 'number',
            'is_required' => false,
            'position'    => 0,
        ]);

        Cache::forget('custom_fields.contact');

        $response = $this->withAuth($admin)->get('/contacts/create');

        $response->assertStatus(200);
        $response->assertSee('Budget');
        $response->assertSee('custom_values[budget]');
    }

    public function test_custom_value_persisted_on_contact_create(): void
    {
        $admin = $this->makeAdmin();
        CustomField::create([
            'entity_type' => 'contact',
            'label'       => 'Budget',
            'key'         => 'budget',
            'field_type'  => 'number',
            'is_required' => false,
            'position'    => 0,
        ]);

        Cache::forget('custom_fields.contact');

        $this->withAuth($admin)->post('/contacts', [
            'first_name'    => 'Test',
            'email'         => 'cftest@example.com',
            'custom_values' => ['budget' => '5000'],
            '_token'        => 'test',
        ]);

        $contact = Contact::where('email', 'cftest@example.com')->first();
        $this->assertNotNull($contact);
        $this->assertEquals(5000.0, $contact->custom_values['budget'] ?? null);
    }

    public function test_custom_value_shown_in_edit_form(): void
    {
        $admin = $this->makeAdmin();
        CustomField::create([
            'entity_type' => 'contact',
            'label'       => 'Budget',
            'key'         => 'budget',
            'field_type'  => 'number',
            'is_required' => false,
            'position'    => 0,
        ]);
        Cache::forget('custom_fields.contact');

        $contact = Contact::create([
            'first_name'    => 'Test',
            'email'         => 'cfshow@example.com',
            'custom_values' => ['budget' => '3000'],
        ]);

        $response = $this->withAuth($admin)->get('/contacts/' . $contact->id . '/edit');

        $response->assertStatus(200);
        $response->assertSee('3000');
    }

    public function test_admin_can_update_custom_field(): void
    {
        $admin = $this->makeAdmin();
        $field = CustomField::create([
            'entity_type' => 'contact',
            'label'       => 'Budget',
            'key'         => 'budget',
            'field_type'  => 'number',
            'is_required' => false,
            'position'    => 0,
        ]);

        $response = $this->withAuth($admin)->patch('/settings/fields/' . $field->id, [
            'label'      => 'Budget annuel',
            'field_type' => 'number',
            '_token'     => 'test',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('custom_fields', ['id' => $field->id, 'label' => 'Budget annuel']);
    }

    public function test_admin_can_delete_custom_field(): void
    {
        $admin = $this->makeAdmin();
        $field = CustomField::create([
            'entity_type' => 'contact',
            'label'       => 'Budget',
            'key'         => 'budget',
            'field_type'  => 'number',
            'is_required' => false,
            'position'    => 0,
        ]);

        $response = $this->withAuth($admin)->delete('/settings/fields/' . $field->id, [
            '_token' => 'test',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('custom_fields', ['id' => $field->id]);
    }

    public function test_boolean_custom_value_can_be_updated(): void
    {
        $admin = $this->makeAdmin();
        $field = CustomField::create([
            'entity_type' => 'contact',
            'label'       => 'Blacklist',
            'key'         => 'blacklist',
            'field_type'  => 'boolean',
            'is_required' => false,
            'position'    => 0,
        ]);
        Cache::forget('custom_fields.contact');

        $contact = Contact::create([
            'first_name'    => 'Test',
            'email'         => 'cfboolean@example.com',
            'custom_values' => ['blacklist' => true],
        ]);

        $response = $this->withAuth($admin)->put('/contacts/' . $contact->id, [
            'first_name'    => 'Test',
            'custom_values' => ['blacklist' => '0'],
            '_token'        => 'test',
        ]);

        $response->assertRedirect();
        $contact->refresh();
        $this->assertFalse($contact->custom_values['blacklist'] ?? null);
    }
}
