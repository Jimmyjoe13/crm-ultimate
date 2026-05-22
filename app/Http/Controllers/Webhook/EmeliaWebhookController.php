<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Contact;
use App\Support\EmeliaEventDispatcher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmeliaWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Vérification signature HMAC (optionnelle : Emelia n'envoie le header que si configuré)
        $secret    = config('services.emelia.webhook_secret');
        $signature = $request->header('X-Emelia-Signature');

        if ($signature !== null && ! empty($secret)) {
            $expected = hash_hmac('sha256', $request->getContent(), $secret);
            abort_unless(hash_equals($expected, $signature), 401, 'Invalid signature.');
        }

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
                'first_name'        => '',
                'email'             => $email,
                'lifecycle_stage'   => 'lead',
                'emelia_contact_id' => $request->input('contact_id'),
            ]);
        } elseif ($request->input('contact_id') && ! $contact->emelia_contact_id) {
            $contact->update(['emelia_contact_id' => $request->input('contact_id')]);
        }

        // 5. Mapping événement → type Activity
        $type = EmeliaEventDispatcher::typeFromEmeliaEvent($request->input('event', ''));

        if ($type === null) {
            Log::info('Emelia webhook: unknown event type ignored', ['event' => $request->input('event')]);

            return response()->json(['status' => 'ignored', 'reason' => 'unknown event']);
        }

        // 6. Parser le timestamp réel de l'event depuis le payload Emelia
        $rawDate    = $request->input('date') ?? $request->input('timestamp') ?? $request->input('created_at');
        $occurredAt = $rawDate ? Carbon::parse($rawDate) : now();

        // 7. Déléguer au dispatcher (création Activity + actions secondaires REPLIED)
        $activity = EmeliaEventDispatcher::dispatch(
            contact:    $contact,
            type:       $type,
            payload:    $request->all(),
            occurredAt: $occurredAt,
            externalId: $eventId,
            title:      $request->input('subject'),
            body:       $request->input('preview', ''),
        );

        if ($activity === null) {
            return response()->json(['status' => 'duplicate']);
        }

        return response()->json(['status' => 'ok']);
    }
}
