<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SyncEmeliaCampaignJob;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\EmeliaCampaign;
use App\Services\EmeliaService;
use App\Support\EmeliaEventDispatcher;
use Carbon\Carbon;
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

        $mapped = array_map(fn ($c) => [
            'id' => $c['_id'] ?? $c['id'] ?? null,
            'name' => $c['name'] ?? $c['title'] ?? '',
            'status' => $c['status'] ?? '',
            'contacts_count' => $c['contactsCount'] ?? 0,
        ], $campaigns);

        return response()->json($mapped);
    }

    public function status(Contact $contact, EmeliaService $emelia): JsonResponse
    {
        // ─── Campagnes liées via le pivot N:N ─────────────────────────────
        $linkedCampaigns = $contact->emeliaCampaigns()
            ->orderByPivot('last_event_at', 'desc')
            ->get();

        // Fallback : si aucune campagne via pivot mais colonnes legacy renseignées,
        // considérer quand même le contact comme dans Emelia
        $inEmelia = $linkedCampaigns->isNotEmpty()
            || $contact->emelia_campaign_id !== null
            || $contact->emelia_contact_id !== null;

        // ─── Compteurs webhook groupés par campagne ────────────────────────
        $allActivities = Activity::where('source', 'emelia')
            ->where('subject_type', Contact::class)
            ->where('subject_id', $contact->id)
            ->orderBy('occurred_at', 'desc')
            ->get();

        $globalStats = [
            'sent' => $allActivities->where('type', Activity::TYPE_EMAIL_SENT)->count(),
            'opened' => $allActivities->where('type', Activity::TYPE_EMAIL_OPENED)->count(),
            'clicked' => $allActivities->where('type', Activity::TYPE_EMAIL_CLICKED)->count(),
            'replied' => $allActivities->where('type', Activity::TYPE_EMAIL_REPLIED)->count(),
            'bounced' => $allActivities->where('type', Activity::TYPE_EMAIL_BOUNCED)->count(),
            'unsubscribed' => $allActivities->where('type', Activity::TYPE_EMAIL_UNSUBSCRIBED)->count(),
        ];

        // Stats par campagne (indexées par emelia_campaign_id, null = legacy sans campagne)
        $statsByCampaign = [];
        foreach ($allActivities as $act) {
            $key = $act->emelia_campaign_id ?? 'legacy';
            if (! isset($statsByCampaign[$key])) {
                $statsByCampaign[$key] = ['sent' => 0, 'opened' => 0, 'clicked' => 0, 'replied' => 0, 'bounced' => 0, 'unsubscribed' => 0];
            }
            $typeMap = [
                Activity::TYPE_EMAIL_SENT => 'sent',
                Activity::TYPE_EMAIL_OPENED => 'opened',
                Activity::TYPE_EMAIL_CLICKED => 'clicked',
                Activity::TYPE_EMAIL_REPLIED => 'replied',
                Activity::TYPE_EMAIL_BOUNCED => 'bounced',
                Activity::TYPE_EMAIL_UNSUBSCRIBED => 'unsubscribed',
            ];
            if (isset($typeMap[$act->type])) {
                $statsByCampaign[$key][$typeMap[$act->type]]++;
            }
        }

        // ─── Données live Emelia (1 appel par campagne, cache 10 min) ─────
        $msToDate = fn (?string $ms): ?string => $ms
            ? Carbon::createFromTimestampMs((int) $ms)->diffForHumans()
            : null;

        $campaignsOutput = [];

        if ($linkedCampaigns->isNotEmpty()) {
            foreach ($linkedCampaigns as $camp) {
                $cacheKey = "emelia_live_{$contact->id}_{$camp->id}";
                $liveData = null;

                if ($contact->email) {
                    $liveData = Cache::remember($cacheKey, 600, function () use ($contact, $emelia, $camp) {
                        try {
                            $emeliaCid = $camp->pivot->emelia_contact_id ?? $contact->emelia_contact_id;
                            if ($emeliaCid && $camp->emelia_id) {
                                $data = $emelia->getContactById($emeliaCid, $camp->emelia_id);
                                if ($data) {
                                    return $data;
                                }
                            }

                            return $emelia->getContactByEmail($contact->email, $camp->name);
                        } catch (\Exception) {
                            return null;
                        }
                    });

                    // Back-fill emelia_contact_id sur le pivot si trouvé
                    if ($liveData && ! empty($liveData['_id']) && ! $camp->pivot->emelia_contact_id) {
                        $contact->emeliaCampaigns()->updateExistingPivot($camp->id, [
                            'emelia_contact_id' => $liveData['_id'],
                        ]);
                    }
                }

                $campaignStats = $statsByCampaign[$camp->id] ?? ['sent' => 0, 'opened' => 0, 'clicked' => 0, 'replied' => 0, 'bounced' => 0, 'unsubscribed' => 0];

                $campaignsOutput[] = [
                    'id' => $camp->id,
                    'emelia_id' => $camp->emelia_id,
                    'name' => $camp->name,
                    'client_name' => $camp->client_name,
                    'objective' => $camp->objective,
                    'pivot_status' => $camp->pivot->status,
                    'first_event_at' => $camp->pivot->first_event_at
                        ? Carbon::parse($camp->pivot->first_event_at)->diffForHumans()
                        : null,
                    'last_event_at' => $camp->pivot->last_event_at
                        ? Carbon::parse($camp->pivot->last_event_at)->diffForHumans()
                        : null,
                    'emelia_status' => $liveData['status'] ?? null,
                    'last_contacted' => $msToDate($liveData['lastContacted'] ?? null),
                    'last_open' => $msToDate($liveData['lastOpen'] ?? null),
                    'last_replied' => $msToDate($liveData['lastReplied'] ?? null),
                    'stats' => $campaignStats,
                ];
            }
        } elseif ($inEmelia && $contact->email) {
            // Fallback legacy (1:1) — pas encore de pivot mais colonnes plates renseignées
            $cacheKey = "emelia_live_{$contact->id}";
            $liveData = Cache::remember($cacheKey, 600, function () use ($contact, $emelia) {
                try {
                    if ($contact->emelia_contact_id && $contact->emelia_campaign_id) {
                        $data = $emelia->getContactById(
                            $contact->emelia_contact_id,
                            $contact->emelia_campaign_id,
                        );
                        if ($data) {
                            return $data;
                        }
                    }

                    return $emelia->getContactByEmail($contact->email, $contact->emelia_campaign_name);
                } catch (\Exception) {
                    return null;
                }
            });

            if ($liveData && ! empty($liveData['_id']) && ! $contact->emelia_contact_id) {
                $contact->update(['emelia_contact_id' => $liveData['_id']]);
            }

            $campaignsOutput[] = [
                'id' => null,
                'emelia_id' => $contact->emelia_campaign_id,
                'name' => $contact->emelia_campaign_name ?? $contact->emelia_campaign_id,
                'client_name' => null,
                'objective' => null,
                'pivot_status' => null,
                'first_event_at' => null,
                'last_event_at' => null,
                'emelia_status' => $liveData['status'] ?? null,
                'last_contacted' => $msToDate($liveData['lastContacted'] ?? null),
                'last_open' => $msToDate($liveData['lastOpen'] ?? null),
                'last_replied' => $msToDate($liveData['lastReplied'] ?? null),
                'stats' => $statsByCampaign['legacy'] ?? $globalStats,
            ];
        }

        // Campagne "principale" = la plus récente (rétro-compat avec l'UI legacy)
        $primaryCampaign = $campaignsOutput[0] ?? null;

        return response()->json([
            'in_emelia' => $inEmelia,
            // Rétro-compat (1 campagne)
            'campaign_id' => $primaryCampaign['emelia_id'] ?? $contact->emelia_campaign_id,
            'campaign_name' => $primaryCampaign['name'] ?? $contact->emelia_campaign_name,
            'contact_id' => $contact->emelia_contact_id,
            'emelia_status' => $primaryCampaign['emelia_status'] ?? null,
            'last_contacted' => $primaryCampaign['last_contacted'] ?? null,
            'last_open' => $primaryCampaign['last_open'] ?? null,
            'last_replied' => $primaryCampaign['last_replied'] ?? null,
            // Nouveau : liste de toutes les campagnes
            'campaigns' => $campaignsOutput,
            // Compteurs globaux (toutes campagnes confondues)
            'stats' => $globalStats,
            'total_activities' => $allActivities->count(),
            'last_activity' => $allActivities->first()?->occurred_at?->diffForHumans(),
            'webhook_active' => $allActivities->count() > 0,
        ]);
    }

    public function addContact(Contact $contact, Request $request, EmeliaService $emelia): mixed
    {
        // Accepte campaign_ids[] (multi) ou campaign_id (legacy single)
        $campaignIds = $request->input('campaign_ids', []);
        if (empty($campaignIds) && $request->input('campaign_id')) {
            $campaignIds = [$request->input('campaign_id')];
        }

        $request->validate([
            'campaign_ids' => 'nullable|array',
            'campaign_ids.*' => 'string',
            'campaign_id' => 'nullable|string',
            'campaign_name' => 'nullable|string|max:255',
        ]);

        if (empty($campaignIds)) {
            return response()->json(['error' => 'Au moins une campagne est requise.'], 422);
        }

        // Bloquer les contacts blacklistés
        if ($contact->blacklisted_at !== null) {
            $msg = 'Ce contact est blacklisté — il ne peut pas être ajouté à une campagne Emelia.';
            if ($request->expectsJson()) {
                return response()->json(['error' => $msg], 422);
            }

            return back()->with('flash_toast', ['type' => 'error', 'message' => $msg]);
        }

        // Lister les campagnes Emelia pour résoudre les noms
        try {
            $raw = $emelia->listCampaigns();
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        $emeliaCampaignList = collect($raw['campaigns'] ?? $raw)->keyBy(fn ($c) => $c['_id'] ?? $c['id'] ?? '');

        $payload = [
            'email' => $contact->email,
            'firstName' => $contact->first_name ?? '',
            'lastName' => $contact->last_name ?? '',
            'companyName' => $contact->companies->first()?->name ?? '',
        ];

        $addedNames = [];
        $lastResult = null;

        foreach ($campaignIds as $campaignEmeliaId) {
            try {
                $result = $emelia->addContactToCampaign($campaignEmeliaId, $payload);
            } catch (RuntimeException $e) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => $e->getMessage()], 502);
                }

                return back()->with('flash_toast', ['type' => 'error', 'message' => 'Erreur Emelia : '.$e->getMessage()]);
            }

            $emeliaCampData = $emeliaCampaignList->get($campaignEmeliaId);
            $campaignName = $emeliaCampData['name'] ?? $emeliaCampData['title'] ?? $request->input('campaign_name') ?? $campaignEmeliaId;

            // Upsert dans le registre local + pivot
            $campaign = EmeliaCampaign::firstOrCreate(
                ['emelia_id' => $campaignEmeliaId],
                ['name' => $campaignName],
            );

            if ($campaign->name !== $campaignName && ! $campaign->wasRecentlyCreated) {
                $campaign->update(['name' => $campaignName]);
            }

            $emeliaCid = $result['id'] ?? $result['contact_id'] ?? $contact->emelia_contact_id;

            $pivotData = [
                'emelia_contact_id' => $emeliaCid,
                'last_event_at' => now(),
            ];

            if (! $contact->emeliaCampaigns()->whereKey($campaign->id)->exists()) {
                $pivotData['first_event_at'] = now();
                $contact->emeliaCampaigns()->attach($campaign->id, $pivotData);
            } else {
                $contact->emeliaCampaigns()->updateExistingPivot($campaign->id, $pivotData);
            }

            $addedNames[] = $campaignName;
            $lastResult = ['id' => $emeliaCid, 'campaign_name' => $campaignName, 'emelia_id' => $campaignEmeliaId];
        }

        // Compat legacy : colonnes plates = dernière campagne ajoutée
        if ($lastResult) {
            $contact->update([
                'emelia_contact_id' => $lastResult['id'] ?? $contact->emelia_contact_id,
                'emelia_campaign_id' => $lastResult['emelia_id'],
                'emelia_campaign_name' => $lastResult['campaign_name'],
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'campaign_names' => $addedNames,
                'campaign_name' => $lastResult['campaign_name'] ?? null,
            ]);
        }

        $msg = count($addedNames) > 1
            ? 'Contact ajouté à '.count($addedNames).' campagnes Emelia.'
            : 'Contact ajouté à la campagne Emelia.';

        return back()->with('flash_toast', ['type' => 'success', 'message' => $msg]);
    }

    public function syncNow(): JsonResponse
    {
        SyncEmeliaCampaignJob::dispatch(onlyLinked: false);

        return response()->json(['message' => 'Synchronisation lancée en arrière-plan.']);
    }

    public function syncContact(Contact $contact, EmeliaService $emelia): JsonResponse
    {
        $campaigns = $contact->emeliaCampaigns()->get();

        // Fallback legacy : si aucun pivot mais colonnes plates renseignées
        if ($campaigns->isEmpty()) {
            if (! $contact->emelia_campaign_name && ! $contact->emelia_campaign_id) {
                return response()->json(['error' => 'Contact non lié à Emelia.'], 422);
            }

            $emeliData = $emelia->getContactByEmail($contact->email, $contact->emelia_campaign_name);
            $resolvedId = $emeliData['_id'] ?? null;
            if (! $resolvedId) {
                return response()->json(['error' => 'Contact introuvable dans Emelia.'], 404);
            }

            $contact->update(['emelia_contact_id' => $resolvedId]);
            $contact->refresh();

            // Créer le pivot si campagne connue
            if ($contact->emelia_campaign_id) {
                $campaign = EmeliaCampaign::firstOrCreate(
                    ['emelia_id' => $contact->emelia_campaign_id],
                    ['name' => $contact->emelia_campaign_name ?? $contact->emelia_campaign_id],
                );
                if (! $contact->emeliaCampaigns()->whereKey($campaign->id)->exists()) {
                    $contact->emeliaCampaigns()->attach($campaign->id, [
                        'emelia_contact_id' => $resolvedId,
                    ]);
                }
                $contact->refresh();
                $campaigns = $contact->emeliaCampaigns()->get();
            }
        }

        $created = 0;
        $total = 0;

        foreach ($campaigns as $camp) {
            $emeliaCid = $camp->pivot->emelia_contact_id ?? $contact->emelia_contact_id;

            if (! $emeliaCid) {
                continue;
            }

            $events = $emelia->getContactEvents(
                $emeliaCid,
                $camp->emelia_id,
                $contact->email,
                $camp->name,
            );

            $total += count($events);

            foreach ($events as $event) {
                $type = EmeliaEventDispatcher::typeFromEmeliaEvent($event['type']);
                if (! $type) {
                    continue;
                }
                $date = $event['date'];
                $externalId = 'emelia:'.hash('sha256', "{$emeliaCid}:{$camp->emelia_id}:{$type}:{$date->toIso8601String()}");
                $activity = EmeliaEventDispatcher::dispatch(
                    contact: $contact,
                    type: $type,
                    payload: ['synthetic' => true, 'source_contact_id' => $emeliaCid],
                    occurredAt: $date,
                    externalId: $externalId,
                    emeliaCampaignId: $camp->id,
                );
                if ($activity !== null) {
                    $created++;
                }
            }

            Cache::forget("emelia_live_{$contact->id}_{$camp->id}");
        }

        Cache::forget("emelia_status_{$contact->id}");

        return response()->json(['created' => $created, 'total_events' => $total]);
    }
}
