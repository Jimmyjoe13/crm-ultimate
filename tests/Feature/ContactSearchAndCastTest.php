<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use App\Services\JwtService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactSearchAndCastTest extends TestCase
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
        return User::create([
            'name'     => 'Admin',
            'email'    => 'admin@casttest.dev',
            'password' => bcrypt('secret'),
            'role'     => User::ROLE_ADMIN,
        ]);
    }

    /** ai_score_updated_at doit être casté en Carbon, pas en string. */
    public function test_ai_score_updated_at_is_cast_to_carbon(): void
    {
        $contact = Contact::create([
            'first_name'          => 'Jean',
            'email'               => 'jean@cast.test',
            'ai_score'            => 75,
            'ai_score_updated_at' => now(),
        ]);

        $contact->refresh();

        $this->assertInstanceOf(Carbon::class, $contact->ai_score_updated_at);
        $this->assertIsString($contact->ai_score_updated_at->diffForHumans());
    }

    /** La page liste contacts doit retourner 200 même si des contacts ont un ai_score_updated_at. */
    public function test_contacts_index_returns_200_with_ai_scored_contacts(): void
    {
        $user = $this->makeAdmin();

        Contact::create([
            'first_name'          => 'Marie',
            'email'               => 'marie@scored.test',
            'ai_score'            => 82,
            'ai_score_updated_at' => now()->subHour(),
        ]);

        $response = $this->withAuth($user)->get('/contacts');

        $response->assertStatus(200);
    }

    /** La recherche par nom doit retourner 200 et afficher les résultats correspondants. */
    public function test_contact_search_returns_200_and_matching_contacts(): void
    {
        $user = $this->makeAdmin();

        Contact::create(['first_name' => 'Pierre', 'last_name' => 'Dupont', 'email' => 'pierre@search.test']);
        Contact::create(['first_name' => 'Sophie', 'last_name' => 'Martin', 'email' => 'sophie@search.test']);

        $response = $this->withAuth($user)->get('/contacts?search=Pierre');

        $response->assertStatus(200);
        $response->assertSee('Pierre');
        $response->assertDontSee('Sophie');
    }

    /** La recherche avec un contact ayant un ai_score_updated_at ne doit pas provoquer de 500. */
    public function test_contact_search_no_500_with_ai_score(): void
    {
        $user = $this->makeAdmin();

        Contact::create([
            'first_name'          => 'Lucas',
            'email'               => 'lucas@aiscore.test',
            'ai_score'            => 60,
            'ai_score_updated_at' => now()->subDays(2),
        ]);

        $response = $this->withAuth($user)->get('/contacts?search=Lucas');

        $response->assertStatus(200);
        $response->assertSee('Lucas');
    }

    /** blacklisted_at doit aussi être casté en Carbon. */
    public function test_blacklisted_at_is_cast_to_carbon(): void
    {
        $contact = Contact::create([
            'first_name' => 'Stop',
            'email'      => 'stop@blacklist.test',
        ]);

        $contact->blacklist('Test raison');
        $contact->refresh();

        $this->assertInstanceOf(Carbon::class, $contact->blacklisted_at);
    }
}
