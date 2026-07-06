<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function withAuth(User $user): static
    {
        $jwt = app(JwtService::class)->encode([
            'sub' => $user->id,
            'exp' => time() + 3600,
        ]);

        return $this->withCookies(['crm_jwt' => $jwt])->withSession(['_token' => 'test']);
    }

    private function makeUser(string $role = User::ROLE_SALES): User
    {
        static $counter = 0;
        $counter++;

        return User::createWithRole([
            'name' => 'User '.$counter,
            'email' => 'user'.$counter.'@tpl.test',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    public function test_user_can_create_template(): void
    {
        $user = $this->makeUser();

        $this->withAuth($user)->post('/email-templates', [
            '_token' => 'test',
            'name' => 'Relance J+3',
            'subject' => 'Bonjour {{first_name}}',
            'body' => 'Un mot à propos de {{company}}.',
            'category' => 'Relance',
        ])->assertRedirect();

        $this->assertDatabaseHas('email_templates', [
            'name' => 'Relance J+3',
            'owner_id' => $user->id,
            'is_shared' => false,
        ]);
    }

    public function test_commercial_cannot_edit_foreign_template(): void
    {
        $owner = $this->makeUser(User::ROLE_SALES);
        $attacker = $this->makeUser(User::ROLE_SALES);

        $template = EmailTemplate::factory()->create(['owner_id' => $owner->id, 'name' => 'Privé']);

        // L'attaquant tente d'éditer → 404 (cloisonnement owner).
        $this->withAuth($attacker)->put('/email-templates/'.$template->id, [
            '_token' => 'test',
            'name' => 'Piraté',
        ])->assertNotFound();

        $this->assertDatabaseHas('email_templates', ['id' => $template->id, 'name' => 'Privé']);
    }

    public function test_shared_template_is_visible_to_other_user(): void
    {
        $owner = $this->makeUser(User::ROLE_SALES);
        $other = $this->makeUser(User::ROLE_SALES);

        EmailTemplate::factory()->create(['owner_id' => $owner->id, 'name' => 'Privé du owner']);
        EmailTemplate::factory()->shared()->create(['owner_id' => $owner->id, 'name' => 'Partagé équipe']);

        $resp = $this->withAuth($other)->get('/email-templates/options', ['Accept' => 'application/json']);
        $resp->assertOk();

        $names = collect($resp->json())->pluck('name');
        $this->assertContains('Partagé équipe', $names);       // partagé → visible
        $this->assertNotContains('Privé du owner', $names);    // privé d'un autre → masqué
    }

    public function test_render_substitutes_contact_variables(): void
    {
        $user = $this->makeUser(User::ROLE_SALES);

        $contact = Contact::create([
            'first_name' => 'Marie',
            'last_name' => 'Curie',
            'email' => 'marie@labo.test',
            'owner_id' => $user->id,
        ]);

        $template = EmailTemplate::factory()->create([
            'owner_id' => $user->id,
            'subject' => 'Bonjour {{first_name}}',
            'body' => '{{full_name}} — {{unknown_var}}',
        ]);

        $resp = $this->withAuth($user)->post('/email-templates/'.$template->id.'/render', [
            '_token' => 'test',
            'contact_id' => $contact->id,
        ], ['Accept' => 'application/json']);

        $resp->assertOk();
        $resp->assertJsonPath('subject', 'Bonjour Marie');
        // Variable connue substituée, variable inconnue laissée telle quelle.
        $resp->assertJsonPath('body', 'Marie Curie — {{unknown_var}}');
    }

    public function test_commercial_cannot_publish_shared_template(): void
    {
        $user = $this->makeUser(User::ROLE_SALES);

        $this->withAuth($user)->post('/email-templates', [
            '_token' => 'test',
            'name' => 'Tentative partage',
            'is_shared' => '1',
        ])->assertRedirect();

        // is_shared forcé à false pour un commercial.
        $this->assertDatabaseHas('email_templates', [
            'name' => 'Tentative partage',
            'is_shared' => false,
        ]);
    }

    public function test_admin_can_publish_shared_template(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);

        $this->withAuth($admin)->post('/email-templates', [
            '_token' => 'test',
            'name' => 'Modèle équipe',
            'is_shared' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('email_templates', [
            'name' => 'Modèle équipe',
            'is_shared' => true,
        ]);
    }
}
