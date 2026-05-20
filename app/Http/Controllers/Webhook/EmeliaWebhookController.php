<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EmeliaWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Vérification signature HMAC
        $secret = config('services.emelia.webhook_secret');

        if (empty($secret)) {
            throw new RuntimeException('EMELIA_WEBHOOK_SECRET not configured.');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        $received = $request->header('X-Emelia-Signature', '');

        abort_unless(hash_equals($expected, $received), 401, 'Invalid signature.');

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
                'email'               => $email,
                'lifecycle_stage'     => 'lead',
                'emelia_contact_id'   => $request->input('contact_id'),
            ]);
        } elseif ($request->input('contact_id') && ! $contact->emelia_contact_id) {
            $contact->update(['emelia_contact_id' => $request->input('contact_id')]);
        }

        // 5. Mapping événement → type Activity
        $type = match ($request->input('event')) {
            'email_sent'           => Activity::TYPE_EMAIL_SENT,
            'email_opened'         => Activity::TYPE_EMAIL_OPENED,
            'email_clicked'        => Activity::TYPE_EMAIL_CLICKED,
            'email_replied'        => Activity::TYPE_EMAIL_REPLIED,
            'email_bounced'        => Activity::TYPE_EMAIL_BOUNCED,
            'contact_unsubscribed' => Activity::TYPE_EMAIL_UNSUBSCRIBED,
            default                => null,
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
            'subject_type' => 'contact',
            'subject_id'   => $contact->id,
            'title'        => $request->input('subject', ucfirst(str_replace('_', ' ', $type))),
            'body'         => $request->input('preview', ''),
            'metadata'     => $request->all(),
        ]);

        return response()->json(['status' => 'ok']);
    }
}
