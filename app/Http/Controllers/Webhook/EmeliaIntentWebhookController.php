<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\RemoveFromEmeliaCampaign;
use App\Models\Activity;
use App\Models\Contact;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmeliaIntentWebhookController extends Controller
{
    // Délai tâche urgente (intéressé) — 4 heures
    private const INTERESTED_DUE_HOURS = 4;
    // Délai tâche retour OOO — 7 jours
    private const OOO_DUE_DAYS = 7;

    public function handle(Request $request): JsonResponse
    {
        // 1. Vérification signature HMAC obligatoire : sans elle, n'importe qui peut pousser des événements.
        $secret    = config('services.emelia.webhook_secret');
        $signature = $request->header('X-Emelia-Signature');

        if (empty($secret) || empty($signature)) {
            abort(401, 'Missing webhook signature.');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, $signature), 401, 'Invalid signature.');

        $intent  = $request->input('intent', 'stop');
        $eventId = $request->input('event_id');

        // 2. Idempotence via event_id
        if ($eventId && Activity::where('source', 'emelia')->where('external_id', $eventId)->exists()) {
            return response()->json(['status' => 'duplicate']);
        }

        // 3. Résolution du contact
        $contact = Contact::where('emelia_contact_id', $request->input('emelia_contact_id'))
            ->orWhere('email', $request->input('email'))
            ->first();

        if (! $contact) {
            $email = $request->input('email');

            if (empty($email)) {
                Log::warning('Emelia intent webhook: missing email', $request->all());
                return response()->json(['status' => 'ignored', 'reason' => 'no email']);
            }

            Log::info("Emelia intent webhook: contact not found for email {$email}");
            return response()->json(['status' => 'ignored', 'reason' => 'contact not found']);
        }

        // 4. Dispatch selon l'intent
        return match ($intent) {
            'stop'         => $this->handleStop($request, $contact, $eventId),
            'interested'   => $this->handleInterested($request, $contact, $eventId),
            'not_interested' => $this->handleNotInterested($request, $contact, $eventId),
            'out_of_office'  => $this->handleOutOfOffice($request, $contact, $eventId),
            default        => $this->handleUnknown($intent),
        };
    }

    // ─── STOP / Blacklist ─────────────────────────────────────────────────────

    private function handleStop(Request $request, Contact $contact, ?string $eventId): JsonResponse
    {
        if ($contact->blacklisted_at !== null) {
            return response()->json(['status' => 'duplicate', 'blacklisted_at' => $contact->blacklisted_at]);
        }

        $replyText  = $request->input('reply_text') ?? $request->input('preview') ?? '';
        $campaignId = $request->input('campaign_id');
        $occurredAt = $this->parseDate($request);

        $contact->blacklist('STOP via Emelia reply');

        Activity::create([
            'type'         => Activity::TYPE_EMAIL_UNSUBSCRIBED,
            'source'       => 'emelia',
            'external_id'  => $eventId,
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'title'        => 'Désabonnement automatique (STOP)',
            'body'         => $replyText,
            'occurred_at'  => $occurredAt,
            'metadata'     => [
                'auto_blacklist' => true,
                'intent'         => 'stop',
                'reply_text'     => $replyText,
                'campaign_id'    => $campaignId,
            ],
        ]);

        if ($campaignId && $contact->emelia_contact_id) {
            RemoveFromEmeliaCampaign::dispatch($contact, $campaignId);
        }

        Log::info("Emelia intent webhook: contact #{$contact->id} blacklisted via STOP reply");

        return response()->json(['status' => 'ok', 'action' => 'blacklisted']);
    }

    // ─── Intéressé ────────────────────────────────────────────────────────────

    private function handleInterested(Request $request, Contact $contact, ?string $eventId): JsonResponse
    {
        $replyText    = $request->input('reply_text') ?? $request->input('preview') ?? '';
        $campaignName = $request->input('campaign_name') ?? $contact->emelia_campaign_name ?? 'campagne Emelia';
        $occurredAt   = $this->parseDate($request);
        $firstName    = trim($contact->first_name ?? '');

        // Tâche urgente pour le owner (due dans 4h)
        Activity::create([
            'type'         => Activity::TYPE_TASK,
            'source'       => 'emelia',
            'external_id'  => $eventId ? $eventId . '@task' : null,
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'owner_id'     => $contact->owner_id,
            'title'        => 'URGENT — ' . ($firstName ? "{$firstName} est intéressé(e)" : 'Contact intéressé(e)') . " — {$campaignName}",
            'body'         => $replyText,
            'status'       => 'open',
            'due_at'       => now()->addHours(self::INTERESTED_DUE_HOURS),
            'occurred_at'  => $occurredAt,
            'metadata'     => ['auto_created_by' => 'emelia_intent', 'intent' => 'interested', 'campaign' => $campaignName],
        ]);

        // Bump lifecycle lead/mql → sql
        if (in_array($contact->lifecycle_stage, ['lead', 'mql'])) {
            $contact->update(['lifecycle_stage' => 'sql']);
            Log::info("Emelia intent webhook: contact #{$contact->id} lifecycle bumped {$contact->lifecycle_stage}→sql (interested reply)");
        }

        return response()->json(['status' => 'ok', 'action' => 'task_created_urgent']);
    }

    // ─── Pas intéressé ────────────────────────────────────────────────────────

    private function handleNotInterested(Request $request, Contact $contact, ?string $eventId): JsonResponse
    {
        $replyText    = $request->input('reply_text') ?? $request->input('preview') ?? '';
        $campaignName = $request->input('campaign_name') ?? $contact->emelia_campaign_name ?? 'campagne Emelia';
        $occurredAt   = $this->parseDate($request);
        $firstName    = trim($contact->first_name ?? '');

        // Note dans la timeline (pas de tâche — ne pas rappeler ce contact)
        Activity::create([
            'type'         => Activity::TYPE_NOTE,
            'source'       => 'emelia',
            'external_id'  => $eventId,
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'owner_id'     => $contact->owner_id,
            'title'        => ($firstName ? "{$firstName}" : 'Contact') . ' n\'est pas intéressé(e) — ' . $campaignName,
            'body'         => $replyText,
            'occurred_at'  => $occurredAt,
            'metadata'     => ['auto_created_by' => 'emelia_intent', 'intent' => 'not_interested', 'campaign' => $campaignName],
        ]);

        // Rétrograder le lifecycle mql → lead si le contact était encore à chaud
        if ($contact->lifecycle_stage === 'mql') {
            $contact->update(['lifecycle_stage' => 'lead']);
        }

        return response()->json(['status' => 'ok', 'action' => 'note_created']);
    }

    // ─── Absent / OOO ─────────────────────────────────────────────────────────

    private function handleOutOfOffice(Request $request, Contact $contact, ?string $eventId): JsonResponse
    {
        $replyText    = $request->input('reply_text') ?? $request->input('preview') ?? '';
        $campaignName = $request->input('campaign_name') ?? $contact->emelia_campaign_name ?? 'campagne Emelia';
        $occurredAt   = $this->parseDate($request);
        $firstName    = trim($contact->first_name ?? '');

        // Tâche de rappel différé (7 jours)
        Activity::create([
            'type'         => Activity::TYPE_TASK,
            'source'       => 'emelia',
            'external_id'  => $eventId ? $eventId . '@task' : null,
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'owner_id'     => $contact->owner_id,
            'title'        => 'Relancer ' . ($firstName ?: 'le contact') . ' après son retour (était absent)',
            'body'         => $replyText,
            'status'       => 'open',
            'due_at'       => now()->addDays(self::OOO_DUE_DAYS)->setTime(9, 0),
            'occurred_at'  => $occurredAt,
            'metadata'     => ['auto_created_by' => 'emelia_intent', 'intent' => 'out_of_office', 'campaign' => $campaignName],
        ]);

        return response()->json(['status' => 'ok', 'action' => 'task_created_deferred']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function handleUnknown(string $intent): JsonResponse
    {
        Log::info("Emelia intent webhook: unknown intent '{$intent}' — ignored");
        return response()->json(['status' => 'ignored', 'reason' => 'unknown intent']);
    }

    private function parseDate(Request $request): Carbon
    {
        $raw = $request->input('date') ?? $request->input('timestamp') ?? $request->input('created_at');
        return $raw ? Carbon::parse($raw) : now();
    }
}
