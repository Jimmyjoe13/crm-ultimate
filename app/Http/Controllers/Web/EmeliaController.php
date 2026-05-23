<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SyncEmeliaCampaignJob;
use App\Models\Activity;
use App\Models\Contact;
use App\Services\EmeliaService;
use App\Support\EmeliaEventDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class EmeliaController extends Controller
{
    public function campaigns(EmeliaService $emelia): JsonResponse
    {
        try {
            $raw = $emelia->listCampaigns();
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        $campaigns = $raw['campaigns'] ?? $raw;

        $mapped = array_map(fn($c) => [
            'id'     => $c['_id'] ?? $c['id'] ?? null,
            'name'   => $c['name'] ?? $c['title'] ?? '',
            'status' => $c['status'] ?? '',
            'contacts_count' => $c['contactsCount'] ?? 0,
        ], $campaigns);

        return response()->json($mapped);
    }

    public function status(Contact $contact, EmeliaService $emelia): JsonResponse
    {
        // ─── Compteurs depuis la table activities (webhook) ───────────────
        $activities = Activity::where('source', 'emelia')
            ->where('subject_type', Contact::class)
            ->where('subject_id', $contact->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'sent'         => $activities->where('type', Activity::TYPE_EMAIL_SENT)->count(),
            'opened'       => $activities->where('type', Activity::TYPE_EMAIL_OPENED)->count(),
            'clicked'      => $activities->where('type', Activity::TYPE_EMAIL_CLICKED)->count(),
            'replied'      => $activities->where('type', Activity::TYPE_EMAIL_REPLIED)->count(),
            'bounced'      => $activities->where('type', Activity::TYPE_EMAIL_BOUNCED)->count(),
            'unsubscribed' => $activities->where('type', Activity::TYPE_EMAIL_UNSUBSCRIBED)->count(),
        ];

        // ─── Données live Emelia (statut + dates) — cache 10 min ─────────
        $liveData = null;
        $inEmelia = $contact->emelia_campaign_id !== null || $contact->emelia_contact_id !== null;

        if ($inEmelia && $contact->email) {
            $cacheKey = "emelia_live_{$contact->id}";
            $liveData = Cache::remember($cacheKey, 600, function () use ($contact, $emelia) {
                try {
                    // Lookup par ID Emelia si disponible (plus rapide)
                    if ($contact->emelia_contact_id && $contact->emelia_campaign_id) {
                        $data = $emelia->getContactById(
                            $contact->emelia_contact_id,
                            $contact->emelia_campaign_id
                        );
                        if ($data) {
                            return $data;
                        }
                    }
                    // Lookup par email + nom de campagne (fallback)
                    return $emelia->getContactByEmail($contact->email, $contact->emelia_campaign_name);
                } catch (\Exception) {
                    return null;
                }
            });

            // Sauvegarder l'ID Emelia si on vient de le trouver
            if ($liveData && ! empty($liveData['_id']) && ! $contact->emelia_contact_id) {
                $contact->update(['emelia_contact_id' => $liveData['_id']]);
            }
        }

        $msToDate = fn(?string $ms): ?string => $ms
            ? \Carbon\Carbon::createFromTimestampMs((int) $ms)->diffForHumans()
            : null;

        return response()->json([
            'in_emelia'        => $inEmelia,
            'campaign_id'      => $contact->emelia_campaign_id,
            'campaign_name'    => $contact->emelia_campaign_name,
            'contact_id'       => $contact->emelia_contact_id ?? ($liveData['_id'] ?? null),
            // Statut live depuis l'API Emelia (SENT/OPENED/REPLIED/BOUNCED/UNSUBSCRIBED)
            'emelia_status'    => $liveData['status'] ?? null,
            'last_contacted'   => $msToDate($liveData['lastContacted'] ?? null),
            'last_open'        => $msToDate($liveData['lastOpen'] ?? null),
            'last_replied'     => $msToDate($liveData['lastReplied'] ?? null),
            // Compteurs webhook (0 tant que le webhook n'est pas configuré)
            'stats'            => $stats,
            'total_activities' => $activities->count(),
            'last_activity'    => $activities->first()?->created_at?->diffForHumans(),
            'webhook_active'   => $activities->count() > 0,
        ]);
    }

    public function addContact(Contact $contact, Request $request, EmeliaService $emelia): mixed
    {
        $request->validate([
            'campaign_id'   => 'required|string',
            'campaign_name' => 'nullable|string|max:255',
        ]);

        $payload = [
            'email'       => $contact->email,
            'firstName'   => $contact->first_name ?? '',
            'lastName'    => $contact->last_name ?? '',
            'companyName' => $contact->companies->first()?->name ?? '',
        ];

        try {
            $result = $emelia->addContactToCampaign($request->campaign_id, $payload);
        } catch (RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 502);
            }
            return back()->with('flash_toast', ['type' => 'error', 'message' => 'Erreur Emelia : '.$e->getMessage()]);
        }

        $contact->update([
            'emelia_contact_id'   => $result['id'] ?? $result['contact_id'] ?? $contact->emelia_contact_id,
            'emelia_campaign_id'  => $request->campaign_id,
            'emelia_campaign_name' => $request->input('campaign_name'),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'campaign_name' => $request->input('campaign_name')]);
        }

        return back()->with('flash_toast', ['type' => 'success', 'message' => 'Contact ajouté à la campagne Emelia.']);
    }

    public function syncNow(): JsonResponse
    {
        SyncEmeliaCampaignJob::dispatch(onlyLinked: false);
        return response()->json(['message' => 'Synchronisation lancée en arrière-plan.']);
    }

    public function syncContact(Contact $contact, EmeliaService $emelia): JsonResponse
    {
        // Résoudre l'ID Emelia si absent
        if (! $contact->emelia_contact_id) {
            if (! $contact->emelia_campaign_name) {
                return response()->json(['error' => 'Contact non lié à Emelia.'], 422);
            }
            $emeliData = $emelia->getContactByEmail($contact->email, $contact->emelia_campaign_name);
            $resolvedId = $emeliData['_id'] ?? null;
            if (! $resolvedId) {
                return response()->json(['error' => 'Contact introuvable dans Emelia.'], 404);
            }
            $contact->update([
                'emelia_contact_id' => $resolvedId,
                'emelia_campaign_id' => $contact->emelia_campaign_id
                    ?? ($emeliData['campaigns'][0] ?? null),
            ]);
            $contact->refresh();
        }

        $events  = $emelia->getContactEvents(
            $contact->emelia_contact_id,
            $contact->emelia_campaign_id,
            $contact->email,
            $contact->emelia_campaign_name,
        );
        $created = 0;

        foreach ($events as $event) {
            $type = EmeliaEventDispatcher::typeFromEmeliaEvent($event['type']);
            if (! $type) {
                continue;
            }
            $date       = $event['date'];
            $externalId = 'emelia:' . hash('sha256', "{$contact->emelia_contact_id}:{$type}:{$date->toIso8601String()}");
            $activity   = EmeliaEventDispatcher::dispatch(
                contact:    $contact,
                type:       $type,
                payload:    ['synthetic' => true, 'source_contact_id' => $contact->emelia_contact_id],
                occurredAt: $date,
                externalId: $externalId,
            );
            if ($activity !== null) {
                $created++;
            }
        }

        Cache::forget("emelia_status_{$contact->id}");

        return response()->json(['created' => $created, 'total_events' => count($events)]);
    }
}
