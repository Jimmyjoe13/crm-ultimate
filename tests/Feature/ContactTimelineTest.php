<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTimelineTest extends TestCase
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

    private function makeUser(): User
    {
        static $counter = 0;
        $counter++;

        return User::createWithRole([
            'name'     => 'User ' . $counter,
            'email'    => 'user' . $counter . '@timeline.test',
            'password' => bcrypt('password'),
            'role'     => User::ROLE_ADMIN,
        ]);
    }

    public function test_contact_show_renders_activity_tab(): void
    {
        $user    = $this->makeUser();
        $contact = Contact::create(['first_name' => 'Test', 'email' => 'tl@test.com']);

        $response = $this->withAuth($user)->get('/contacts/' . $contact->id);

        $response->assertStatus(200);
        $response->assertSee('Activité');
    }

    public function test_store_activity_for_contact(): void
    {
        $user    = $this->makeUser();
        $contact = Contact::create(['first_name' => 'Test', 'email' => 'tl2@test.com']);

        $response = $this->withAuth($user)->post('/activities', [
            'type'         => 'note',
            'title'        => 'Appel découverte',
            'body'         => 'RAS',
            'subject_type' => 'contact',
            'subject_id'   => $contact->id,
            '_token'       => 'test',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('activities', [
            'title'        => 'Appel découverte',
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
        ]);
    }

    public function test_activity_appears_in_contact_show(): void
    {
        $user    = $this->makeUser();
        $contact = Contact::create(['first_name' => 'Test', 'email' => 'tl3@test.com']);

        Activity::create([
            'type'         => 'note',
            'title'        => 'Note timeline',
            'status'       => 'open',
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'owner_id'     => $user->id,
        ]);

        $response = $this->withAuth($user)->get('/contacts/' . $contact->id);

        $response->assertStatus(200);
        $response->assertSee('Note timeline');
    }
}
