<?php

namespace App\Support;

use App\Jobs\AnalyzeReplySentiment;
use App\Models\Activity;
use App\Models\Contact;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EmeliaEventDispatcher
{
    /**
     * Mappe un nom d'event Emelia (UPPERCASE ou prefixé) vers un type Activity.
     */
    public static function typeFromEmeliaEvent(string $event): ?string
    {
        return match (strtoupper($event)) {
            'SENT', 'EMAIL_SENT'                                         => Activity::TYPE_EMAIL_SENT,
            'OPENED', 'FIRST_OPEN', 'EMAIL_OPENED'                       => Activity::TYPE_EMAIL_OPENED,
            'CLICKED', 'EMAIL_CLICKED'                                   => Activity::TYPE_EMAIL_CLICKED,
            'REPLIED', 'EMAIL_REPLIED'                                   => Activity::TYPE_EMAIL_REPLIED,
            'BOUNCED', 'EMAIL_BOUNCED'                                   => Activity::TYPE_EMAIL_BOUNCED,
            'UNSUBSCRIBED', 'CONTACT_UNSUBSCRIBED', 'EMAIL_UNSUBSCRIBED' => Activity::TYPE_EMAIL_UNSUBSCRIBED,
            default                                                       => null,
        };
    }

    /**
     * Crée (ou skip si doublon) une Activity Emelia et applique les actions secondaires.
     *
     * @param  Contact      $contact     Le contact CRM concerné
     * @param  string       $type        Constante Activity::TYPE_EMAIL_*
     * @param  array        $payload     Payload brut Emelia (stocké dans metadata)
     * @param  Carbon|null  $occurredAt  Timestamp réel de l'event (null = now)
     * @param  string|null  $externalId  ID unique de l'event pour idempotence
     * @param  string|null  $title       Titre de l'activité (sinon déduit du type)
     * @param  string|null  $body        Aperçu du message
     * @return Activity|null  null si doublon ignoré
     */
    public static function dispatch(
        Contact $contact,
        string $type,
        array $payload = [],
        ?Carbon $occurredAt = null,
        ?string $externalId = null,
        ?string $title = null,
        ?string $body = null,
    ): ?Activity {
        $occurred = $occurredAt ?? now();

        $data = [
            'type'         => $type,
            'source'       => 'emelia',
            'external_id'  => $externalId,
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'title'        => $title ?? ucfirst(str_replace('_', ' ', $type)),
            'body'         => $body ?? '',
            'metadata'     => $payload,
            'occurred_at'  => $occurred,
        ];

        // Sans external_id, pas d'idempotence possible — on crée directement
        if ($externalId === null) {
            $activity = Activity::create($data);
        } else {
            $activity = Activity::updateOrCreate(
                ['source' => 'emelia', 'external_id' => $externalId],
                $data,
            );

            // C'était un doublon → on arrête là
            if (! $activity->wasRecentlyCreated) {
                return null;
            }
        }

        if ($type === Activity::TYPE_EMAIL_REPLIED) {
            self::handleReply($contact, $payload, $occurred);
            AnalyzeReplySentiment::dispatch($activity);
        }

        return $activity;
    }

    private static function handleReply(Contact $contact, array $payload, Carbon $occurred): void
    {
        $contact->refresh();
        $campaignName = $contact->emelia_campaign_name ?? 'campagne Emelia';
        $firstName    = trim($contact->first_name);
        $taskTitle    = 'Rappeler' . ($firstName ? " {$firstName}" : '') . " — a répondu à {$campaignName}";

        // Tâche pour le owner (ou sans owner si non assigné)
        Activity::create([
            'type'         => Activity::TYPE_TASK,
            'source'       => 'emelia',
            'external_id'  => null,
            'subject_type' => Contact::class,
            'subject_id'   => $contact->id,
            'owner_id'     => $contact->owner_id,
            'title'        => $taskTitle,
            'body'         => '',
            'status'       => 'open',
            'due_at'       => $occurred->copy()->addDay()->setTime(9, 0),
            'metadata'     => ['auto_created_by' => 'emelia_reply', 'campaign' => $campaignName],
        ]);

        // Bump lifecycle lead → mql
        if ($contact->lifecycle_stage === 'lead') {
            $contact->update(['lifecycle_stage' => 'mql']);
            Log::info("Emelia reply: contact #{$contact->id} lifecycle bumped lead→mql");
        }
    }
}
