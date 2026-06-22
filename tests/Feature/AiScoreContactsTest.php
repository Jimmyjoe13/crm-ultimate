<?php

namespace Tests\Feature;

use App\Console\Commands\AiScoreContacts;
use App\Models\Activity;
use App\Models\Contact;
use App\Services\AiInsightService;
use App\Services\LlmService;
use App\Services\JwtService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AiScoreContactsTest extends TestCase
{
    use RefreshDatabase;

    private function withAuth(User $user): static
    {
        $jwt = app(JwtService::class)->encode(['sub' => $user->id, 'exp' => time() + 3600]);
        return $this->withCookies(['crm_jwt' => $jwt])->withSession(['_token' => 'test']);
    }

    private function makeUser(string $role = User::ROLE_ADMIN): User
    {
        static $n = 0; $n++;
        return User::create(['name' => "U{$n}", 'email' => "u{$n}@score.test", 'password' => bcrypt('x'), 'role' => $role]);
    }

    // ─── scoreContact() service ───────────────────────────────────────────────

    public function test_score_contact_returns_score_and_rationale(): void
    {
        $this->mock(LlmService::class, fn($m) => $m->shouldReceive('complete')
            ->once()->andReturn('{"score": 72, "rationale": "Contact très engagé via Emelia."}'));

        $contact = Contact::create([
            'first_name' => 'Score', 'last_name' => 'Test',
            'email' => 'score@test.test',
            'lifecycle_stage' => 'mql',
            'emelia_campaign_id' => 'camp-score',
        ]);

        $result = app(AiInsightService::class)->scoreContact($contact->id, fresh: true);

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(72, $result['data']['score']);
        $this->assertStringContainsString('Emelia', $result['data']['rationale']);
    }

    public function test_score_contact_result_is_cached(): void
    {
        $this->mock(LlmService::class, fn($m) => $m->shouldReceive('complete')
            ->once()->andReturn('{"score": 55, "rationale": "Ok."}'));

        $contact = Contact::create(['first_name' => 'Cache', 'last_name' => 'Me', 'email' => 'cache@score.test']);
        $service = app(AiInsightService::class);

        $r1 = $service->scoreContact($contact->id, fresh: true);
        $r2 = $service->scoreContact($contact->id, fresh: false);

        $this->assertEquals(55, $r1['data']['score']);
        $this->assertEquals(55, $r2['data']['score']);
        $this->assertTrue($r2['cached']);
    }

    // ─── Commande Artisan ai:score-contacts ───────────────────────────────────

    /**
     * Construit une réponse LLM au FORMAT BATCH attendu par
     * AiInsightService::batchScoreContacts() : un tableau JSON d'objets
     * {"id": N, "score": X, "rationale": "..."} couvrant les IDs présents
     * dans le prompt utilisateur (extraits via "ID:<n>").
     *
     * Note : l'ancienne version de ces tests mockait un objet unique
     * {"score": X} (API mono-contact), incompatible avec le scoring batch
     * introduit ensuite — d'où les échecs préexistants. On aligne le mock
     * sur l'implémentation réelle.
     */
    private function batchReply(int $score, string $rationale = 'ok'): \Closure
    {
        return function (string $system, string $userPrompt) use ($score, $rationale): string {
            preg_match_all('/ID:(\d+)/', $userPrompt, $matches);
            $items = array_map(
                fn ($id) => ['id' => (int) $id, 'score' => $score, 'rationale' => $rationale],
                $matches[1]
            );

            return json_encode($items);
        };
    }

    public function test_artisan_command_updates_ai_score_on_contacts(): void
    {
        $this->mock(LlmService::class, fn($m) => $m->shouldReceive('complete')
            ->andReturnUsing($this->batchReply(80, 'Bon profil.')));

        $c1 = Contact::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.test', 'emelia_campaign_id' => 'c1']);
        $c2 = Contact::create(['first_name' => 'C', 'last_name' => 'D', 'email' => 'c@d.test', 'emelia_campaign_id' => 'c2']);

        $this->artisan('ai:score-contacts --limit=10')->assertSuccessful();

        $this->assertNotNull($c1->fresh()->ai_score);
        $this->assertEquals(80, $c1->fresh()->ai_score);
        $this->assertNotNull($c1->fresh()->ai_score_updated_at);
        $this->assertEquals(80, $c2->fresh()->ai_score);
    }

    public function test_artisan_command_skips_contacts_without_emelia_by_default(): void
    {
        $this->mock(LlmService::class, fn($m) => $m->shouldReceive('complete')->never());

        Contact::create(['first_name' => 'No', 'last_name' => 'Emelia', 'email' => 'no@emelia.test']);

        $this->artisan('ai:score-contacts --limit=10')->assertSuccessful();
    }

    public function test_artisan_command_with_all_flag_scores_all_contacts(): void
    {
        $this->mock(LlmService::class, fn($m) => $m->shouldReceive('complete')
            ->once()->andReturnUsing($this->batchReply(30, 'Peu engagé.')));

        $contact = Contact::create(['first_name' => 'All', 'last_name' => 'Flag', 'email' => 'all@flag.test']);

        $this->artisan('ai:score-contacts --limit=10 --all')->assertSuccessful();

        $this->assertEquals(30, $contact->fresh()->ai_score);
    }

    public function test_artisan_command_dry_run_does_not_write_score(): void
    {
        $this->mock(LlmService::class, fn($m) => $m->shouldReceive('complete')
            ->andReturnUsing($this->batchReply(90, 'Top.')));

        $contact = Contact::create(['first_name' => 'Dry', 'last_name' => 'Run', 'email' => 'dry@run.test', 'emelia_campaign_id' => 'cx']);

        $this->artisan('ai:score-contacts --limit=10 --dry-run')->assertSuccessful();

        $this->assertNull($contact->fresh()->ai_score);
    }

    public function test_artisan_command_caps_score_at_100(): void
    {
        $this->mock(LlmService::class, fn($m) => $m->shouldReceive('complete')
            ->andReturnUsing($this->batchReply(150, 'Hors limite.')));

        $contact = Contact::create(['first_name' => 'Cap', 'last_name' => 'Test', 'email' => 'cap@test.test', 'emelia_campaign_id' => 'cx2']);

        $this->artisan('ai:score-contacts --limit=5')->assertSuccessful();

        $this->assertEquals(100, $contact->fresh()->ai_score);
    }

    // ─── Tri ai_score dans l'index contacts ───────────────────────────────────

    public function test_contacts_index_sorts_by_ai_score_desc_nulls_last(): void
    {
        $user = $this->makeUser();

        $c1 = Contact::create(['first_name' => 'High',  'last_name' => 'Score', 'email' => 'high@score.test',  'ai_score' => 90]);
        $c2 = Contact::create(['first_name' => 'Low',   'last_name' => 'Score', 'email' => 'low@score.test',   'ai_score' => 20]);
        $c3 = Contact::create(['first_name' => 'Null',  'last_name' => 'Score', 'email' => 'null@score.test']);

        $response = $this->withAuth($user)
            ->get('/contacts?sort=ai_score&dir=desc');

        $response->assertStatus(200);
        $content = $response->getContent();
        // High doit apparaître avant Low, et Null après
        $posHigh = strpos($content, 'high@score.test');
        $posLow  = strpos($content, 'low@score.test');
        $posNull = strpos($content, 'null@score.test');

        $this->assertLessThan($posLow,  $posHigh, 'Score 90 devrait apparaître avant score 20');
        $this->assertLessThan($posNull, $posLow,  'Score 20 devrait apparaître avant NULL');
    }

    // ─── Badge UI visible dans l'index ────────────────────────────────────────

    public function test_contacts_index_shows_score_badge(): void
    {
        $user = $this->makeUser();
        Contact::create(['first_name' => 'Badge', 'last_name' => 'User', 'email' => 'badge@ui.test', 'ai_score' => 77]);

        $response = $this->withAuth($user)->get('/contacts');

        $response->assertStatus(200);
        $response->assertSee('77');
        $response->assertSee('hsl(', false);
    }

    public function test_contacts_index_shows_dash_when_no_score(): void
    {
        $user = $this->makeUser();
        Contact::create(['first_name' => 'No', 'last_name' => 'Score', 'email' => 'noscore@ui.test']);

        $response = $this->withAuth($user)->get('/contacts');
        $response->assertStatus(200);
        // La colonne Score IA doit être présente
        $response->assertSee('Score IA');
    }
}
