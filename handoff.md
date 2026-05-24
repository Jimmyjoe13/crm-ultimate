# Handoff — CRM Ultimate

> [!IMPORTANT]
> **RÈGLE DE COHABITATION CLAUDE CODE / GEMINI :**
> Claude Code gère ce fichier (`handoff.md`) et toute la logique backend (PHP, migrations, services, commandes artisan, routes, jobs). Gemini gère `GEMINI_handoff.md` et les vues d'interface. Ne jamais empiéter sur le périmètre de l'autre. En cas de modification partagée d'un fichier de vue (ex. `contacts/show.blade.php`), déployer uniquement les fichiers concernés et ne pas écraser les modifications mutuelles.

---

## 1. Objectif

Transformer le CRM Ultimate (Laravel 13 + Blade + Alpine.js + PostgreSQL) en un outil complet :
suppression/sélection multiple des entités, propriétés personnalisées dynamiques sur les fiches,
réintégration des fonctionnalités IA dans l'UI, toggle de tâches, timeline d'activités,
corbeille/restauration, validation typée des champs personnalisés, import CSV avec mapping avancé,
et intégration d'outils tiers (Emelia emailing).

**v2.0 :** Déploiement production stabilisé — Nginx+PHP-FPM, HTTPS assets, import CSV 50MB.
**v2.1 :** Bug fix custom fields import + refonte UX import (stratégie doublons, validation requis, sélection globale contacts).
**v2.2 :** Interface mapping style HubSpot — cartes enrichies, combobox searchable, panneau sticky, création propriété inline.
**v2.3 :** Intégration Emelia — sync CRM→campagne via GraphQL, webhook events→timeline, contact léger auto sur orphelin, sync manuelle 1062 contacts.
**v2.4 :** Bug fix `in_emelia` (vérifiait `emelia_contact_id` null au lieu de `emelia_campaign_id`) + auto-sync quotidienne toutes campagnes + bouton "Sync maintenant" dans Settings + job queue `SyncEmeliaCampaignJob`.
**v2.5 :** Sync events Emelia → activités fiche contact — `EmeliaEventDispatcher` partagé (webhook + polling), `occurred_at` sur activities, polling `emelia:sync-contact-events`, onglets Tout/Emelia fiche contact, REPLIED → tâche owner + bump lifecycle lead→mql, badge admin replies non lues. **Validé 15/15 tests prod (2026-05-22).**
**v2.6 :** Export CSV contacts/entreprises (`fputcsv`, BOM UTF-8, custom fields labellés, filtre search propagé) + palette ⌘K (composant Alpine modale, endpoint `/search/quick`, navigation clavier ↑↓ + Entrée) + `Api\InfoController` (suppression closure → `route:cache` possible).
**v2.7 :** Optimisation N+1 — eager loading `with(['rel:id,name', ...])` dans les 3 index + cache Redis TTL 30 s `Cache::tags(['{entity}.index'])->remember(...)` + invalidation model events `saved`/`deleted` sur Contact/Company/Deal + `route:cache` ajouté à la procédure de redéploiement.
**v2.8 (A1+A2) :** Enrichissement IA — `contactContext()` inclut lifecycle_stage + stats Emelia agrégées (type/count/last_at par event) + nom de campagne. `dailySuggestions()` enrichi : 4 sources (deals stagnants, deals closing soon, tâches overdue, replies Emelia 48h) + réponse JSON `{suggestions, alerts, priorities}`. `ai-insight-card` affiche les 3 sections avec styles différenciés.
**v2.8 (B1) :** Score IA contacts persisté — migration `ai_score SMALLINT` + `ai_score_updated_at` sur `contacts`. `AiInsightService::scoreContact()` (heuristique + LLM → JSON `{score, rationale}`). Commande `ai:score-contacts {--limit=50} {--all} {--dry-run}`. Schedule quotidien 04:00. Badge coloré HSL sur `contacts/index` avec colonne triable `ai_score DESC NULLS LAST`.
**v2.8 (B2) :** Rédaction email IA — `AiInsightService::draftEmail(?contactId, ?dealId, intent)` → prompt contextualisé → JSON `{subject, body}`. Endpoint `POST /web/ai/draft-email`. Composant `<x-email-draft-modal>` Alpine : sélecteur d'intent (5 presets), génération, champs éditables subject+body, copie individuelle + "Tout copier". Bouton "Rédiger un email" dans la fiche contact (barre d'action) et la fiche deal (panneau IA).
**v2.8 (A3) :** Préchargement cache IA — commande `ai:precompute {--limit=50} {--contacts} {--dry-run}` : pré-calcule en nuit `summarizeDeal + scoreDeal + nextActionDeal` pour tous les deals ouverts (order by amount DESC), optionnellement `summarizeContact` pour contacts Emelia. Scheduler `dailyAt('03:00')` avant le score-contacts (04:00). Tests unitaires écrits (`tests/Feature/AiPrecomputeTest.php`, 9 tests). **Validé T1-T4 en prod (2026-05-23)** : commande listée, 3 options, dry-run exit 0, schedule 0 3 * * * confirmé.
**v2.8 (B3) :** Analyse sentiment replies Emelia — `AiInsightService::analyzeSentiment(text)` → LLM → JSON `{sentiment, score, summary}`. Job `AnalyzeReplySentiment` (ShouldQueue, tries=2) dispatché automatiquement par `EmeliaEventDispatcher` sur chaque `TYPE_EMAIL_REPLIED`. Icône sentiment (😊/😐/😟) dans `activity-timeline.blade.php` sur les activités `email_replied` ayant `metadata.sentiment`. Tests unitaires `tests/Feature/AnalyzeReplySentimentTest.php` (8 tests). **Validé T1-T6 prod (2026-05-23)**.

---

## 2. État actuel

### Ce qui fonctionne déjà

**Sécurité**

- Log de mot de passe retiré de `Web\AuthController::login`
- `RequireRole` middleware dual-mode : JSON 403 si AJAX, `abort(403)` sinon
- `<x-toast-container>` présent dans le layout actif — tous les `flash_toast` fonctionnent

**CRUD Web**

- Contacts : create / edit / destroy (soft delete) + flash toast
- Companies : idem
- Deals : edit / destroy (store existait déjà via modal)

**Bulk delete + sélection globale (v2.1)**

- `Alpine.store('bulk')` global avec `Set` + `selectAllMode` par entité (contact / company / deal)
- `<x-bulk-bar entity delete-action :total-count>` : barre flottante bottom-fixed
- Bouton "Tout sélectionner (N)" dans la toolbar contacts : sélectionne TOUS les contacts de la base (pas juste la page courante), indépendamment de la pagination
- Bannière de confirmation dans le tbody quand le mode sélection globale est actif
- `bulkDestroy` : gère `select_all=1` → suppression douce de TOUS les contacts sans passer les IDs
- Checkboxes header + row sur les 3 index, visibles admin/manager uniquement

**Custom fields**

- `CustomFieldRenderer::forEntity($type)` cache Redis 60 s + `displayValue($field, $raw)`
- **Cache invalidé via model events** (`saved`/`deleted` sur `CustomField`) → `Cache::forget("custom_fields.{$entity_type}")` — bug critique résolu en v2.2
- Composants Blade : `<x-form-field>`, `<x-custom-fields-form>`, `<x-custom-fields-show>`
- `/settings/fields` : edit inline Alpine + delete par ligne
- Intégré dans tous les create/edit + affichage labellé dans les show

**`CustomValueValidator`**

- `validationRules(string $entityType)` : règles Laravel per-field
- `cast(string $entityType, array $values)` : cast `float`, `Y-m-d`, `bool`, `trim string`
- Câblé dans Contact/Company/DealController (store + update)

**Import CSV (v2.1 + v2.2)**

- Wizard 3 étapes : upload → mapping → suivi progression
- **Bug fix (v2.2)** : `CustomValueValidator::cast()` utilisait le cache Redis pour résoudre les champs custom. Si un champ était créé après la population du cache, il était silencieusement ignoré → `custom_values` jamais écrit. Résolu par invalidation automatique du cache dans `CustomField::boot()`
- `ProcessCsvImport` (ShouldQueue) : partition `$combined` → core fields via `$fillable` + custom fields via `CustomValueValidator::cast()`
- Stratégie doublons : `skip` (par défaut) / `update` (merge `custom_values`) / `create`
- `duplicate_strategy` stocké dans `import_jobs` (migration `2026_05_20_000001`)
- Validation côté serveur des champs requis (422 avec `missing[]`) + côté client (bouton désactivé + tooltip)
- Polling statut job toutes les 1500 ms, barre de progression, erreurs par ligne
- Statut `completed` / `completed_with_errors` / `failed` — bug `'done'` vs `'completed'` résolu en v2.1

**Interface mapping HubSpot (v2.2)**

- **Layout 2 colonnes** : cartes empilées à gauche (flex-1), panneau sticky à droite (w-72)
- **Une carte par colonne CSV** avec :
  - Icône type inféré (email/phone/date/number/url/text/boolean/select) — SVG inline par type
  - Jusqu'à 4 pills d'échantillons CSV + taux de remplissage si < 80%
  - Badge état : `auto` (vert) / `modifié` (bleu) / `requis !` (rouge) / `ignoré` (gris)
  - Bouton `×` par carte pour basculer "Ne pas importer" (opacité réduite + badge)
- **Combobox searchable** par carte :
  - Dropdown avec champ recherche filtrant label + clé
  - Options groupées : **Champs standard** / **Société liée** / **Propriétés personnalisées**
  - Badge `requis` (rouge) et `✦` (accent) sur chaque option, icône type, check sur la sélection active
  - Dropdown non contraint en hauteur (fix overflow) — liste scrollable max-height:210px indépendante
- **Panneau sticky droit** :
  - Barre de progression (colonnes mappées / total)
  - Checklist champs requis avec ✓ (vert) ou ⚠ (rouge) par champ
  - Stratégie doublons (3 radios avec descriptions)
  - Bouton "Lancer l'import →" désactivé si requis non mappés
- **Création propriété custom inline** :
  - Bouton "+ Créer une propriété personnalisée" en bas de chaque dropdown
  - Modal : nom pré-rempli avec le nom de la colonne CSV, type, **sélecteur d'entité (Contact / Société / Deal)**
  - Lors d'un import contact, propose Contact et Société comme cibles
  - POST `/imports/quick-field` → crée le `CustomField` en base + invalide le cache + retourne le champ pour auto-sélection dans le dropdown
  - Si créé pour la même entité que l'import → ajouté aux `availableFields` + mappé automatiquement
  - Si créé pour une autre entité (ex: Société pendant import Contact) → créé en base mais non ajouté au dropdown courant

**Preview enrichi (`/imports/preview`)**

- Retourne `columns[]` : `{header, samples[], fill_rate, inferred_type}` — samples basés sur 10 premières lignes, dédupliqués, max 5 valeurs non-vides
- `available_fields[]` : `{key, label, type, group, field_type, required}` — `group` = `standard` | `company` | `custom`, `field_type` = type natif du champ
- `inferred_type` détecté par regex sur échantillons (seuil 60%) : email, phone, url, date, number, text

**Fiche contact (v2.1)**

- `lead_status` et `owner.name` affichés dans la card "Informations"
- `ContactController::show()` eager-load la relation `owner`
- Custom fields visibles via `<x-custom-fields-show>` (inclut automatiquement les champs importés)

**IA Web**

- `AiInsightService` (logique partagée Web ↔ API) — summarizeDeal, nextActionDeal, scoreDeal, summarizeContact, summarizeCompany, dailySuggestions
- Cache 24 h par entité ; `?fresh=1` bypass cache (admin/manager)
- `<x-ai-insight-card endpoint title>` Alpine intégré dans deals/show, contacts/show, companies/show, dashboard

**UX v1.6**

- Toggle tâches done/open : `ActivityController::toggleDone`
- `<x-activity-timeline showComposer>` : composer + listing chrono avec toggle tâche inline
- Onglets Informations / Activité sur fiches contact et company

**Tri de colonnes**

- `<x-sort-th column label :sort :dir>` avec ▲/▼ et URL preserving
- Contacts : `last_name` (défaut), `email`, `created_at`
- Companies : `name`, `industry`, `city`, `created_at`
- Deals : `close_date`, `name`, `amount`

**Corbeille**

- `GET /trash` : soft-deleted contacts / companies / deals (onglets Alpine)
- Restauration `POST /*/restore` — admin/manager uniquement

**Intégration Emelia (v2.3)**

- `EmeliaService` : `listCampaigns()` + `findCampaign()` + `addContactToCampaign()` via **GraphQL** (pas REST — voir §4)
- `EmeliaController` (Web) : `GET /emelia/campaigns` + `POST /contacts/{contact}/emelia` + `GET /contacts/{contact}/emelia/status`
- `EmeliaWebhookController` : `POST /api/webhooks/emelia` — HMAC SHA256 optionnel, idempotence `external_id`, contact léger auto sur orphelin
- 6 types Activity : `email_sent/opened/clicked/replied/bounced/unsubscribed` — events UPPERCASE (`SENT`, `OPENED`, `FIRST_OPEN`…)
- Migrations : `emelia_contact_id` + `emelia_campaign_id` + `emelia_campaign_name` sur `contacts` + `source`/`external_id`/`metadata` sur `activities`
- Modal Alpine fiche contact : fetch campagnes + POST add + badge + panel stats (sent/opened/clicked…)
- Artisan `emelia:sync-campaign {campaign_id} {--dry-run} {--only-linked}` — sync en masse CRM→Emelia
- **Sync prod exécutée (2026-05-21) :** campagne `69eb1cca5033df0a8663a88e` ("acquisition-agence-marketing") — 1062 contacts CRM traités : 1052 déjà dans la campagne Emelia (skip + champs CRM mis à jour), 10 nouvellement ajoutés, 0 erreurs
- 19 tests (webhook + service + controller) — **211 tests verts** au total

### Découverte API Emelia importante

- `POST /campaigns/{id}/contacts` **n'existe pas** dans l'API REST Emelia → répond "Cannot POST" (404 Express)
- La bonne méthode : **GraphQL mutation** `addContactToCampaignHook(id: ID!, contact: JSON!) → ID!`
- La mutation retourne l'`_id` Emelia du contact créé
- La mutation n'est **pas idempotente** — retourne `"This contact is already included in this campaign"` si déjà présent
- `GET /campaigns` (REST) fonctionne correctement pour lister les campagnes
- Introspection GraphQL désactivée en prod (`__schema` bloqué) — mais les erreurs de validation révèlent les champs

### Dernière action effectuée

**v2.9 — Fix webhook n8n (2026-05-23)** : Nœud "Normalize Event" du workflow `q4GXMH5Qzjz9H6AZ` corrigé via PUT API n8n. `d.contact?.email`, mapping campagne nom→UUID, `event_id` déterministe par concaténation. Idempotence testée et confirmée en prod. Webhook Emelia → n8n → CRM opérationnel.

**Bug prod résolu (2026-05-23)** : 500 sur toutes les pages après redéploiement — `Vite manifest not found`. Cause : `public/build/` copié avec permissions `drwx------` (uid 1001) → PHP-FPM (`www-data`) ne pouvait pas entrer le répertoire. Fix permanent : `chmod -R 755 /var/www/html/public/build` ajouté au `CMD` de `Dockerfile.prod` + rebuild complet → permissions `www-data:www-data drwxr-xr-x` confirmées. Site opérationnel HTTP 200.

---

## 3. Fichiers concernés

### Contrôleurs Web

| Fichier                                                       | Rôle                                                                                      |
| ------------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| `app/Http/Controllers/Web/AuthController.php`                 | Login — log password supprimé                                                             |
| `app/Http/Controllers/Web/ContactController.php`              | CRUD + bulkDestroy (select_all) + CustomValueValidator + owner eager-load                 |
| `app/Http/Controllers/Web/CompanyController.php`              | CRUD + bulkDestroy + CustomValueValidator                                                 |
| `app/Http/Controllers/Web/DealController.php`                 | Edit + destroy + bulkDestroy + CustomValueValidator + auto-link company sur attachContact |
| `app/Http/Controllers/Web/ImportController.php`               | Wizard CSV : preview enrichi (columns/inferred_type/groups), store, status, quickField    |
| `app/Http/Controllers/Web/TrashController.php`                | index + restore × 3 entités                                                               |
| `app/Http/Controllers/Web/ActivityController.php`             | index + toggleDone + store + destroy (avec contrôle droits)                               |
| `app/Http/Controllers/Web/AiController.php`                   | 4 endpoints IA Web                                                                        |
| `app/Http/Controllers/Web/EmeliaController.php`               | campaigns + status + addContact + syncNow + syncContact (bouton sync fiche contact)       |
| `app/Http/Controllers/Web/NotificationController.php`         | markEmeliaRepliesSeen                                                                     |
| `app/Http/Controllers/Web/Settings/CustomFieldController.php` | CRUD + cache invalidation                                                                 |
| `app/Http/Controllers/Api/AiController.php`                   | Refactorisé → délègue à AiInsightService                                                  |
| `app/Http/Controllers/Api/InfoController.php`                 | Remplace la closure `GET /api/v1/` → `route:cache` désormais possible                    |

### Jobs / Services / Support

| Fichier                                 | Rôle                                                                                              |
| --------------------------------------- | ------------------------------------------------------------------------------------------------- |
| `app/Jobs/ProcessCsvImport.php`         | Import CSV : partition core/custom, stratégie doublons, merge custom_values                       |
| `app/Models/ImportJob.php`              | Modèle import — fillable `duplicate_strategy`                                                     |
| `app/Models/CustomField.php`            | Model events `saved`/`deleted` → Cache::forget (fix bug v2.2)                                     |
| `app/Models/Activity.php`               | `occurred_at` + `source` + `external_id` dans fillable + casts                                    |
| `app/Models/User.php`                   | `emelia_replies_last_seen` dans fillable + casts                                                  |
| `app/Services/AiInsightService.php`     | Logique IA partagée Web ↔ API + `analyzeSentiment()`                                              |
| `app/Services/LlmService.php`           | Client HTTP OpenRouter                                                                            |
| `app/Services/EmeliaService.php`        | `getContactEvents()` : timestamps Emelia en **millisecondes** → `Carbon::createFromTimestampMs()` |
| `app/Support/CustomFieldRenderer.php`   | Cache Redis 60s + formatage                                                                       |
| `app/Support/CustomValueValidator.php`  | Validation + cast per-type des custom_values                                                      |
| `app/Support/EmeliaEventDispatcher.php` | Service central : dispatch Activity + actions REPLIED (tâche + lifecycle + sentiment job)          |

### Routes

| Fichier          | Rôle                                                                                                       |
| ---------------- | ---------------------------------------------------------------------------------------------------------- |
| `routes/web.php` | Toutes les routes Web + `POST /contacts/{contact}/emelia/sync` + `POST /notifications/emelia-replies/seen` |

### Migrations

| Fichier                                                                           | Rôle                                                                      |
| --------------------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| `database/migrations/2026_05_20_000001_add_duplicate_strategy_to_import_jobs.php` | Colonne `duplicate_strategy VARCHAR(16) DEFAULT 'skip'` sur `import_jobs` |
| `database/migrations/2026_05_22_000001_add_occurred_at_to_activities.php`         | `timestamp occurred_at nullable index` sur `activities`                   |
| `database/migrations/2026_05_22_000002_add_emelia_replies_last_seen_to_users.php` | `timestamp emelia_replies_last_seen nullable` sur `users`                 |

### Commandes Artisan

| Fichier                                            | Rôle                                                                           |
| -------------------------------------------------- | ------------------------------------------------------------------------------ |
| `app/Console/Commands/EmeliaSyncAllCampaigns.php`  | Liste toutes les campagnes Emelia + sync-campaign + chaîne sync-contact-events |
| `app/Console/Commands/EmeliaSyncCampaign.php`      | Sync CRM→Emelia + résolution `emelia_contact_id` pour les "already included"   |
| `app/Console/Commands/EmeliaSyncContactEvents.php` | Polling events Emelia → Activity (résout les IDs manquants automatiquement)    |
| `app/Console/Commands/AiScoreContacts.php`         | Score IA contacts (0-100) — `--limit`, `--all`, `--dry-run` ; schedule 04:00   |
| `app/Console/Commands/AiPrecompute.php`            | Préchauffe cache IA deals actifs — `--limit`, `--contacts`, `--dry-run` ; schedule 03:00 |

### Vues — composants

| Fichier                                                   | Rôle                                                                                 |
| --------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| `resources/views/components/bulk-bar.blade.php`           | Barre bulk delete avec mode sélection globale                                        |
| `resources/views/components/import-stepper.blade.php`     | Stepper 3 étapes (lit `step` depuis Alpine parent)                                   |
| `resources/views/components/form-field.blade.php`         | Input générique typé                                                                 |
| `resources/views/components/custom-fields-form.blade.php` | Section custom fields dans forms                                                     |
| `resources/views/components/custom-fields-show.blade.php` | Affichage labellé custom fields                                                      |
| `resources/views/components/ai-insight-card.blade.php`    | Card IA Alpine fetch                                                                 |
| `resources/views/components/activity-timeline.blade.php`  | Timeline + composer + `occurred_at` + filtre source + badges sync/live + suppression |
| `resources/views/components/sort-th.blade.php`            | En-tête triable ▲/▼                                                                  |
| `resources/views/components/app-shell.blade.php`          | Badge admin replies non lues (sidebar Contacts) + bouton ⌘K ouvre la palette        |
| `resources/views/components/command-palette.blade.php`    | Palette ⌘K — Alpine `cmdPalette()`, endpoint `/search/quick`, nav clavier ↑↓+Entrée  |

### Vues — pages

| Fichier                                          | Rôle                                                            |
| ------------------------------------------------ | --------------------------------------------------------------- |
| `resources/views/pages/contacts/index.blade.php` | Bulk + sélection globale + toolbar "Tout sélectionner (N)"      |
| `resources/views/pages/contacts/show.blade.php`  | Onglets Tout/Emelia + bouton Sync Emelia + bouton Voir → réparé |
| `resources/views/pages/imports/create.blade.php` | Wizard complet HubSpot-style (2600 lignes JS/Blade)             |

### Tests

| Fichier                                         | Rôle                                                 |
| ----------------------------------------------- | ---------------------------------------------------- |
| `tests/Feature/BulkActionsTest.php`             | Bulk delete × 3 entités + 403 viewer                 |
| `tests/Feature/WebContactControllerTest.php`    | CRUD contact Web                                     |
| `tests/Feature/ImportCustomFieldsTest.php`      | custom_values écrits à l'import + cast types         |
| `tests/Feature/ImportRequiredFieldsTest.php`    | Validation requis preview + store 422                |
| `tests/Feature/ImportDuplicateStrategyTest.php` | skip/update/create + merge custom_values             |
| `tests/Feature/WebImportControllerTest.php`     | Preview + store + duplicate_strategy                 |
| `tests/Feature/EmeliaEventDispatcherTest.php`   | REPLIED → tâche + lifecycle + idempotence (8 tests)  |
| `tests/Feature/EmeliaSyncContactEventsTest.php` | Polling + idempotence + dry-run + mock API (7 tests) |
| `tests/Feature/ActivityDeleteTest.php`          | Suppression activité + contrôle droits               |

---

## 4. Ce qui a échoué

### CSRF dans les tests AJAX Web

**Ce qui fonctionne :** `->post($url, ['_token' => 'test'])` avec `withSession(['_token' => 'test'])` dans `withAuth()`.

### `assertSee()` avec apostrophes dans les vues

**Solution :** `assertSeeText("Modifier l'entreprise")` (compare le texte décodé, pas le HTML encodé).

### `assertSame` sur custom_values numériques post-DB

**Solution :** `assertEquals(7500.0, ...)` — JSON round-trip transforme `7500.0` en `int(7500)`, `==` passe.

### Bug cache custom fields à l'import (résolu v2.2)

**Symptôme :** Custom fields mappés à l'import silencieusement ignorés en production, tests verts.
**Cause :** `CustomValueValidator::cast()` appelle `CustomFieldRenderer::forEntity()` qui lit un cache Redis TTL 60s. Quand un `CustomField` était créé après la population du cache, `cast()` ne le voyait pas et droppait silencieusement la valeur. `ProcessCsvImport` lisait les clés directement en DB (correct) mais `cast()` lisait le cache (stale).
**Pourquoi les tests passaient :** `Cache::flush()` dans le setUp des tests garantissait un cache frais.
**Fix :** Model events `saved`/`deleted` sur `CustomField` → `Cache::forget("custom_fields.{$entity_type}")`.

### API Emelia REST `POST /campaigns/{id}/contacts` inexistante (résolu v2.3)

**Symptôme :** 100% des contacts échouaient avec `"Cannot POST /campaigns/..."` (Express 404).
**Cause :** Cet endpoint REST n'existe pas dans l'API Emelia. Documenté nulle part.
**Fix :** Utiliser la mutation GraphQL `addContactToCampaignHook(id: ID!, contact: JSON!)` sur `/graphql`.
**La mutation n'est pas idempotente** — si le contact est déjà dans la campagne : `RuntimeException("This contact is already included...")`. Géré dans `EmeliaSyncCampaign` : `str_contains($e->getMessage(), 'already included')` → skip + update champs CRM sans compter comme erreur.

### `subject_type` polymorphique doit être un FQCN (résolu v2.3)

**Symptôme :** 500 sur le dashboard après les premiers webhooks Emelia.
**Fix :** `'subject_type' => Contact::class` (= `'App\Models\Contact'`). Corriger les enregistrements corrompus en base : `UPDATE activities SET subject_type = 'App\Models\Contact' WHERE source = 'emelia' AND subject_type = 'contact'`.

### Events Emelia sont en UPPERCASE sans préfixe (résolu v2.3)

**Symptôme :** Webhooks reçus mais aucune Activity créée (retour 422).
**Fix :** `match(strtoupper($request->input('event', '')))` avec les deux formes dans les cases.

### Contacts sans `emelia_contact_id` invisibles au polling (résolu v2.5)

**Symptôme :** 924/1062 contacts avaient un `emelia_campaign_name` mais pas d'`emelia_contact_id`. La commande `sync-contact-events` les ignorait silencieusement.
**Cause :** Dans `EmeliaSyncCampaign`, le chemin "already included" sauvegardait `emelia_campaign_id` + `emelia_campaign_name` mais **pas** `emelia_contact_id`.
**Fix :** `EmeliaSyncContactEvents` résout maintenant l'ID via `getContactByEmail()` avant le polling. `EmeliaSyncCampaign` résout aussi l'ID dans le chemin "already included". Résultat : 138 → 1016 contacts avec ID résolu après une sync.

### Timestamps Emelia en millisecondes (résolu v2.5)

**Symptôme :** `getContactEvents()` ne créait aucune activité malgré des données `lastReplied`/`lastOpen` non nulles.
**Cause :** L'API Emelia renvoie `lastContacted`/`lastOpen`/`lastReplied` en **millisecondes** (ex: `1778837804955`). `Carbon::parse(1778837804955)` interprète comme des secondes → date en l'an 57000 → exception ou date invalide → activité non créée.
**Fix :** `Carbon::createFromTimestampMs((int) $ms)` dans `EmeliaService::getContactEvents()`.

### Webhook Emelia non disponible dans le plan actuel

**Symptôme :** Pas de compteurs temps réel, pas de badge `live`.
**Cause :** Le plan Emelia actuel ne propose pas de configuration webhook via l'interface.
**Workaround :** Bouton "Sync" dans le panel Emelia de la fiche contact (`POST /contacts/{contact}/emelia/sync`) + polling daily automatique. Les activités créées ont `metadata.synthetic = true` et le badge `sync`.

### `attachContact` n'auto-linkait pas la company (résolu v2.5)

**Symptôme :** Quand un contact est ajouté à un deal existant via le bouton "Associer un contact", la company du contact n'apparaît pas dans le deal.
**Cause :** `DealController::attachContact()` manquait le bloc d'auto-association company (présent dans `store()` mais oublié dans `attachContact()`).
**Fix :** Après `contacts()->syncWithoutDetaching()`, récupérer `$contact->companies()->first()` et attacher la company si elle n'est pas déjà liée au deal.

---

## 5. État du déploiement production

### Infrastructure — VPS 51.38.99.226 (Ubuntu 22.04)

**URL : https://crm.nana-intelligence.fr** (HTTPS Let's Encrypt via Caddy)

#### Architecture

- Caddy Docker partagé sur réseau `web` (`/home/jimmy/docker/docker-compose.yml`)
- Repo CRM : `/home/jimmy/crm-ultimate/`
- Compose prod : `docker compose -f /home/jimmy/crm-ultimate/docker-compose.prod.yml`

#### Conteneurs prod

| Conteneur      | Image                | Rôle                                      |
| -------------- | -------------------- | ----------------------------------------- |
| `crm-app`      | `crm-ultimate-app`   | Nginx:8080 + PHP-FPM:9000 via supervisord |
| `crm-queue`    | `crm-ultimate-queue` | Worker queue Redis                        |
| `crm-postgres` | `postgres:17-alpine` | Base de données (volume `pgdata`)         |
| `crm-redis`    | `redis:7-alpine`     | Cache + sessions + queue                  |

#### Procédure de redéploiement — IMPORTANT

Le code PHP et les vues Blade sont **embarqués dans les images Docker** au moment du `build`.
**Un simple SCP + `view:clear` ne met PAS à jour le code en production.**

```bash
# Workflow complet à chaque déploiement :
# 1. Copier les fichiers modifiés sur le VPS (SCP ou paramiko depuis Claude Code)
# 2. Rebuilder les images
cd ~/crm-ultimate
docker compose -f docker-compose.prod.yml build app queue
docker compose -f docker-compose.prod.yml up -d app queue
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T app php artisan config:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan view:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan cache:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache
```

#### Règles de cohabitation avec Gemini — CRITIQUE

Le VPS (`/home/jimmy/crm-ultimate/`) est un **dossier flat sans git** : chaque assistant y SCP ses fichiers directement.

**Périmètre Claude Code (ce fichier) :** backend PHP (`app/`, `routes/`, `database/`, `tests/`, `config/`, `docker/`).

**Périmètre Gemini (`GEMINI_handoff.md`) :** vues UI (`resources/views/pages/`, `resources/views/components/activity-timeline.blade.php`), services front (`app/Services/SegmentQueryEngine.php`, `app/Http/Controllers/Web/SegmentController.php`).

**Règles impératives :**
- **Ne jamais SCP** les fichiers du périmètre Gemini lors d'un déploiement Claude Code.
- Le `docker compose build` recompile les assets Vite depuis les fichiers VPS en place (Tailwind scanne toutes les blade files) → les assets CSS/JS regénérés incluent le travail de Gemini automatiquement. Les hashes changent à chaque rebuild, c'est normal.
- Si un fichier est partagé (ex. un contrôleur que les deux modifient), synchroniser via le repo local avant de SCP.

#### Tests en production (sans PHPUnit disponible)

`composer install --no-dev` dans l'image → PHPUnit et `tinker` sont absents.
Utiliser des scripts PHP temporaires + `docker cp` :

```bash
docker cp /home/jimmy/crm-ultimate/_test.php crm-app:/var/www/html/_test.php
docker exec crm-app php /var/www/html/_test.php
docker exec crm-app rm /var/www/html/_test.php
```

#### Variables `.env` VPS

```
APP_URL=https://crm.nana-intelligence.fr
ASSET_URL=https://crm.nana-intelligence.fr
TRUSTED_PROXIES=*
OPENROUTER_API_KEY=sk-or-v1-...   ← configuré ✓
OPENROUTER_MODEL=anthropic/claude-haiku-4-5
EMELIA_API_KEY=5jwwzTUNnb0IrdDEJtMVe1D0h8nsOgECa07X73IJsLozKq6U   ← configuré ✓
EMELIA_WEBHOOK_SECRET=bc36d8f114a744e03e578c1b4f9380fbe416de780da2a8c4cebb271ee7a5d08e   ← configuré ✓
EMELIA_BASE_URL=https://api.emelia.io   ← configuré ✓
```

#### Compte admin de production

```
Email    : admin@example.com
Password : password   ← à changer
```

#### Bug connu : `route:cache` et closure dans `routes/api.php`

`Route::get('/', fn() => ...)` est une closure → Laravel refuse de cacher les routes.
Fix : déplacer dans `Api\InfoController`. Impact actuel : nul (~1-2ms/requête).

---

## 6. v2.4 : Auto-synchronisation Emelia (LIVRÉ — 2026-05-21)

### Fichiers livrés v2.4

| Fichier                                           | Statut                                                                                          |
| ------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| `app/Http/Controllers/Web/EmeliaController.php`   | Bug fix `in_emelia` : vérifie `emelia_campaign_id OR emelia_contact_id`                         |
| `app/Console/Commands/EmeliaSyncAllCampaigns.php` | Nouveau — liste toutes les campagnes Emelia puis appelle `emelia:sync-campaign` pour chacune    |
| `app/Jobs/SyncEmeliaCampaignJob.php`              | Nouveau — job queue qui appelle `emelia:sync-all-campaigns`                                     |
| `routes/console.php`                              | Schedule quotidien : `emelia:sync-all-campaigns --only-linked` à minuit, `withoutOverlapping()` |
| `routes/web.php`                                  | Route `POST /settings/emelia/sync`                                                              |
| `resources/views/pages/settings/fields.blade.php` | Section Emelia avec bouton "Synchroniser maintenant"                                            |

### Option C — Hybrid (choisie et implémentée)

1. ✅ Scheduled daily sync pour toutes les campagnes (`emelia:sync-all-campaigns`)
2. ✅ Bouton "Synchroniser maintenant" dans Settings
3. ✅ Bouton "Sync" par fiche contact (`POST /contacts/{contact}/emelia/sync`)
4. ⚠️ Webhook temps réel non disponible (limitation plan Emelia)

### Campagnes Emelia connues

| Campaign ID                | Nom                          | Contacts Emelia          |
| -------------------------- | ---------------------------- | ------------------------ |
| `69eb1cca5033df0a8663a88e` | acquisition-agence-marketing | ~1062 (syncs 2026-05-21) |

---

## 7. v2.5 : Sync events Emelia → activités fiche contact (LIVRÉ + validé — 2026-05-22)

### Résultats tests prod (15/15)

- T1 — Colonnes DB (`occurred_at`, `emelia_replies_last_seen`) : PASS
- T2 — Classes PHP autoloadées (`EmeliaEventDispatcher`, `EmeliaSyncContactEvents`) : PASS
- T3 — Routes (`emelia-replies/seen`, `webhooks/emelia`) : PASS
- T4 — Schedule (`emelia:sync-all-campaigns`) : PASS
- T5 — Intégration REPLIED : Activity créée + tâche + lifecycle lead→mql + occurred_at : PASS
- T6 — `emelia:sync-contact-events --dry-run` exit 0 : PASS
- T7 — HTTP `/login 200` + webhook `200` : PASS

### Ce qui est livré

#### Architecture hybride (webhook + polling)

- **`app/Support/EmeliaEventDispatcher.php`** — service central partagé :
  - `dispatch(Contact, type, payload, occurredAt, externalId, title, body)` : crée l'`Activity` via `updateOrCreate` (idempotence) si `externalId` non null, sinon `create` direct
  - `typeFromEmeliaEvent(string)` : mapping UPPERCASE Emelia → constante `Activity::TYPE_EMAIL_*`
  - Actions secondaires sur `REPLIED` : tâche follow-up pour le owner (due demain 9h) + bump `lifecycle_stage` lead→mql

- **`app/Console/Commands/EmeliaSyncContactEvents.php`** — polling artisan :
  - `emelia:sync-contact-events {--only-linked} {--contact=} {--dry-run}`
  - Résout automatiquement `emelia_contact_id` manquant via `getContactByEmail()` avant le polling
  - `external_id = 'emelia:' + sha256("{emeliaContactId}:{type}:{isoDate}")` — idempotence totale
  - Rate-limit Emelia respecté (`usleep(220_000)`)
  - Chaîné automatiquement depuis `emelia:sync-all-campaigns` (schedule daily existant)

- **`app/Services/EmeliaService::getContactEvents()`** :
  - Timestamps Emelia **en millisecondes** → `Carbon::createFromTimestampMs()` ← CRITIQUE
  - Tente GraphQL enrichi `contact { activities { type date } }` (fallback gracieux)
  - Fallback sur agrégats : `lastContacted→SENT`, `lastOpen→OPENED`, `lastReplied→REPLIED`, `status BOUNCED/UNSUBSCRIBED`
  - Accepte `email` + `campaignName` en paramètres optionnels pour fallback `getContactByEmail()`

- **`app/Http/Controllers/Web/EmeliaController::syncContact()`** :
  - `POST /contacts/{contact}/emelia/sync` — sync manuelle d'un contact individuel
  - Résout l'ID Emelia si absent avant le polling
  - Invalide le cache `emelia_status_{contact_id}` après sync

#### DB

- `activities.occurred_at` — timestamp nullable indexé (timestamp réel de l'event)
- `users.emelia_replies_last_seen` — timestamp nullable (pour le badge admin)

#### UI fiche contact

- **Onglets Tout / Emelia** au-dessus du fil d'activité (Alpine `activeTab`)
- **Badge `sync`/`live`** sur les activités Emelia (gris si `metadata.synthetic`, vert si webhook)
- **Bouton "Sync"** dans le panel Emelia → `POST /contacts/{contact}/emelia/sync` → recharge si nouvelles activités créées
- **Bouton "Voir →"** du panneau Emelia réparé : `$dispatch('switch-activity-tab', 'emelia')` + scroll
- **Heure affichée** depuis `occurred_at ?? created_at` (heure réelle si disponible)
- **Message webhook** mis à jour : "Webhook non disponible sur ce plan Emelia — utilisez le bouton Sync"

#### Badge admin

- **Sidebar** : badge rouge sur l'icône Contacts — compte les `email_replied` créées après `emelia_replies_last_seen`
- **`POST /notifications/emelia-replies/seen`** — met à jour `emelia_replies_last_seen = now()`, déclenché au clic sur le lien Contacts

### Comportement en production (2026-05-23)

- 1016 contacts ont désormais `emelia_contact_id` résolu (contre 138 avant)
- Sync journalière automatique via schedule `emelia:sync-all-campaigns`
- Bouton "Sync" disponible sur chaque fiche contact pour synchronisation immédiate

---

## 8. Backlog / Prochaine session

### Bug à corriger en priorité

- ✅ **Deal → Company auto-link** (corrigé v2.5, déployé v2.6) : `DealController::attachContact()` n'auto-associait pas la company du contact.
- ✅ **500 Vite manifest permissions** (résolu 2026-05-23) : `chmod -R 755 /var/www/html/public/build` ajouté au `CMD` de `Dockerfile.prod`.

### Features livrées (v2.6–v2.7)

- ✅ **Export CSV** contacts/companies : `GET /contacts/export` + `GET /companies/export`
- ✅ **Palette ⌘K** : `<x-command-palette>` Alpine, endpoint `/search/quick?q=`, nav clavier ↑↓+Entrée
- ✅ **route:cache** : `Api\InfoController::index()` + ajouté à la procédure de déploiement
- ✅ **N+1 fix v2.7** : eager loading select partiel + cache Redis TTL 30s + invalidation model events

### Optimisation backend — v2.7 (LIVRÉ 2026-05-23)

**N+1 queries sur les pages index : eager loading + cache Redis**

#### Ce qui a été fait

- **Niveau 1 — Eager loading avec select partiel** : `with(['companies:id,name', 'owner:id,name'])` dans `ContactController`, `with(['contacts:id,first_name,last_name', 'owner:id,name'])` dans `CompanyController`, `with(['stage:id,name,color', 'companies:id,name', 'contacts:id,first_name,last_name', 'owner:id,name'])` dans `DealController`.
- **Niveau 2 — Cache Redis TTL 30 s** : `Cache::tags(['{entity}.index'])->remember($key, 30, fn()...)` — cache les items + total du paginator, reconstruit le `LengthAwarePaginator` depuis le cache.
- **Invalidation** : `Contact::boot()`, `Company::boot()`, `Deal::boot()` → `saved`/`deleted` → `Cache::tags(['{entity}.index'])->flush()`.
- **Niveau 3 — `route:cache`** : ajouté à la procédure de redéploiement après `cache:clear`.

#### Gain attendu
-80% requêtes SQL sur pages index, temps de réponse <50ms vs ~200ms.

#### Backlog restant
- **Comptes utilisateurs** : ✅ Jimmy (admin, jimmygay13180@gmail.com) et Jonathan (manager, jo.boetsch@gmail.com) créés en prod (2026-05-23). ⚠️ Supprimer le compte par défaut `admin@example.com` / `password` dès que possible.
- **Backup BDD** : script cron `pg_dump` quotidien → `/opt/backups/crm/` avec rotation 7 jours — **voir §11 Lot 7**
- **Sélection globale companies/deals** : même pattern que contacts (toolbar "Tout sélectionner")
- ✅ **Webhook Emelia via n8n** : Livré 2026-05-23 — voir §9 ci-dessous.
- **Optimisations performances backend v3.0** : 7 lots identifiés et documentés — **voir §11**

---

## 9. Webhook Emelia → n8n → CRM (LIVRÉ 2026-05-23 — correctifs à apporter)

### Architecture

```
Emelia ──POST──▶ n8n webhook ──POST──▶ https://crm.nana-intelligence.fr/api/webhooks/emelia
```

### Workflow n8n

- **URL** : https://n8n.nana-intelligence.fr/workflow/q4GXMH5Qzjz9H6AZ
- **ID** : `q4GXMH5Qzjz9H6AZ`
- **Nom** : "Emelia - CRM Ultimate"
- **Statut** : `active: true`

```
Emelia Trigger1 (camp. 69eb1cca)  ─┐
                                    ├──▶  Normalize Event  ──▶  Forward to CRM
Emelia Trigger  (camp. 69ebb728)  ─┘
```

| Nœud | Type | Rôle |
|------|------|------|
| Emelia Trigger1 | `n8n-nodes-base.emeliaTrigger` v1 | Campagne `69eb1cca5033df0a8663a88e` |
| Emelia Trigger | `n8n-nodes-base.emeliaTrigger` v1 | Campagne `69ebb7281762b60a8169f625` |
| Normalize Event | `n8n-nodes-base.code` v2 | Normalise le payload Emelia → format CRM |
| Forward to CRM | `n8n-nodes-base.httpRequest` v4.2 | POST `https://crm.nana-intelligence.fr/api/webhooks/emelia` |

---

### ⚠️ BUG CRITIQUE — Normalize Event produit un payload vide (découvert 2026-05-23)

Un vrai event Emelia a été reçu et capturé dans le workflow (pinData). Le payload réel envoyé par Emelia est **structurellement différent** de ce qu'on avait supposé.

#### Payload réel Emelia (event OPENED capturé) :

```json
{
  "event": "OPENED",
  "campaign": "conseiller-consultant-acquisition",
  "sender": "jonathan@first-lead.fr",
  "contact": {
    "title": "Dirigeante",
    "company": "GOETIC",
    "sector": "Services de publicité",
    "city": "Montauban",
    "companyWebsite": "http://www.goetic.fr",
    "domainemelia": "goetic.fr",
    "firstName": "Magali",
    "lastName": "Fernando",
    "email": "magali.fernando@goetic.fr"
  },
  "date": "2026-05-23T19:12:39.046Z",
  "step": 3
}
```

#### Ce que le code Normalize Event produit actuellement :

```json
{ "event": "OPENED", "date": "2026-05-23T19:12:39.046Z" }
```

**Tous les autres champs sont `undefined`** car le code lit `d.email` (n'existe pas à la racine) au lieu de `d.contact.email`, `d.contactId` (inexistant), `d.campaignId` (inexistant — le payload a `campaign` en nom, pas en ID UUID), etc.

#### Bugs précis dans le code actuel :

| Champ produit | Code actuel | Problème | Fix |
|---|---|---|---|
| `email` | `d.email` | Inexistant à la racine | `d.contact?.email \|\| d.email` |
| `contact_id` | `d.contact_id \|\| d.contactId` | Emelia n'envoie pas d'ID contact | Supprimer — le CRM lookup par email |
| `event_id` | `d.event_id \|\| d.id \|\| d.eventId` | Aucun ID unique dans le payload | Générer : `` `${email}_${event}_${date}` `` |
| `campaign_id` | `d.campaign_id \|\| d.campaignId` | Emelia envoie `campaign` (nom string) | Mapper nom → UUID en dur OU envoyer `campaign_name` |
| `preview` | `d.preview \|\| d.previewText` | Inexistant sur OPENED (seulement sur REPLIED) | `d.preview \|\| d.previewText \|\| null` (laisser null si absent) |

#### Code Normalize Event corrigé (à appliquer en prochaine session) :

```js
const d = $input.first().json;
const email = d.contact?.email || d.email || '';
const event = d.event || d.type || '';
const date  = d.date  || d.timestamp || d.createdAt || d.created_at || '';

// Mapping nom campagne → UUID (à maintenir si nouvelles campagnes)
const campaignMap = {
  'conseiller-consultant-acquisition': '69ebb7281762b60a8169f625',
  'acquisition-agence-marketing':      '69eb1cca5033df0a8663a88e',
};
const campaignName = d.campaign || '';
const campaignId   = d.campaign_id || d.campaignId || campaignMap[campaignName] || null;

// Pas d'ID unique dans le payload Emelia → on le génère (idempotence côté CRM)
const eventId = email && event && date
  ? `${email}_${event}_${date}`
  : null;

return [{ json: {
  event,
  email,
  event_id:     eventId,
  date,
  subject:      d.subject      || null,
  preview:      d.preview      || d.previewText || null,
  campaign_id:  campaignId,
  campaign_name: campaignName,
  first_name:   d.contact?.firstName || null,
  last_name:    d.contact?.lastName  || null,
  step:         d.step               || null,
} }];
```

> **Note :** Le CRM répond `{"status":"ok"}` même si `email` est vide (il crée un "contact léger orphelin"). C'est pourquoi le test end-to-end semblait passer sans que l'activity soit réellement créée sur le bon contact.

---

### Campagnes connues (mapping nom → UUID)

| Nom campagne | UUID | Trigger n8n |
|---|---|---|
| `acquisition-agence-marketing` | `69eb1cca5033df0a8663a88e` | Emelia Trigger1 |
| `conseiller-consultant-acquisition` | `69ebb7281762b60a8169f625` | Emelia Trigger |

---

### Points techniques inchangés

- **Triggers natifs** : n8n s'enregistre auprès de l'API Emelia (credentials `f3Q24MOVU6pk1vzC`).
- **HMAC** non calculé (module `crypto` désactivé) — CRM accepte sans signature.
- **HTTP Request** : `contentType: "raw"`, `rawContentType: "application/json"`, `body: ={{ JSON.stringify($json) }}`.

### Ce qu'il reste à faire

4. Optionnel : nœud "Error Trigger" + notification si échec vers le CRM.

### ✅ Correctifs appliqués (2026-05-23)

1. ✅ **Nœud "Normalize Event" corrigé** — `d.contact?.email || d.email`, mapping `campaign` (nom) → UUID via `campaignMap`, `event_id` généré par concaténation `email + '_' + event + '_' + date`.
2. ✅ **Webhook controller CRM vérifié** — lookup par `emelia_contact_id` puis `email` déjà en place (ligne 34-36), retourne `ignored` si email vide.
3. ✅ **Testé end-to-end** — `{"status":"ok"}` au 1er appel, `{"status":"duplicate"}` au 2e (même `event_id`) — idempotence confirmée. Workflow n8n actif (`active: true`, `updatedAt: 2026-05-23T21:02:06Z`).

---

## 10. v2.8 — Roadmap IA (Prochaine session)

> Objectif : passer d'un CRM avec des cartes IA statiques à un assistant commercial actif qui détecte, score, rédige et alerte.

### État actuel des fonctionnalités IA

| Surface | Ce qui existe | Limite |
|---------|--------------|--------|
| `summarizeDeal` | Brief 4-6 lignes | Contexte limité à 10 activités, pas de données Emelia |
| `nextActionDeal` | JSON `{action, rationale, priority}` | Ne tient pas compte du historique Emelia du contact |
| `scoreDeal` | Score 0-100 + trend + flags | Pas persisté en base → recalculé à chaque refresh |
| `summarizeContact` | Brief texte | Pas de stats Emelia (sent/opened/replied) dans le contexte |
| `summarizeCompany` | Brief texte | Pas de CA réel ni deal pipeline |
| `dailySuggestions` | 3-5 actions sur deals stagnants | Ne regarde que les deals, pas les contacts ni tâches |

---

### Priorité A — Quick wins (impact immédiat, ≤1 jour chacun)

#### ✅ A1. Enrichir le contexte contact avec les données Emelia — LIVRÉ 2026-05-23

**Problème** : `contactContext()` dans `AiInsightService` ignore complètement l'engagement Emelia du contact.

**Fix** : Dans `AiInsightService::contactContext()`, ajouter après les activités :

```php
// Ajouter dans contactContext()
if ($contact->emelia_campaign_id) {
    $emeliaActivities = Activity::where('subject_type', Contact::class)
        ->where('subject_id', $contact->id)
        ->where('source', 'emelia')
        ->selectRaw("type, COUNT(*) as cnt, MAX(occurred_at) as last_at")
        ->groupBy('type')
        ->get();

    $lines[] = "\nEngagement email (Emelia) :";
    foreach ($emeliaActivities as $ea) {
        $lines[] = "- {$ea->type} : {$ea->cnt}x (dernier : {$ea->last_at?->format('d/m/Y')})";
    }
    $lines[] = "Lifecycle : " . ($contact->lifecycle_stage ?? 'N/A');
}
```

**Effet** : La synthèse contact inclut "ouvert 12x, répondu 2x, lifecycle: mql" → insights beaucoup plus pertinents.

**Fichier** : `app/Services/AiInsightService.php:contactContext()`

---

#### ✅ A2. `dailySuggestions` enrichi — tableau de bord complet — LIVRÉ 2026-05-23

**Problème** : ne regarde que les deals stagnants. Ne voit pas les contacts qui ont répondu à Emelia, les tâches en retard ou les deals qui closent cette semaine.

**Réécriture du contexte** dans `dailySuggestions()` :

```php
$context = [];

// 1. Deals stagnants (existant)
$stagnantDeals = Deal::with('stage')->where('status','open')
    ->where('updated_at','<', now()->subDays(7))->orderByDesc('amount')->limit(5)->get();

// 2. Deals qui closent dans les 7 prochains jours
$closingSoon = Deal::where('status','open')
    ->whereBetween('close_date', [now(), now()->addDays(7)])->get();

// 3. Tâches overdue assignées à l'utilisateur
$overdueTasks = Activity::where('type','task')->where('status','open')
    ->where('owner_id', $user->id)->where('due_date','<', now())->limit(5)->get();

// 4. Contacts qui ont répondu à Emelia dans les 48h (pas encore suivis)
$recentReplies = Activity::where('type', Activity::TYPE_EMAIL_REPLIED)
    ->where('created_at','>', now()->subHours(48))
    ->with('subject')->limit(5)->get();
```

Assembler un prompt riche et demander `{"suggestions": [], "alerts": [], "priorities": []}`.

**Fichier** : `app/Services/AiInsightService.php:dailySuggestions()`

---

#### ✅ A3. Préchargement cache IA (`ai:precompute`) — LIVRÉ 2026-05-23

**Problème** : le premier utilisateur qui ouvre une fiche deal attend 3-5s pour le LLM.

**Solution** : commande Artisan planifiée chaque nuit qui pré-calcule les insights des deals actifs.

```php
// app/Console/Commands/AiPrecompute.php
class AiPrecompute extends Command
{
    protected $signature = 'ai:precompute {--limit=50}';

    public function handle(AiInsightService $ai): void
    {
        $deals = Deal::where('status', 'open')->orderByDesc('amount')
            ->limit($this->option('limit'))->get();

        foreach ($deals as $deal) {
            $ai->summarizeDeal($deal->id, fresh: false);  // remplit le cache si absent
            $ai->scoreDeal($deal->id, fresh: false);
            usleep(500_000); // rate-limit OpenRouter
        }
    }
}
```

Scheduler dans `routes/console.php` : `Schedule::command('ai:precompute')->dailyAt('03:00')`.

**Fichiers** : `app/Console/Commands/AiPrecompute.php` (nouveau) + `routes/console.php`

---

### Priorité B — Features différenciatrices (2-3 jours)

#### ✅ B1. Score IA persisté sur les contacts (`ai_score`) — LIVRÉ 2026-05-23

**Ce que c'est** : chaque contact reçoit un score 0-100 calculé chaque nuit par un job, persisté en base. Affiché comme badge coloré sur l'index contacts et la fiche.

**Migration** :
```sql
ALTER TABLE contacts ADD COLUMN ai_score SMALLINT DEFAULT NULL;
ALTER TABLE contacts ADD COLUMN ai_score_updated_at TIMESTAMP DEFAULT NULL;
```

**Artisan `ai:score-contacts`** :
```php
// Pour chaque contact actif avec emelia_campaign_id :
// Signaux : lifecycle (lead=10, mql=30, sql=60, customer=100) 
//         + emelia replied (×20 pts) + opened (×5) + sent (×1)
//         + activité CRM dans les 30j (×15)
//         + deal associé ouvert (×20)
// → appel LLM pour normaliser et justifier le score
// → Contact::update(['ai_score' => $score, 'ai_score_updated_at' => now()])
```

**UI** : badge `<span class="ai-score-badge" style="background: hsl({{score}}, 70%, 45%)">{{score}}</span>` sur `contacts/index.blade.php` + tri par `ai_score DESC` dans ContactController.

**Fichiers** :
- Migration `2026_05_XX_add_ai_score_to_contacts`
- `app/Console/Commands/AiScoreContacts.php` (nouveau)
- `app/Services/AiInsightService.php:scoreContact()` (nouveau)
- `resources/views/pages/contacts/index.blade.php` (badge)
- `routes/console.php` (schedule quotidien 04:00)

---

#### ✅ B2. Assistant rédaction d'email — LIVRÉ 2026-05-23

**Ce que c'est** : bouton "✍ Rédiger un email" sur la fiche contact et la fiche deal → modal Alpine → LLM génère un email pro contextualisé (objet + corps) → l'utilisateur édite et copie.

**Endpoint** : `POST /ai/draft-email` (params : `contact_id` ou `deal_id`, `intent` optionnel — ex: "relance", "proposition commerciale", "suivi")

**Réponse LLM** :
```json
{
  "subject": "Suite à notre échange — Proposition CRM Ultimate",
  "body": "Bonjour [Prénom],\n\nJe me permets de..."
}
```

**Prompt** : inclure le contexte contact/deal complet (activités récentes, lifecycle, Emelia stats, deals associés) + l'intent. Demander un email professionnel B2B court (max 200 mots), en français, en-tête et signature génériques.

**UI** : modal Alpine dans `contacts/show.blade.php` + `deals/show.blade.php`. Textarea éditable pour subject + body. Bouton "Copier" (`navigator.clipboard.writeText`).

**Fichiers** :
- `app/Http/Controllers/Web/AiController.php:draftEmail()` (nouveau endpoint)
- `app/Services/AiInsightService.php:draftEmail()` (nouveau)
- `routes/web.php` : `POST /ai/draft-email`
- Composant ou section dans `contacts/show.blade.php` + `deals/show.blade.php`

---

#### B3. Analyse de sentiment des replies Emelia

**Ce que c'est** : quand un contact répond à une campagne Emelia (`email_replied` activity créée), un job lance le LLM pour scorer le sentiment de la reply (si disponible dans le payload Emelia).

**Déclencheur** : dans `EmeliaEventDispatcher::dispatch()`, après création d'une activity `TYPE_EMAIL_REPLIED`, dispatcher le job `AnalyzeReplySentiment::dispatch($activity)`.

**Job** :
```php
class AnalyzeReplySentiment implements ShouldQueue
{
    public function handle(AiInsightService $ai): void
    {
        $preview = $this->activity->metadata['preview'] ?? null;
        if (!$preview) return;  // pas de contenu à analyser

        // LLM : "Analyse le sentiment de cette réponse email en 1 mot (positif/négatif/neutre) 
        //        et donne un score de -1 à 1. JSON: {sentiment, score, summary}"
        $result = $ai->analyzeSentiment($preview);

        $meta = $this->activity->metadata ?? [];
        $meta['sentiment'] = $result;
        $this->activity->update(['metadata' => $meta]);
    }
}
```

**UI** : icône sentiment (😊/😐/😟) à côté des activités `email_replied` dans `activity-timeline.blade.php`.

**Fichiers** :
- `app/Jobs/AnalyzeReplySentiment.php` (nouveau)
- `app/Services/AiInsightService.php:analyzeSentiment()` (nouveau)
- `app/Support/EmeliaEventDispatcher.php` : dispatch du job après REPLIED
- `resources/views/components/activity-timeline.blade.php` : icône sentiment

---

### Priorité C — Nice to have

#### C1. Détection de contacts "froids" (widget dashboard)

Widget sur le dashboard : "X contacts sans activité depuis 30 jours". Chaque ligne = contact + dernier contact + suggestion de relance IA (1 ligne). Bouton "Créer une tâche de relance".

- Requête SQL : `contacts WHERE emelia_campaign_id IS NOT NULL AND id NOT IN (SELECT subject_id FROM activities WHERE subject_type='App\Models\Contact' AND created_at > NOW() - INTERVAL '30 days')`
- Pas de LLM nécessaire — la suggestion de relance peut être un template fixe.

#### C2. Digest hebdomadaire par email

Commande `ai:weekly-digest`, planifiée le lundi à 8h. Génère un email HTML personnalisé par commercial (deals à closer cette semaine, contacts qui ont répondu, tâches overdue, top 3 suggestions). Envoyé via `Mail::to($user)->send(new WeeklyDigestMail($digest))`.

Nécessite : configuration SMTP dans `.env` + mailable `WeeklyDigestMail` + vue `emails/weekly-digest.blade.php`.

---

### Ordre d'implémentation recommandé pour v2.8

1. ✅ **A1** — `contactContext()` + Emelia stats — **LIVRÉ 2026-05-23**
2. ✅ **A2** — `dailySuggestions()` enrichi — **LIVRÉ 2026-05-23**
3. ✅ **B1** — Score IA contacts (migration + job + UI badge) — **LIVRÉ 2026-05-23**
4. ✅ **B2** — Rédaction d'email assistée (endpoint + modal Alpine) — **LIVRÉ 2026-05-23**
5. ✅ **A3** — `ai:precompute` (commande + schedule) — **LIVRÉ 2026-05-23**
6. ✅ **B3** — Sentiment replies (job + UI icône) — **LIVRÉ 2026-05-23**

**Roadmap v2.8 complète.**

---

## 11. Optimisations performances backend — v3.0 (Prochaine session)

> Audit réalisé le 2026-05-24. Problèmes confirmés dans le code source — aucun travail Gemini concerné (périmètre 100% backend).

### Lot 1 — Indexes DB manquants (1 migration) — PRIORITÉ P1

**Fichier à créer :** `database/migrations/2026_05_24_100001_add_performance_indexes.php`

Colonnes confirmées sans index :

| Colonne(s) | Table | Problème confirmé | Gain estimé |
|---|---|---|---|
| `first_name`, `last_name` | `contacts` | ILIKE full-table scan sur recherche | -50% temps recherche |
| `ai_score` | `contacts` | ORDER BY sans index (ajouté en `2026_05_23` sans `->index()`) | -60% sur tri |
| `is_won`, `is_lost` | `pipeline_stages` | Full scan sur chaque `markWon`/`markLost` (DealController:204,216) | Scan → point lookup |
| `(subject_type, subject_id)` | `activities` | Aucun index composite — toutes les timelines font un seq scan | -70% latence timeline |

```php
Schema::table('contacts', function (Blueprint $table) {
    $table->index('first_name');
    $table->index('last_name');
    $table->index('ai_score');
});
Schema::table('pipeline_stages', function (Blueprint $table) {
    $table->index('is_won');
    $table->index('is_lost');
});
Schema::table('activities', function (Blueprint $table) {
    $table->index(['subject_type', 'subject_id'], 'activities_subject_idx');
});
```

---

### Lot 2 — Dashboard : N+1 + 0 cache — PRIORITÉ P1

**Fichier :** `app/Http/Controllers/Web/DashboardController.php`

**Problèmes confirmés :**
- Lignes 30-38 : N+1 — `$stages->map(fn($stage) => Deal::where('pipeline_stage_id', $stage->id)->get())` = 1 requête SQL par stage
- Lignes 19-27 : 7 requêtes `Deal::where(...)` sans aucun cache → recalculées à chaque page load

**Fix : une seule requête groupée + cache 5 minutes**

```php
public function index()
{
    return Cache::remember('dashboard.data', 300, function () {
        $now          = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOf30d   = $now->copy()->subDays(30);

        // 1 seule requête pour les 3 SUM
        $dealStats = Deal::selectRaw("
            SUM(CASE WHEN status='open'  THEN amount ELSE 0 END) as pipeline_total,
            SUM(CASE WHEN status='won'   THEN amount ELSE 0 END) as ca_total,
            SUM(CASE WHEN status='lost'  THEN amount ELSE 0 END) as ca_lost
        ")->first();

        // Remplacement du N+1 : groupBy en PHP sur résultat unique
        $openDeals  = Deal::where('status', 'open')->get(['pipeline_stage_id', 'amount']);
        $stagesData = PipelineStage::orderBy('position')
            ->where('is_won', false)->where('is_lost', false)->get()
            ->map(fn($stage) => [
                'name'  => $stage->name,
                'count' => $openDeals->where('pipeline_stage_id', $stage->id)->count(),
                'total' => $openDeals->where('pipeline_stage_id', $stage->id)->sum('amount'),
            ]);

        // ... reste du code
    });
}
```

**Invalidation à ajouter dans `Deal::boot()`** : `Cache::forget('dashboard.data')` sur `saved`/`deleted`.

---

### Lot 3 — DealController::show() — charge TOUS les contacts/sociétés — PRIORITÉ P2

**Fichier :** `app/Http/Controllers/Web/DealController.php:134-135`

```php
// CONFIRMÉ : charge TOUS les contacts en RAM à chaque fiche deal
$allContacts  = Contact::orderBy('last_name')->get();   // ← 6000 objets
$allCompanies = Company::orderBy('name')->get();         // ← sans limit ni cache
```

**Fix :**
```php
$allContacts = Cache::remember('contacts.dropdown', 60, fn() =>
    Contact::select('id', 'first_name', 'last_name')->orderBy('last_name')->get()
);
$allCompanies = Cache::remember('companies.dropdown', 60, fn() =>
    Company::select('id', 'name')->orderBy('name')->get()
);
```

Invalidation déjà en place via `Contact::boot()` / `Company::boot()` — utiliser les tags existants ou `Cache::forget`.

---

### Lot 4 — PipelineStage : 5 requêtes identiques sans cache — PRIORITÉ P2

**Fichier :** `app/Http/Controllers/Web/DealController.php` (lignes 62, 115, 143, 204, 216)

`PipelineStage::get()` est appelé 5 fois dans DealController sur des données quasi-statiques. Créer un helper central :

```php
// Dans DealController ou via une façade cachée
private function cachedStages(): Collection
{
    return Cache::remember('pipeline.stages.active', 3600,
        fn() => PipelineStage::orderBy('position')->where('is_won', false)->where('is_lost', false)->get()
    );
}

private function wonStage(): ?PipelineStage
{
    return Cache::remember('pipeline.stage.won', 86400,
        fn() => PipelineStage::where('is_won', true)->first()
    );
}
```

Invalidation : `PipelineStage::boot()` → `saved`/`deleted` → `Cache::forget('pipeline.stages.active')`, etc.

---

### Lot 5 — GIN trigram index sur recherche contacts — PRIORITÉ P3

**Problème :** recherche ILIKE `%term%` = full-table scan même avec index B-tree classique.

**Migration :**
```php
DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
DB::statement('CREATE INDEX contacts_search_trgm ON contacts USING gin (
    (first_name || \' \' || last_name || \' \' || coalesce(email, \'\')) gin_trgm_ops
)');
```

**Aucun changement de code applicatif requis** — PostgreSQL utilisera l'index automatiquement sur les ILIKE. Réduction de ~95% du temps de recherche à 6000+ contacts.

---

### Lot 6 — ProcessCsvImport::loadExistingKeys() — PRIORITÉ P2

**Fichier :** `app/Jobs/ProcessCsvImport.php:324-332`

```php
// CONFIRMÉ : charge TOUS les emails en mémoire avant l'import
Contact::whereNotNull('email')->pluck('email')->flip()->toArray(); // → 6000+ strings en RAM
```

**Fix : construire le lookup uniquement sur les emails du batch courant**

```php
private function buildBatchLookup(array $batch, string $entityType): array
{
    $keys = array_filter(array_column($batch, match($entityType) {
        'contact' => 'email',
        'company' => 'domain',
        'deal'    => 'name',
        default   => 'id',
    }));

    return match($entityType) {
        'contact' => Contact::whereIn('email', $keys)->pluck('id', 'email')->all(),
        'company' => Company::whereIn('domain', $keys)->pluck('id', 'domain')->all(),
        'deal'    => Deal::whereIn('name', $keys)->pluck('id', 'name')->all(),
        default   => [],
    };
}
```
Appelé par tranche de 500 lignes (le job chunke déjà) → mémoire constante quelle que soit la taille du CSV.

---

### Lot 7 — Backup pg_dump quotidien — PRIORITÉ P0 (critique prod)

Aucun backup automatisé en production. En cas de corruption DB ou erreur humaine, toutes les données sont perdues.

**Script à créer sur VPS :** `/home/jimmy/scripts/backup_db.sh`
```bash
#!/bin/bash
BACKUP_DIR="/opt/backups/crm"
mkdir -p "$BACKUP_DIR"
docker exec crm-postgres pg_dump -U crm crm_ultimate | gzip > "$BACKUP_DIR/crm_$(date +%Y%m%d_%H%M).sql.gz"
# Rotation : garder 7 jours
find "$BACKUP_DIR" -name "*.sql.gz" -mtime +7 -delete
```

**Cron à ajouter** (crontab jimmy) : `0 3 * * * /home/jimmy/scripts/backup_db.sh >> /home/jimmy/logs/backup.log 2>&1`

---

### Résumé priorités

| # | Lot | Effort | Priorité | Impact | Statut |
|---|-----|--------|----------|--------|--------|
| 7 | Backup pg_dump | 45 min | **P0** | Sécurité données prod | ✅ `scripts/backup_db.sh` — à SCP + cron sur VPS |
| 1 | Indexes DB | 30 min | **P1** | -60% latence timeline + recherche | ✅ migration `2026_05_24_100001` |
| 2 | Dashboard cache/N+1 | 1h | **P1** | -80% requêtes page accueil | ✅ `DashboardController` + `Deal::boot()` |
| 3 | DealController dropdown | 45 min | **P2** | -90% RAM fiches deal | ✅ `DealController::show()` — Cache::tags |
| 4 | PipelineStage cache | 30 min | **P2** | -5 queries/page deal | ✅ helpers `activeStages/wonStage/...` + `PipelineStage::boot()` |
| 6 | CSV import batch lookup | 1h | **P2** | Import 50K+ sans OOM | ✅ `ProcessCsvImport` — `processChunk` + `buildBatchLookup` |
| 5 | GIN trigram index | 20 min | **P3** | Recherche instantanée | ✅ migration `2026_05_24_100002` |

**v3.0 backend performance — LIVRÉ 2026-05-24. Reste : déployer sur VPS + créer le cron backup.**

### Déploiement v3.0 — à faire sur VPS

```bash
# 1. SCP fichiers modifiés
# Périmètre backend (Claude Code) :
#   app/Http/Controllers/Web/DashboardController.php
#   app/Http/Controllers/Web/DealController.php
#   app/Models/Deal.php
#   app/Models/PipelineStage.php
#   app/Jobs/ProcessCsvImport.php
#   database/migrations/2026_05_24_100001_add_performance_indexes.php
#   database/migrations/2026_05_24_100002_add_gin_trgm_index_contacts.php

# 2. Rebuild + migrate + cache clear
cd ~/crm-ultimate
docker compose -f docker-compose.prod.yml build app queue
docker compose -f docker-compose.prod.yml up -d app queue
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T app php artisan config:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan view:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan cache:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache

# 3. Script backup + cron
mkdir -p /home/jimmy/scripts /home/jimmy/logs
# SCP scripts/backup_db.sh → /home/jimmy/scripts/backup_db.sh
chmod +x /home/jimmy/scripts/backup_db.sh
crontab -l | { cat; echo "0 3 * * * /home/jimmy/scripts/backup_db.sh >> /home/jimmy/logs/backup.log 2>&1"; } | crontab -
```
