<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\EmeliaService;
use Illuminate\Http\Request;
use RuntimeException;

class EmeliaController extends Controller
{
    public function campaigns(EmeliaService $emelia)
    {
        try {
            $campaigns = $emelia->listCampaigns();
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json($campaigns);
    }

    public function addContact(Contact $contact, Request $request, EmeliaService $emelia)
    {
        $request->validate(['campaign_id' => 'required|string']);

        $payload = [
            'email'       => $contact->email,
            'firstName'   => $contact->first_name ?? '',
            'lastName'    => $contact->last_name ?? '',
            'companyName' => $contact->companies->first()?->name ?? '',
        ];

        try {
            $result = $emelia->addContactToCampaign($request->campaign_id, $payload);
        } catch (RuntimeException $e) {
            return back()->with('flash_toast', ['type' => 'error', 'message' => 'Erreur Emelia : '.$e->getMessage()]);
        }

        $contact->update(['emelia_contact_id' => $result['id'] ?? $result['contact_id'] ?? null]);

        return back()->with('flash_toast', ['type' => 'success', 'message' => 'Contact ajouté à la campagne Emelia.']);
    }
}
