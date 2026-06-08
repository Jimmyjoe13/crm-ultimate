<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\EmeliaCampaign;
use App\Support\EmeliaEventDispatcher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmeliaWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Vérification signature HMAC obligatoire : sans elle, n'importe qui peut pousser des événements.
        $secret    = config('services.emelia.webhook_secret');
        $signature = $request->header('X-Emelia-Signature');

        if (empty($secret) || empty($signature)) {
            abort(401, 'Missing webhook signature.');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, $signature), 401, 'Invalid signature.');

        // 2. Idempotence — ignorer les doublons via external_id
        $eventId = $request->input('event_id');

        if ($eventId && Activity::where('source', 'emelia')->where('external_id', $eventId)->exists()) {
            return response()->json(['status' => 'duplicate']);
        }

        // 3. Résolution du contact (par emelia_contact_id puis par email)
        $contact = Contact::where('emelia_contact_id', $request->input('contact_id'))
            ->orWhere('email', $request->input('email'))
            ->first();

        // 4. Création d'un contact léger si inconnu
        if (! $contact) {
            $email = $request->input('email');

            if (empty($email)) {
                Log::warning('Emelia webhook: missing email for orphan contact', $request->all());

                return response()->json(['status' => 'ignored', 'reason' => 'no email']);
            }

            $contact = Contact::create([
                'first_name'        => $request->input('first_name', ''),
                'last_name'         => $request->input('last_name', ''),
                'email'             => $email,
                'lifecycle_stage'   => 'lead',
                'emelia_contact_id' => $request->input('contact_id'),
            ]);
        } elseif ($request->input('contact_id') && ! $contact->emelia_contact_id) {
            $contact->update(['emelia_contact_id' => $request->input('contact_id')]);
        }

        // 4.bis — Parser le timestamp réel de l'event (nécessaire pour le pivot)
        $rawDate    = $request->input('date') ?? $request->input('timestamp') ?? $request->input('created_at');
        $occurredAt = $rawDate ? Carbon::parse($rawDate) : now();

        // 4.ter — Résolution / création de la campagne + lien pivot N:N
        $rawCampaignId = $request->input('campaign_id');
        $campaignName  = $request->input('campaign_name');
        $campaign      = null;

        if ($rawCampaignId) {
            $campaign = EmeliaCampaign::firstOrCreate(
                ['emelia_id' => $rawCampaignId],
                ['name' => $campaignName ?? $rawCampaignId],
            );

            // Mettre à jour le nom si Emelia l'a changé
            if ($campaignName && $campaign->name !== $campaignName && ! $campaign->wasRecentlyCreated) {
                $campaign->update(['name' => $campaignName]);
            }

            // Lier le contact à la campagne via le pivot (idempotent — ne détache pas les autres campagnes)
            $pivotData = ['emelia_contact_id' => $request->input('contact_id')];

            $existing = $contact->emeliaCampaigns()->whereKey($campaign->id)->first();

            if ($existing) {
                // Mettre à jour last_event_at et status si l'event est plus récent
                if (! $existing->pivot->last_event_at || $occurredAt->gt($existing->pivot->last_event_at)) {
                    $pivotData['last_event_at'] = $occurredAt;
                    $pivotData['status']        = strtoupper($request->input('event', ''));
                }
                $contact->emeliaCampaigns()->updateExistingPivot($campaign->id, $pivotData);
            } else {
                $pivotData['first_event_at'] = $occurredAt;
                $pivotData['last_event_at']  = $occurredAt;
                $pivotData['status']         = strtoupper($request->input('event', ''));
                try {
                    $contact->emeliaCampaigns()->attach($campaign->id, $pivotData);
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Conflit de concurrence (pivot créé entre le check et l'insert) — on met à jour
                    unset($pivotData['first_event_at']);
                    $contact->emeliaCampaigns()->updateExistingPivot($campaign->id, $pivotData);
                }
            }

            // Compat legacy — maintenir les colonnes plates avec la campagne courante
            $contact->update([
                'emelia_campaign_id'   => $rawCampaignId,
                'emelia_campaign_name' => $campaignName,
            ]);
        }

        // 5. Mapping événement → type Activity
        $type = EmeliaEventDispatcher::typeFromEmeliaEvent($request->input('event', ''));

        if ($type === null) {
            Log::info('Emelia webhook: unknown event type ignored', ['event' => $request->input('event')]);

            return response()->json(['status' => 'ignored', 'reason' => 'unknown event']);
        }

        // 6. Déléguer au dispatcher (création Activity + actions secondaires REPLIED)
        // Pour email_replied : privilégier le corps complet, sinon le preview
        $replyBody = $request->input('full_reply')
            ?? $request->input('body')
            ?? $request->input('content')
            ?? $request->input('replyContent')
            ?? $request->input('text')
            ?? $request->input('preview', '');

        $activity = EmeliaEventDispatcher::dispatch(
            contact:          $contact,
            type:             $type,
            payload:          $request->all(),
            occurredAt:       $occurredAt,
            externalId:       $eventId,
            title:            $request->input('subject'),
            body:             $replyBody,
            emeliaCampaignId: $campaign?->id,
        );

        if ($activity === null) {
            return response()->json(['status' => 'duplicate']);
        }

        return response()->json(['status' => 'ok']);
    }
}
