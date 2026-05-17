<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebContactControllerTest extends TestCase
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
            'email'    => 'user' . $counter . '@test.com',
            'password' => bcrypt('password'),
            'role'     => $role,
        ]);
    }

    public function test_index_shows_contacts(): void
    {
        $admin = $this->makeUser();
        Contact::create(['first_name' => 'Alice', 'email' => 'alice@test.com']);

        $response = $this->withAuth($admin)->get('/contacts');
        $response->assertStatus(200)->assertSee('Alice');
    }

    public function test_create_page_loads(): void
    {
        $admin = $this->makeUser();
        $response = $this->withAuth($admin)->get('/contacts/create');
        $response->assertStatus(200)->assertSee('Nouveau contact');
    }

    public function test_store_creates_contact_and_redirects(): void
    {
        $admin = $this->makeUser();

        $response = $this->withAuth($admin)->post('/contacts', [
            'first_name' => 'Bob',
            'last_name'  => 'Martin',
            'email'      => 'bob@test.com',
            '_token'     => 'test',
        ]);

        $contact = Contact::where('email', 'bob@test.com')->first();
        $this->assertNotNull($contact);
        $response->assertRedirect('/contacts/' . $contact->id);
    }

    public function test_store_fails_without_first_name(): void
    {
        $admin = $this->makeUser();

        $response = $this->withAuth($admin)->post('/contacts', [
            'email'  => 'no-name@test.com',
            '_token' => 'test',
        ]);

        $response->assertSessionHasErrors('first_name');
        $this->assertDatabaseMissing('contacts', ['email' => 'no-name@test.com']);
    }

    public function test_show_renders_contact(): void
    {
        $admin   = $this->makeUser();
        $contact = Contact::create(['first_name' => 'Carol', 'email' => 'carol@test.com']);

        $response = $this->withAuth($admin)->get('/contacts/' . $contact->id);
        $response->assertStatus(200)->assertSee('Carol');
    }

    public function test_edit_page_loads(): void
    {
        $admin   = $this->makeUser();
        $contact = Contact::create(['first_name' => 'Dan', 'email' => 'dan@test.com']);

        $response = $this->withAuth($admin)->get('/contacts/' . $contact->id . '/edit');
        $response->assertStatus(200)->assertSee('Modifier le contact');
    }

    public function test_update_modifies_contact(): void
    {
        $admin   = $this->makeUser();
        $contact = Contact::create(['first_name' => 'Eve', 'email' => 'eve@test.com']);

        $response = $this->withAuth($admin)->put('/contacts/' . $contact->id, [
            'first_name' => 'Eva',
            '_token'     => 'test',
        ]);

        $response->assertRedirect('/contacts/' . $contact->id);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'first_name' => 'Eva']);
    }

    public function test_destroy_soft_deletes_contact(): void
    {
        $admin   = $this->makeUser();
        $contact = Contact::create(['first_name' => 'Frank', 'email' => 'frank@test.com']);

        $response = $this->withAuth($admin)->delete('/contacts/' . $contact->id, ['_token' => 'test']);

        $response->assertRedirect('/contacts');
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    public function test_viewer_cannot_delete_contact(): void
    {
        $viewer  = $this->makeUser(User::ROLE_SALES);
        $contact = Contact::create(['first_name' => 'Grace', 'email' => 'grace@test.com']);

        $response = $this->withAuth($viewer)->delete('/contacts/' . $contact->id, ['_token' => 'test']);

        $response->assertStatus(403);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'deleted_at' => null]);
    }
}
