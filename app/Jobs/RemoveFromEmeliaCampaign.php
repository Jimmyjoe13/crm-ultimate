<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Services\EmeliaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RemoveFromEmeliaCampaign implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly Contact $contact,
        public readonly string $campaignId,
    ) {}

    public function handle(EmeliaService $emelia): void
    {
        $emeliaContactId = $this->contact->emelia_contact_id;

        if (! $emeliaContactId) {
            Log::warning("RemoveFromEmeliaCampaign: contact #{$this->contact->id} has no emelia_contact_id — cannot remove from campaign {$this->campaignId}");
            return;
        }

        try {
            $removed = $emelia->removeFromCampaign($this->campaignId, $emeliaContactId);

            if ($removed) {
                Log::info("RemoveFromEmeliaCampaign: contact #{$this->contact->id} removed from campaign {$this->campaignId}");
            } else {
                Log::warning("RemoveFromEmeliaCampaign: could not remove contact #{$this->contact->id} from campaign {$this->campaignId} (already removed or mutation not supported)");
            }
        } catch (\Throwable $e) {
            Log::warning("RemoveFromEmeliaCampaign: failed for contact #{$this->contact->id} — {$e->getMessage()}");
            // Ne pas faire échouer le job : le contact est blacklisté côté CRM même si Emelia refuse
        }
    }
}
