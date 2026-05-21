<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Contact;
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

        // 2. Idempotence — ignorer les doublons
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

        // 5. Mapping événement → type Activity (Emelia envoie des noms UPPERCASE sans préfixe)
        $type = match (strtoupper($request->input('event', ''))) {
            'SENT', 'EMAIL_SENT'                                    => Activity::TYPE_EMAIL_SENT,
            'OPENED', 'FIRST_OPEN', 'EMAIL_OPENED'                  => Activity::TYPE_EMAIL_OPENED,
            'CLICKED', 'EMAIL_CLICKED'                              => Activity::TYPE_EMAIL_CLICKED,
            'REPLIED', 'EMAIL_REPLIED'                              => Activity::TYPE_EMAIL_REPLIED,
            'BOUNCED', 'EMAIL_BOUNCED'                              => Activity::TYPE_EMAIL_BOUNCED,
            'UNSUBSCRIBED', 'CONTACT_UNSUBSCRIBED', 'EMAIL_UNSUBSCRIBED' => Activity::TYPE_EMAIL_UNSUBSCRIBED,
            default                                                 => null,
        };

        if ($type === null) {
            Log::info('Emelia webhook: unknown event type ignored', ['event' => $request->input('event')]);

            return response()->json(['status' => 'ignored', 'reason' => 'unknown event']);
        }

        // 6. Création de l'activité
        Activity::create([
            'type'         => $type,
            'source'       => 'emelia',
            'external_id'  => $eventId,
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'title'        => $request->input('subject', ucfirst(str_replace('_', ' ', $type))),
            'body'         => $request->input('preview', ''),
            'metadata'     => $request->all(),
        ]);

        return response()->json(['status' => 'ok']);
    }
}
