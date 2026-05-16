<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── Users ─────────────────────────────────────────────────────────────

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@demo.com'],
            ['name' => 'Marie Dupont', 'password' => Hash::make('password'), 'role' => User::ROLE_ADMIN],
        );

        $user = User::query()->firstOrCreate(
            ['email' => 'user@demo.com'],
            ['name' => 'Thomas Leroy', 'password' => Hash::make('password'), 'role' => 'commercial'],
        );

        // ── Pipelines & Stages ────────────────────────────────────────────────

        $inbound = Pipeline::query()->firstOrCreate(['name' => 'Inbound'], ['is_default' => true]);
        $outbound = Pipeline::query()->firstOrCreate(['name' => 'Outbound'], ['is_default' => false]);
        $partners = Pipeline::query()->firstOrCreate(['name' => 'Partenaires'], ['is_default' => false]);

        $inboundStages = $this->createStages($inbound, [
            ['name' => 'Nouveau lead',    'position' => 10, 'probability' => 10],
            ['name' => 'Qualifié',        'position' => 20, 'probability' => 25],
            ['name' => 'Démo planifiée',  'position' => 30, 'probability' => 50],
            ['name' => 'Proposition',     'position' => 40, 'probability' => 70],
            ['name' => 'Négociation',     'position' => 50, 'probability' => 85],
            ['name' => 'Gagné',           'position' => 90, 'probability' => 100, 'is_won' => true],
            ['name' => 'Perdu',           'position' => 95, 'probability' => 0, 'is_lost' => true],
        ]);

        $outboundStages = $this->createStages($outbound, [
            ['name' => 'Prospection',     'position' => 10, 'probability' => 5],
            ['name' => 'Premier contact', 'position' => 20, 'probability' => 15],
            ['name' => 'Intérêt confirmé','position' => 30, 'probability' => 40],
            ['name' => 'Offre envoyée',   'position' => 40, 'probability' => 65],
            ['name' => 'Closing',         'position' => 50, 'probability' => 85],
            ['name' => 'Gagné',           'position' => 90, 'probability' => 100, 'is_won' => true],
            ['name' => 'Perdu',           'position' => 95, 'probability' => 0, 'is_lost' => true],
        ]);

        $partnerStages = $this->createStages($partners, [
            ['name' => 'Discussion',      'position' => 10, 'probability' => 20],
            ['name' => 'Accord verbal',   'position' => 20, 'probability' => 60],
            ['name' => 'Contrat en cours','position' => 30, 'probability' => 80],
            ['name' => 'Signé',           'position' => 90, 'probability' => 100, 'is_won' => true],
            ['name' => 'Abandonné',       'position' => 95, 'probability' => 0, 'is_lost' => true],
        ]);

        // ── Companies (20) ────────────────────────────────────────────────────

        $companiesData = [
            ['name' => 'Nexus Digital',        'domain' => 'nexus-digital.fr',    'industry' => 'SaaS',           'city' => 'Paris',      'country' => 'France'],
            ['name' => 'Greenleaf Bio',        'domain' => 'greenleaf.bio',       'industry' => 'Biotech',        'city' => 'Lyon',       'country' => 'France'],
            ['name' => 'Atelier Lumière',      'domain' => 'atelier-lumiere.com',  'industry' => 'Design',         'city' => 'Bordeaux',   'country' => 'France'],
            ['name' => 'TerraLogis',           'domain' => 'terralogis.fr',       'industry' => 'Immobilier',     'city' => 'Marseille',  'country' => 'France'],
            ['name' => 'CloudFirst',           'domain' => 'cloudfirst.io',       'industry' => 'Cloud/Infra',    'city' => 'Paris',      'country' => 'France'],
            ['name' => 'MedConnect',           'domain' => 'medconnect.fr',       'industry' => 'Santé',          'city' => 'Toulouse',   'country' => 'France'],
            ['name' => 'FinWise',              'domain' => 'finwise.eu',          'industry' => 'Fintech',        'city' => 'Paris',      'country' => 'France'],
            ['name' => 'EcoRoute',             'domain' => 'ecoroute.fr',         'industry' => 'Logistique',     'city' => 'Nantes',     'country' => 'France'],
            ['name' => 'DataPulse Analytics',  'domain' => 'datapulse.io',        'industry' => 'Data/IA',        'city' => 'Paris',      'country' => 'France'],
            ['name' => 'Artisan Réseaux',      'domain' => 'artisan-reseaux.fr',  'industry' => 'Telecom',        'city' => 'Lille',      'country' => 'France'],
            ['name' => 'Voyageur Pro',         'domain' => 'voyageurpro.com',     'industry' => 'Tourisme',       'city' => 'Nice',       'country' => 'France'],
            ['name' => 'Energis Solutions',    'domain' => 'energis.fr',          'industry' => 'Énergie',        'city' => 'Grenoble',   'country' => 'France'],
            ['name' => 'Patrimonia Conseil',   'domain' => 'patrimonia.fr',       'industry' => 'Finance',        'city' => 'Lyon',       'country' => 'France'],
            ['name' => 'FastRetail',           'domain' => 'fastretail.fr',       'industry' => 'E-commerce',     'city' => 'Paris',      'country' => 'France'],
            ['name' => 'OceanTech',            'domain' => 'oceantech.fr',        'industry' => 'Maritime',       'city' => 'Brest',      'country' => 'France'],
            ['name' => 'AgriSmart',            'domain' => 'agrismart.fr',        'industry' => 'Agritech',       'city' => 'Rennes',     'country' => 'France'],
            ['name' => 'Studio Pixel',         'domain' => 'studiopixel.fr',      'industry' => 'Média',          'city' => 'Paris',      'country' => 'France'],
            ['name' => 'LegalFlow',            'domain' => 'legalflow.fr',        'industry' => 'Legaltech',      'city' => 'Paris',      'country' => 'France'],
            ['name' => 'BuildUp',              'domain' => 'buildup.fr',          'industry' => 'Construction',   'city' => 'Strasbourg', 'country' => 'France'],
            ['name' => 'CyberShield',          'domain' => 'cybershield.eu',      'industry' => 'Cybersécurité',  'city' => 'Paris',      'country' => 'France'],
        ];

        $companies = collect();
        foreach ($companiesData as $c) {
            $companies->push(Company::query()->firstOrCreate(
                ['domain' => $c['domain']],
                array_merge($c, ['owner_id' => $admin->id, 'lifecycle_stage' => collect(['lead', 'customer', 'prospect'])->random()]),
            ));
        }

        // ── Contacts (50) ─────────────────────────────────────────────────────

        $contactsData = [
            ['first_name' => 'Sophie',    'last_name' => 'Martin',    'job_title' => 'CEO'],
            ['first_name' => 'Lucas',     'last_name' => 'Bernard',   'job_title' => 'CTO'],
            ['first_name' => 'Emma',      'last_name' => 'Dubois',    'job_title' => 'Directrice Marketing'],
            ['first_name' => 'Hugo',      'last_name' => 'Thomas',    'job_title' => 'Lead Developer'],
            ['first_name' => 'Léa',       'last_name' => 'Robert',    'job_title' => 'Product Manager'],
            ['first_name' => 'Nathan',    'last_name' => 'Richard',   'job_title' => 'DAF'],
            ['first_name' => 'Camille',   'last_name' => 'Petit',     'job_title' => 'DRH'],
            ['first_name' => 'Antoine',   'last_name' => 'Durand',    'job_title' => 'Responsable Achats'],
            ['first_name' => 'Manon',     'last_name' => 'Leroy',     'job_title' => 'Chargée de projet'],
            ['first_name' => 'Théo',      'last_name' => 'Moreau',    'job_title' => 'DevOps Engineer'],
            ['first_name' => 'Chloé',     'last_name' => 'Simon',     'job_title' => 'UX Designer'],
            ['first_name' => 'Maxime',    'last_name' => 'Laurent',   'job_title' => 'Sales Manager'],
            ['first_name' => 'Inès',      'last_name' => 'Lefebvre',  'job_title' => 'Avocate'],
            ['first_name' => 'Alexandre', 'last_name' => 'Michel',    'job_title' => 'DSI'],
            ['first_name' => 'Julie',     'last_name' => 'Garcia',    'job_title' => 'Customer Success'],
            ['first_name' => 'Raphaël',   'last_name' => 'David',     'job_title' => 'Architecte Cloud'],
            ['first_name' => 'Clara',     'last_name' => 'Bertrand',  'job_title' => 'Data Analyst'],
            ['first_name' => 'Gabriel',   'last_name' => 'Roux',      'job_title' => 'Responsable SI'],
            ['first_name' => 'Zoé',       'last_name' => 'Vincent',   'job_title' => 'Consultante'],
            ['first_name' => 'Arthur',    'last_name' => 'Fournier',  'job_title' => 'Ingénieur Commercial'],
            ['first_name' => 'Jade',      'last_name' => 'Morel',     'job_title' => 'Directrice Générale'],
            ['first_name' => 'Louis',     'last_name' => 'Girard',    'job_title' => 'Responsable Partenariats'],
            ['first_name' => 'Alice',     'last_name' => 'André',     'job_title' => 'Chef de Produit'],
            ['first_name' => 'Ethan',     'last_name' => 'Lefevre',   'job_title' => 'Développeur Full Stack'],
            ['first_name' => 'Lina',      'last_name' => 'Mercier',   'job_title' => 'Office Manager'],
            ['first_name' => 'Paul',      'last_name' => 'Dupont',    'job_title' => 'COO'],
            ['first_name' => 'Margot',    'last_name' => 'Lambert',   'job_title' => 'Chargée de Communication'],
            ['first_name' => 'Victor',    'last_name' => 'Bonnet',    'job_title' => 'Consultant IT'],
            ['first_name' => 'Sarah',     'last_name' => 'François',  'job_title' => 'Comptable'],
            ['first_name' => 'Adam',      'last_name' => 'Martinez',  'job_title' => 'Business Developer'],
            ['first_name' => 'Eva',       'last_name' => 'Legrand',   'job_title' => 'Responsable Juridique'],
            ['first_name' => 'Romain',    'last_name' => 'Garnier',   'job_title' => 'CEO'],
            ['first_name' => 'Nina',      'last_name' => 'Faure',     'job_title' => 'Growth Hacker'],
            ['first_name' => 'Tom',       'last_name' => 'Rousseau',  'job_title' => 'Technicien Réseau'],
            ['first_name' => 'Mia',       'last_name' => 'Blanc',     'job_title' => 'Marketing Manager'],
            ['first_name' => 'Oscar',     'last_name' => 'Henry',     'job_title' => 'Account Manager'],
            ['first_name' => 'Lola',      'last_name' => 'Chevalier', 'job_title' => 'Responsable Formation'],
            ['first_name' => 'Noé',       'last_name' => 'Muller',    'job_title' => 'Ingénieur QA'],
            ['first_name' => 'Ambre',     'last_name' => 'Perrin',    'job_title' => 'Assistante Direction'],
            ['first_name' => 'Sacha',     'last_name' => 'Robin',     'job_title' => 'Développeur Mobile'],
            ['first_name' => 'Anna',      'last_name' => 'Masson',    'job_title' => 'Analyste Financier'],
            ['first_name' => 'Enzo',      'last_name' => 'Fontaine',  'job_title' => 'Chef de Projet IT'],
            ['first_name' => 'Rose',      'last_name' => 'Barbier',   'job_title' => 'Directrice Artistique'],
            ['first_name' => 'Mathis',    'last_name' => 'Roger',     'job_title' => 'Support Engineer'],
            ['first_name' => 'Lou',       'last_name' => 'Clement',   'job_title' => 'Responsable CRM'],
            ['first_name' => 'Jules',     'last_name' => 'Caron',     'job_title' => 'VP Sales'],
            ['first_name' => 'Agathe',    'last_name' => 'Picard',    'job_title' => 'Responsable Qualité'],
            ['first_name' => 'Axel',      'last_name' => 'Schmitt',   'job_title' => 'SRE'],
            ['first_name' => 'Pauline',   'last_name' => 'Colin',     'job_title' => 'Chargée RH'],
            ['first_name' => 'Léon',      'last_name' => 'Vidal',     'job_title' => 'Directeur Technique'],
        ];

        $lifecycles = ['lead', 'mql', 'sql', 'opportunity', 'customer'];
        $contacts = collect();
        $owners = [$admin->id, $user->id];

        foreach ($contactsData as $i => $c) {
            $company = $companies[$i % $companies->count()];
            $email = strtolower($c['first_name']) . '.' . strtolower(str_replace(' ', '', $c['last_name'])) . '@' . $company->domain;

            $contact = Contact::query()->firstOrCreate(
                ['email' => $email],
                array_merge($c, [
                    'email' => $email,
                    'phone' => '+33 6 ' . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                    'lifecycle_stage' => $lifecycles[array_rand($lifecycles)],
                    'owner_id' => $owners[array_rand($owners)],
                ]),
            );

            $contact->companies()->syncWithoutDetaching([$company->id => ['role' => 'employee', 'is_primary' => true]]);
            $contacts->push($contact);
        }

        // ── Deals (30) ────────────────────────────────────────────────────────

        $dealNames = [
            'Licence SaaS annuelle', 'Migration cloud', 'Audit cybersécurité',
            'Refonte site web', 'Formation équipe dev', 'Intégration CRM',
            'Support premium 12 mois', 'Module analytics', 'API marketplace',
            'Consulting stratégie data', 'Pack onboarding', 'Extension licence',
            'Développement sur mesure', 'Infrastructure hybride', 'Maintenance évolutive',
            'Projet IoT pilote', 'Plateforme e-learning', 'Campagne acquisition',
            'Optimisation SEO', 'Déploiement ERP', 'Chatbot IA',
            'Gestion documentaire', 'Portail client', 'Solution emailing',
            'Module RH', 'Sécurisation données', 'App mobile interne',
            'Dashboard temps réel', 'Workflow automatisé', 'Plan de continuité',
        ];

        $pipelinesAndStages = [
            [$inbound, $inboundStages],
            [$outbound, $outboundStages],
            [$partners, $partnerStages],
        ];

        for ($i = 0; $i < 30; $i++) {
            // Distribute: 15 inbound, 10 outbound, 5 partners
            $pipelineIdx = $i < 15 ? 0 : ($i < 25 ? 1 : 2);
            [$pipeline, $stages] = $pipelinesAndStages[$pipelineIdx];

            $wonStage = collect($stages)->firstWhere('is_won', true);
            $lostStage = collect($stages)->firstWhere('is_lost', true);
            $openStages = collect($stages)->filter(fn ($s) => !$s->is_won && !$s->is_lost);

            // 20% won, 10% lost, 70% open
            if ($i % 10 < 7) {
                $stage = $openStages->random();
                $status = 'open';
            } elseif ($i % 10 < 9) {
                $stage = $wonStage;
                $status = 'won';
            } else {
                $stage = $lostStage;
                $status = 'lost';
            }

            $amount = collect([3500, 5000, 8000, 12000, 15000, 22000, 35000, 50000, 75000, 120000])->random();

            $deal = Deal::query()->firstOrCreate(
                ['name' => $dealNames[$i] . ' — ' . $companies[$i % 20]->name],
                [
                    'name' => $dealNames[$i] . ' — ' . $companies[$i % 20]->name,
                    'amount' => $amount,
                    'currency' => 'EUR',
                    'status' => $status,
                    'pipeline_id' => $pipeline->id,
                    'pipeline_stage_id' => $stage->id,
                    'owner_id' => $owners[array_rand($owners)],
                    'close_date' => now()->addDays(rand(-30, 90))->toDateString(),
                ],
            );

            // Associate company + contact
            $deal->companies()->syncWithoutDetaching([$companies[$i % 20]->id => ['role' => 'customer', 'is_primary' => true]]);
            $deal->contacts()->syncWithoutDetaching([$contacts[$i]->id => ['role' => 'primary']]);
            if (isset($contacts[$i + 1])) {
                $deal->contacts()->syncWithoutDetaching([$contacts[$i + 1]->id => ['role' => 'technical']]);
            }
        }

        // ── Activities (60) ───────────────────────────────────────────────────

        $activityTypes = ['note', 'call', 'email', 'task'];
        $activityTitles = [
            'note' => ['Point hebdo', 'Feedback client', 'Note interne', 'Résumé réunion', 'Info complémentaire'],
            'call' => ['Appel de qualification', 'Appel de suivi', 'Démo produit', 'Relance', 'Négociation finale'],
            'email' => ['Envoi proposition', 'Confirmation RDV', 'Relance sans réponse', 'Présentation offre', 'Mail de bienvenue'],
            'task' => ['Préparer la démo', 'Relancer dans 3j', 'Envoyer contrat', 'Planifier meeting', 'Mettre à jour le CRM'],
        ];

        $allDeals = Deal::all();
        for ($i = 0; $i < 60; $i++) {
            $type = $activityTypes[array_rand($activityTypes)];
            $deal = $allDeals->random();

            Activity::query()->create([
                'type' => $type,
                'title' => $activityTitles[$type][array_rand($activityTitles[$type])],
                'body' => null,
                'status' => $type === 'task' ? (rand(0, 1) ? 'done' : 'pending') : 'done',
                'due_at' => $type === 'task' ? now()->addDays(rand(-5, 14)) : null,
                'completed_at' => ($type === 'task' && rand(0, 1)) ? now()->subDays(rand(0, 7)) : null,
                'subject_type' => Deal::class,
                'subject_id' => $deal->id,
                'owner_id' => $owners[array_rand($owners)],
                'created_at' => now()->subDays(rand(0, 60)),
            ]);
        }

        // ── Segments prédéfinis (3) ───────────────────────────────────────────

        Segment::query()->firstOrCreate(
            ['name' => 'Contacts chauds'],
            [
                'entity_type' => 'contact',
                'rules' => [
                    'op' => 'AND', 'rules' => [
                        ['field' => 'rel.deals.status', 'operator' => 'eq', 'value' => 'won'],
                    ],
                ],
                'created_by' => $admin->id,
                'description' => 'Contacts associés à un deal gagné',
            ],
        );

        Segment::query()->firstOrCreate(
            ['name' => 'Sans activité 30j'],
            [
                'entity_type' => 'contact',
                'rules' => [
                    'op' => 'AND', 'rules' => [
                        ['field' => 'updated_at', 'operator' => 'days_ago_gt', 'value' => 30],
                    ],
                ],
                'created_by' => $admin->id,
                'description' => 'Contacts non modifiés depuis 30 jours',
            ],
        );

        Segment::query()->firstOrCreate(
            ['name' => 'Leads entrants'],
            [
                'entity_type' => 'contact',
                'rules' => [
                    'op' => 'AND', 'rules' => [
                        ['field' => 'lifecycle_stage', 'operator' => 'eq', 'value' => 'lead'],
                    ],
                ],
                'created_by' => $admin->id,
                'description' => 'Tous les contacts au stade lead',
            ],
        );

        $this->command->info('DemoSeeder: 2 users, 20 companies, 50 contacts, 30 deals, 60 activities, 3 segments.');
    }

    private function createStages(Pipeline $pipeline, array $stagesData): array
    {
        $stages = [];
        foreach ($stagesData as $data) {
            $stages[] = $pipeline->stages()->firstOrCreate(
                ['name' => $data['name'], 'pipeline_id' => $pipeline->id],
                $data,
            );
        }
        return $stages;
    }
}
