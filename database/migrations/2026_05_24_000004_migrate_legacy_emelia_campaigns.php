<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Back-fill contacts who already have a campaign from the old 1:1 columns
        $contacts = DB::table('contacts')
            ->whereNotNull('emelia_campaign_id')
            ->whereNotNull('email')
            ->select('id', 'emelia_campaign_id', 'emelia_campaign_name', 'emelia_contact_id')
            ->get();

        foreach ($contacts as $contact) {
            // Upsert campaign in the new registry
            $campaignRow = DB::table('emelia_campaigns')
                ->where('emelia_id', $contact->emelia_campaign_id)
                ->first();

            if (! $campaignRow) {
                $campaignId = DB::table('emelia_campaigns')->insertGetId([
                    'emelia_id'  => $contact->emelia_campaign_id,
                    'name'       => $contact->emelia_campaign_name ?? $contact->emelia_campaign_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $campaignId = $campaignRow->id;
                // Update name if it was empty
                if (empty($campaignRow->name) && $contact->emelia_campaign_name) {
                    DB::table('emelia_campaigns')
                        ->where('id', $campaignId)
                        ->update(['name' => $contact->emelia_campaign_name, 'updated_at' => now()]);
                }
            }

            // Upsert pivot row
            $pivotExists = DB::table('contact_emelia_campaign')
                ->where('contact_id', $contact->id)
                ->where('emelia_campaign_id', $campaignId)
                ->exists();

            if (! $pivotExists) {
                DB::table('contact_emelia_campaign')->insert([
                    'contact_id'          => $contact->id,
                    'emelia_campaign_id'  => $campaignId,
                    'emelia_contact_id'   => $contact->emelia_contact_id,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }

            // Back-fill activities.emelia_campaign_id for this contact's emelia activities
            DB::table('activities')
                ->where('source', 'emelia')
                ->where('subject_type', 'App\Models\Contact')
                ->where('subject_id', $contact->id)
                ->whereNull('emelia_campaign_id')
                ->update(['emelia_campaign_id' => $campaignId]);
        }
    }

    public function down(): void
    {
        // Not reversible — data migrations are one-way
    }
};
