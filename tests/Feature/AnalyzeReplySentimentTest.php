<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeReplySentiment;
use App\Models\Activity;
use App\Models\Contact;
use App\Services\AiInsightService;
use App\Services\LlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyzeReplySentimentTest extends TestCase
{
    use RefreshDatabase;

    private function makeContact(): Contact
    {
        static $n = 0; $n++;
        return Contact::create([
            'first_name' => "C{$n}", 'last_name' => 'Test',
            'email' => "c{$n}@sentiment.test",
        ]);
    }

    private function makeReplyActivity(Contact $contact, array $metadata = []): Activity
    {
        return Activity::create([
            'type'         => Activity::TYPE_EMAIL_REPLIED,
            'source'       => 'emelia',
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'title'        => 'A répondu',
            'body'         => '',
            'metadata'     => $metadata,
        ]);
    }

    // ─── analyzeSentiment() service ───────────────────────────────────────────

    public function test_analyze_sentiment_returns_positive(): void
    {
        $this->mock(LlmService::class, fn($m) => $m->shouldReceive('complete')
            ->once()->andReturn('{"sentiment": "positif", "score": 0.9, "summary": "Le contact est très intéressé."}'));

        $result = app(AiInsightService::class)->analyzeSentiment('Merci beaucoup, je suis très intéressé !');

        $this->assertEquals('positif', $result['sentiment']);
        $this->assertEquals(0.9, $result['score']);
        $this->assertNotEmpty($result['summary']);
    }

    public function test_analyze_sentiment_returns_negatif(): void
    {
        $this->mock(LlmService::class, fn($m) => $m->shouldReceive('complete')
            ->once()->andReturn('{"sentiment": "négatif", "score": -0.7, "summary": "Le contact n\'est pas intéressé."}'));

        $result = app(AiInsightService::class)->analyzeSentiment('Non merci, je ne suis pas intéressé.');

        $this->assertEquals('négatif', $result['sentiment']);
        $this->assertLessThan(0, $result['score']);
    }

    public function test_analyze_sentiment_fallback_on_invalid_json(): void
    {
        $this->mock(LlmService::class, fn($m) => $m->shouldReceive('complete')
            ->once()->andReturn('Je ne peux pas analyser cela.'));

        $result = app(AiInsightService::class)->analyzeSentiment('Texte quelconque.');

        $this->assertEquals('neutre', $result['sentiment']);
        $this->assertEquals(0.0, $result['score']);
    }

    // ─── Job AnalyzeReplySentiment ────────────────────────────────────────────

    public function test_job_saves_sentiment_in_metadata(): void
    {
        $this->mock(LlmService::class, fn($m) => $m->shouldReceive('complete')
            ->once()->andReturn('{"sentiment": "positif", "score": 0.8, "summary": "Intéressé."}'));

        $contact  = $this->makeContact();
        $activity = $this->makeReplyActivity($contact, ['preview' => 'Oui, je suis intéressé !']);

        (new AnalyzeReplySentiment($activity))->handle(app(AiInsightService::class));

        $activity->refresh();
        $this->assertArrayHasKey('sentiment', $activity->metadata);
        $this->assertEquals('positif', $activity->metadata['sentiment']['sentiment']);
    }

    public function test_job_skips_when_no_preview(): void
    {
        $this->mock(LlmService::class, fn($m) => $m->shouldReceive('complete')->never());

        $contact  = $this->makeContact();
        $activity = $this->makeReplyActivity($contact, []);

        (new AnalyzeReplySentiment($activity))->handle(app(AiInsightService::class));

        $activity->refresh();
        $this->assertArrayNotHasKey('sentiment', $activity->metadata ?? []);
    }

    public function test_job_skips_short_preview(): void
    {
        $this->mock(LlmService::class, fn($m) => $m->shouldReceive('complete')->never());

        $contact  = $this->makeContact();
        $activity = $this->makeReplyActivity($contact, ['preview' => 'Ok']);

        (new AnalyzeReplySentiment($activity))->handle(app(AiInsightService::class));

        $activity->refresh();
        $this->assertArrayNotHasKey('sentiment', $activity->metadata ?? []);
    }

    public function test_job_fails_silently_on_llm_error(): void
    {
        $this->mock(LlmService::class, fn($m) => $m->shouldReceive('complete')
            ->once()->andThrow(new \RuntimeException('LLM timeout')));

        $contact  = $this->makeContact();
        $activity = $this->makeReplyActivity($contact, ['preview' => 'Je suis intéressé par votre offre.']);

        // Should not throw
        (new AnalyzeReplySentiment($activity))->handle(app(AiInsightService::class));

        $activity->refresh();
        $this->assertArrayNotHasKey('sentiment', $activity->metadata ?? []);
    }

    // ─── Dispatch depuis EmeliaEventDispatcher ────────────────────────────────

    public function test_dispatcher_queues_sentiment_job_on_replied(): void
    {
        Queue::fake();

        $contact = $this->makeContact();

        \App\Support\EmeliaEventDispatcher::dispatch(
            $contact,
            Activity::TYPE_EMAIL_REPLIED,
            ['preview' => 'Oui, cela m\'intéresse beaucoup !'],
            now(),
            'ext-test-'.uniqid(),
        );

        Queue::assertPushed(AnalyzeReplySentiment::class);
    }

    public function test_dispatcher_does_not_queue_sentiment_for_other_events(): void
    {
        Queue::fake();

        $contact = $this->makeContact();

        \App\Support\EmeliaEventDispatcher::dispatch(
            $contact,
            Activity::TYPE_EMAIL_OPENED,
            [],
            now(),
            'ext-open-'.uniqid(),
        );

        Queue::assertNotPushed(AnalyzeReplySentiment::class);
    }
}
