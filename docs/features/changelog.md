# Changelog — CRM Ultimate

## v4.0 — Sécurité + cohérence (2026-06-08) ✅ Prod

- Audit sécurité complet : 4 critiques + 12 améliorations
- Rate limiting sur les endpoints sensibles
- Signature HMAC webhook Emelia
- Owner scope sur les requêtes (isolation des données par commercial)
- Retry logic sur les appels API externes
- 11/11 tests PASS

## v3.9 — Blacklist UI (2026-05-25) ✅ Prod

- Badge `.chip.err` "Blacklisté" dans l'en-tête fiche contact
- Toggle "Masquer les blacklistés" sur la liste contacts (par défaut: masqués)
- Badge inline "Blacklisté" dans la table contacts
- 2 fichiers Blade déployés via SFTP ciblé

## v3.8 — Console Admin + Export CSV Segments + Rapports IA (2026-05-25) ✅ Prod

- Lien "Console Admin" dans la sidebar (admin uniquement)
- Export CSV dans le module Segments (bouton `.btn` standard)
- Rapports & Insights IA interactifs (bouton "Analyser avec l'IA", badge cache)

## v3.7 — Rapports & Analytics (2026-05-25) ✅ Prod

- Lien "Rapports" dans la sidebar (admin/manager)
- Refonte UI page `/reports` (cartes analytiques, Chart.js dynamique)
- Entonnoir de conversion + classement commerciaux (podium Or/Argent/Bronze)

## v3.6 — Micro-animations KPI (2026-05-25) ✅ Prod

- Effets de survol sur les 6 cartes KPI du Dashboard
- Élévation (`hover:-translate-y-1`), ombre portée colorée, opacité SVG animée

## v3.5 — Blacklist contacts + multi-intent Emelia (2026-05-25) ✅ Prod

- Colonnes `blacklisted_at` + `blacklist_reason` sur contacts
- Scopes `contactable()` / `blacklisted()` + méthode `blacklist()`
- Endpoint `POST /api/webhooks/emelia-intent` (HMAC, idempotence, 4 intents)
- Job `RemoveFromEmeliaCampaign` (3 mutations GraphQL)
- Workflow n8n mis à jour (12 nœuds, détection 4 intents par regex)
- 11/11 tests PASS

## v3.4 — Console Artisan Admin (2026-05-24) ✅ Prod

- Interface admin pour exécuter des commandes Artisan prédéfinies (whitelist stricte)
- 5 commandes autorisées : emelia-sync, ai-score, ai-precompute, queue-restart, cache-clear
- Job async avec polling 2s, terminal output, historique en base
- 9/9 tests locaux + 10/10 tests prod PASS

## v3.3 — IA Rapports (2026-05-24) ✅ Prod

- `AiInsightService::analyzeReports()` : analyse les 4 datasets du ReportController
- Endpoint `POST /web/ai/report-insights` (admin/manager)
- Cache Redis 1h, chargement async côté client
- 9/9 tests locaux + 10/10 tests prod PASS

## v3.2 — Export CSV Segments (2026-05-24) ✅ Prod

- Refonte `SegmentController::export()` : colonnes enrichies, chunk(200), BOM UTF-8
- 11/11 tests locaux + 10/10 tests prod PASS

## v3.1 — Page Rapports & Analytics (2026-05-24) ✅ Prod

- `ReportController` : 4 datasets (CA mensuel, entonnoir, classement commerciaux, activité hebdo)
- Cache Redis 30 min, route `GET /reports` admin/manager
- 7/7 tests locaux + 11/11 tests prod PASS

## v3.0 — Optimisations performances backend (2026-05-24) ✅ Prod

- **P0** : Backup pg_dump quotidien (cron 3h, rotation 7j)
- **P1** : 6 indexes DB + GIN trigram PostgreSQL
- **P1** : Dashboard cache Redis 5 min + N+1 supprimé
- **P2** : DealController dropdowns cachés (60s) + PipelineStage cache (1h/24h)
- **P2** : ProcessCsvImport batch lookup (mémoire constante)
- 28/28 tests PASS

## v2.8 — Enrichissement IA (2026-05-23) ✅ Prod

- **A1** : `contactContext()` enrichi avec stats Emelia
- **A2** : `dailySuggestions()` enrichi (4 sources : deals stagnants, closing soon, tâches overdue, replies Emelia 48h)
- **A3** : `ai:precompute` (pré-cache IA, schedule 03:00)
- **B1** : Score IA contacts persisté (`ai_score` 0-100, schedule 04:00)
- **B2** : Rédaction email IA (endpoint + modal Alpine)
- **B3** : Analyse sentiment replies Emelia (job + icône 😊/😐/😟)

## v2.7 — Optimisations N+1 (2026-05-23) ✅ Prod

- Eager loading avec select partiel sur les 3 index
- Cache Redis TTL 30s avec tags + invalidation model events
- `route:cache` ajouté à la procédure de déploiement

## v2.6 — Export CSV + Palette ⌘K (2026-05-23) ✅ Prod

- Export CSV contacts/companies (`fputcsv`, BOM UTF-8, custom fields)
- Palette ⌘K (composant Alpine, endpoint `/search/quick`, navigation clavier)
- `Api\InfoController` (remplace closure → `route:cache` possible)

## v2.5 — Sync events Emelia → activités (2026-05-22) ✅ Prod

- `EmeliaEventDispatcher` (service central partagé webhook + polling)
- `emelia:sync-contact-events` (polling artisan)
- Onglets Tout/Emelia fiche contact, badge sync/live
- Badge admin replies non lues dans sidebar
- 15/15 tests prod PASS

## v2.4 — Auto-synchronisation Emelia (2026-05-21) ✅ Prod

- `EmeliaSyncAllCampaigns` (schedule quotidien minuit)
- Bouton "Synchroniser maintenant" dans Settings
- Job `SyncEmeliaCampaignJob` (ShouldQueue)

## v2.3 — Intégration Emelia (2026-05-21) ✅ Prod

- `EmeliaService` (GraphQL pour ajout contacts)
- `EmeliaController` (Web) + `EmeliaWebhookController` (API)
- 6 types Activity Emelia, contact léger auto sur orphelin
- Sync prod : 1062 contacts, 10 nouvellement ajoutés

## v2.2 — Interface mapping HubSpot (2026-05-20) ✅ Prod

- Layout 2 colonnes (cartes + panneau sticky)
- Combobox searchable, création propriété custom inline
- Preview enrichi (samples, fill_rate, inferred_type)

## v2.1 — Import CSV v2 (2026-05-20) ✅ Prod

- Bug fix custom fields cache à l'import
- Stratégie doublons (skip/update/create)
- Validation requis côté serveur + client

## v2.0 — Déploiement production (2026-05-20) ✅ Prod

- Nginx + PHP-FPM + supervisord
- HTTPS via Caddy/Let's Encrypt
- Upload 50MB, trustProxies

## v1.6 — UX (2026-05-20) ✅ Prod

- Toggle tâches done/open
- Timeline interactive (filtres, tri, recherche, suppression)
- Tri de colonnes (▲/▼)

## v1.5 — CRUD + Custom Fields (2026-05-20) ✅ Prod

- CRUD Web contacts/companies/deals
- Bulk delete + sélection globale
- Custom fields dynamiques (renderer + validator)
- Import CSV (wizard 3 étapes)
- Corbeille + restauration
