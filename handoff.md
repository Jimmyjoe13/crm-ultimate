# Handoff — CRM Ultimate

## 1. Objectif

Transformer le CRM Ultimate (Laravel 13 + Blade + Alpine.js + PostgreSQL) en un outil complet :
suppression/sélection multiple des entités, propriétés personnalisées dynamiques sur les fiches,
réintégration des fonctionnalités IA dans l'UI, toggle de tâches, timeline d'activités,
corbeille/restauration, validation typée des champs personnalisés, import CSV avec mapping avancé,
et intégration d'outils tiers (Emelia emailing).

**v2.0 :** Déploiement production stabilisé — Nginx+PHP-FPM, HTTPS assets, import CSV 50MB.
**v2.1 :** Bug fix custom fields import + refonte UX import (stratégie doublons, validation requis, sélection globale contacts).
**v2.2 :** Interface mapping style HubSpot — cartes enrichies, combobox searchable, panneau sticky, création propriété inline.
**v2.3 :** Intégration Emelia — sync CRM→campagne, webhook events→timeline, contact léger auto sur orphelin.

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
- `EmeliaService` : `listCampaigns()` + `addContactToCampaign()` (pattern identique à `LlmService`)
- `EmeliaController` (Web) : `GET /emelia/campaigns` (JSON) + `POST /contacts/{contact}/emelia` (admin/manager)
- `EmeliaWebhookController` : `POST /api/webhooks/emelia` — HMAC SHA256, idempotence via `external_id`, contact léger auto sur orphelin
- 6 nouveaux types Activity : `email_sent/opened/clicked/replied/bounced/unsubscribed` — constantes + icônes timeline
- Migrations : `emelia_contact_id` sur `contacts` + colonnes `source`/`external_id`/`metadata` sur `activities`
- Modal Alpine sur fiche contact : fetch campagnes + POST add + badge si contact déjà dans Emelia
- 19 nouveaux tests (webhook + service + controller) — **211 tests verts** au total

### Dernière action effectuée
v2.3 — Intégration Emelia complète : EmeliaService, webhook HMAC, modal contact, timeline enrichie.

---

## 3. Fichiers concernés

### Contrôleurs Web
| Fichier | Rôle |
|---|---|
| `app/Http/Controllers/Web/AuthController.php` | Login — log password supprimé |
| `app/Http/Controllers/Web/ContactController.php` | CRUD + bulkDestroy (select_all) + CustomValueValidator + owner eager-load |
| `app/Http/Controllers/Web/CompanyController.php` | CRUD + bulkDestroy + CustomValueValidator |
| `app/Http/Controllers/Web/DealController.php` | Edit + destroy + bulkDestroy + CustomValueValidator |
| `app/Http/Controllers/Web/ImportController.php` | Wizard CSV : preview enrichi (columns/inferred_type/groups), store, status, quickField |
| `app/Http/Controllers/Web/TrashController.php` | index + restore × 3 entités |
| `app/Http/Controllers/Web/ActivityController.php` | index + toggleDone + store |
| `app/Http/Controllers/Web/AiController.php` | 4 endpoints IA Web |
| `app/Http/Controllers/Web/Settings/CustomFieldController.php` | CRUD + cache invalidation |
| `app/Http/Controllers/Api/AiController.php` | Refactorisé → délègue à AiInsightService |

### Jobs / Services / Support
| Fichier | Rôle |
|---|---|
| `app/Jobs/ProcessCsvImport.php` | Import CSV : partition core/custom, stratégie doublons, merge custom_values |
| `app/Models/ImportJob.php` | Modèle import — fillable `duplicate_strategy` |
| `app/Models/CustomField.php` | Model events `saved`/`deleted` → Cache::forget (fix bug v2.2) |
| `app/Services/AiInsightService.php` | Logique IA partagée Web ↔ API |
| `app/Services/LlmService.php` | Client HTTP OpenRouter |
| `app/Support/CustomFieldRenderer.php` | Cache Redis 60s + formatage |
| `app/Support/CustomValueValidator.php` | Validation + cast per-type des custom_values |

### Routes
| Fichier | Rôle |
|---|---|
| `routes/web.php` | Toutes les routes Web + `POST /imports/quick-field` |

### Migrations
| Fichier | Rôle |
|---|---|
| `database/migrations/2026_05_20_000001_add_duplicate_strategy_to_import_jobs.php` | Colonne `duplicate_strategy VARCHAR(16) DEFAULT 'skip'` sur `import_jobs` |

### Vues — composants
| Fichier | Rôle |
|---|---|
| `resources/views/components/bulk-bar.blade.php` | Barre bulk delete avec mode sélection globale |
| `resources/views/components/import-stepper.blade.php` | Stepper 3 étapes (lit `step` depuis Alpine parent) |
| `resources/views/components/form-field.blade.php` | Input générique typé |
| `resources/views/components/custom-fields-form.blade.php` | Section custom fields dans forms |
| `resources/views/components/custom-fields-show.blade.php` | Affichage labellé custom fields |
| `resources/views/components/ai-insight-card.blade.php` | Card IA Alpine fetch |
| `resources/views/components/activity-timeline.blade.php` | Timeline + composer |
| `resources/views/components/sort-th.blade.php` | En-tête triable ▲/▼ |

### Vues — pages
| Fichier | Rôle |
|---|---|
| `resources/views/pages/contacts/index.blade.php` | Bulk + sélection globale + toolbar "Tout sélectionner (N)" |
| `resources/views/pages/contacts/show.blade.php` | lead_status + owner dans card Informations |
| `resources/views/pages/imports/create.blade.php` | Wizard complet HubSpot-style (2600 lignes JS/Blade) |
| `resources/views/pages/contacts/{create,edit}.blade.php` | CRUD + custom fields |
| `resources/views/pages/companies/{index,show,create,edit}.blade.php` | idem |
| `resources/views/pages/deals/{index,show,edit}.blade.php` | idem |
| `resources/views/pages/settings/fields.blade.php` | Edit/delete inline custom fields |

### JS / Assets
| Fichier | Rôle |
|---|---|
| `resources/js/app.js` | `Alpine.store('bulk')` avec `selectAllMode` + `enableSelectAll` + `isSelectAllMode` |
| `public/build/` | Assets compilés — **toujours reconstruire via `docker compose build`** |

### Tests
| Fichier | Rôle |
|---|---|
| `tests/Feature/BulkActionsTest.php` | Bulk delete × 3 entités + 403 viewer |
| `tests/Feature/WebContactControllerTest.php` | CRUD contact Web |
| `tests/Feature/ImportCustomFieldsTest.php` | custom_values écrits à l'import + cast types |
| `tests/Feature/ImportRequiredFieldsTest.php` | Validation requis preview + store 422 |
| `tests/Feature/ImportDuplicateStrategyTest.php` | skip/update/create + merge custom_values |
| `tests/Feature/WebImportControllerTest.php` | Preview + store + duplicate_strategy |

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

---

## 5. État du déploiement production

### Infrastructure — VPS 51.38.99.226 (Ubuntu 22.04)
**URL : https://crm.nana-intelligence.fr** (HTTPS Let's Encrypt via Caddy)

#### Architecture
- Caddy Docker partagé sur réseau `web` (`/home/jimmy/docker/docker-compose.yml`)
- Repo CRM : `/home/jimmy/crm-ultimate/`
- Compose prod : `docker compose -f /home/jimmy/crm-ultimate/docker-compose.prod.yml`

#### Conteneurs prod
| Conteneur | Image | Rôle |
|---|---|---|
| `crm-app` | `crm-ultimate-app` | Nginx:8080 + PHP-FPM:9000 via supervisord |
| `crm-queue` | `crm-ultimate-queue` | Worker queue Redis |
| `crm-postgres` | `postgres:17-alpine` | Base de données (volume `pgdata`) |
| `crm-redis` | `redis:7-alpine` | Cache + sessions + queue |

#### Procédure de redéploiement — IMPORTANT
Le code PHP et les vues Blade sont **embarqués dans les images Docker** au moment du `build`.
**Un simple SCP + `view:clear` ne met PAS à jour le code en production.**

```bash
# Workflow complet à chaque déploiement :
# 1. Copier les fichiers modifiés sur le VPS (SCP ou via la session Claude Code)
# 2. Rebuilder les images
cd ~/crm-ultimate
docker compose -f docker-compose.prod.yml build app queue
docker compose -f docker-compose.prod.yml up -d app queue
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T app php artisan config:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan view:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan cache:clear
```

#### Variables `.env` VPS
```
APP_URL=https://crm.nana-intelligence.fr
ASSET_URL=https://crm.nana-intelligence.fr
TRUSTED_PROXIES=*
OPENROUTER_API_KEY=sk-or-v1-...   ← configuré ✓
OPENROUTER_MODEL=anthropic/claude-haiku-4-5
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

## 6. Prochaine session — Backlog

### Fonctionnalités Emelia livrées (v2.3) ✓
- Bouton + modal Alpine "Ajouter à campagne Emelia" sur la fiche contact
- Webhook `POST /api/webhooks/emelia` avec HMAC + idempotence + contact léger auto
- Timeline enrichie : 6 types email Emelia avec icônes distinctes

### À déployer en prod
1. Ajouter dans `.env` VPS :
   ```
   EMELIA_API_KEY=<clé depuis app.emelia.io/settings>
   EMELIA_WEBHOOK_SECRET=$(openssl rand -hex 32)
   ```
2. `docker compose -f docker-compose.prod.yml build app queue && up -d`
3. `php artisan migrate --force`
4. Configurer le webhook Emelia → `https://crm.nana-intelligence.fr/api/webhooks/emelia`

---

## 7. Backlog feature en attente

- **Export CSV** contacts/companies : `fputcsv` natif, inclure `custom_values` labellés
- **Palette ⌘K** : `<x-command-palette>` Alpine, endpoint `/search` déjà existant
- **Backup BDD** : script cron `pg_dump` quotidien → `/opt/backups/crm/` avec rotation 7 jours
- **Closure route:cache** : déplacer `Route::get('/', fn()=>...)` dans `Api\InfoController`
- **Mot de passe admin** : changer `password` par défaut en production
- **Sélection globale companies/deals** : même pattern que contacts (toolbar "Tout sélectionner")
