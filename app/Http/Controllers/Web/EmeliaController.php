<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Contact;
use App\Services\EmeliaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function status(Contact $contact): JsonResponse
    {
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

        return response()->json([
            'in_emelia'      => (bool) $contact->emelia_contact_id,
            'campaign_id'    => $contact->emelia_campaign_id,
            'campaign_name'  => $contact->emelia_campaign_name,
            'contact_id'     => $contact->emelia_contact_id,
            'stats'          => $stats,
            'total_activities' => $activities->count(),
            'last_activity'  => $activities->first()?->created_at?->diffForHumans(),
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
}
