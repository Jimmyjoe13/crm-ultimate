<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin user ────────────────────────────────────────────────────────

        // `role` étant hors $fillable (anti-escalade), on l'assigne explicitement
        // via forceFill sur l'instance avant save (firstOrCreate ignorerait `role`).
        $admin = User::query()->where('email', 'admin@example.com')->first();
        if (! $admin) {
            $admin = User::createWithRole([
                'email' => 'admin@example.com',
                'name' => 'Admin CRM',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
            ]);
        }

        // ── Default pipeline ──────────────────────────────────────────────────

        $pipeline = Pipeline::query()->firstOrCreate(
            ['name' => 'Default Sales Pipeline'],
            ['is_default' => true],
        );

        $stageData = [
            ['name' => 'Prospecting', 'position' => 10, 'probability' => 10],
            ['name' => 'Qualified',   'position' => 20, 'probability' => 30],
            ['name' => 'Proposal',    'position' => 30, 'probability' => 60],
            ['name' => 'Won',         'position' => 40, 'probability' => 100, 'is_won' => true],
            ['name' => 'Lost',        'position' => 50, 'probability' => 0,   'is_lost' => true],
        ];

        $stages = [];
        foreach ($stageData as $stage) {
            $stages[$stage['name']] = $pipeline->stages()->firstOrCreate(['position' => $stage['position']], $stage);
        }

        // ── Demo companies (20) ───────────────────────────────────────────────

        $companies = Company::factory(20)->create(['owner_id' => $admin->id]);

        // ── Demo contacts (50) ────────────────────────────────────────────────

        $contacts = Contact::factory(50)->create(['owner_id' => $admin->id]);

        // Attach contacts to companies via pivot (90% → 1 company, 10% → 2)
        foreach ($contacts as $i => $contact) {
            $primaryCompany = $companies->random();
            $contact->companies()->attach($primaryCompany->id, [
                'role' => 'employee',
                'is_primary' => true,
            ]);

            // 10% of contacts belong to a second company
            if ($i % 10 === 0) {
                $secondCompany = $companies->except([$primaryCompany->id])->random();
                $contact->companies()->syncWithoutDetaching([$secondCompany->id => [
                    'role' => 'influencer',
                    'is_primary' => false,
                ]]);
            }
        }

        // ── Demo deals (30) ───────────────────────────────────────────────────

        $openStages = collect([$stages['Prospecting'], $stages['Qualified'], $stages['Proposal']]);
        $wonStage = $stages['Won'];
        $lostStage = $stages['Lost'];

        for ($i = 0; $i < 30; $i++) {
            $isWon = $i >= 24 && $i < 27;
            $isLost = $i >= 27;

            $stage = match (true) {
                $isWon => $wonStage,
                $isLost => $lostStage,
                default => $openStages->random(),
            };

            $deal = Deal::factory()->create([
                'pipeline_id' => $pipeline->id,
                'pipeline_stage_id' => $stage->id,
                'status' => $stage->is_won ? 'won' : ($stage->is_lost ? 'lost' : 'open'),
                'owner_id' => $admin->id,
            ]);

            // Associate 1-2 companies
            $primaryCompany = $companies->random();
            $deal->companies()->attach($primaryCompany->id, ['role' => 'customer', 'is_primary' => true]);

            if ($i % 5 === 0) {
                $secondCompany = $companies->except([$primaryCompany->id])->random();
                $deal->companies()->syncWithoutDetaching([$secondCompany->id => [
                    'role' => 'partner',
                    'is_primary' => false,
                ]]);
            }

            // Associate 1-3 contacts
            $dealContacts = $contacts->random(min(rand(1, 3), $contacts->count()));
            foreach ($dealContacts as $j => $contact) {
                $role = $j === 0 ? 'primary' : ($j === 1 ? 'technical' : 'billing');
                $deal->contacts()->syncWithoutDetaching([$contact->id => ['role' => $role]]);
            }
        }
    }
}
