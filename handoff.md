# Handoff — CRM Ultimate v1.8

## 1. Objectif

Transformer le CRM Ultimate (Laravel 13 + Blade + Alpine.js + PostgreSQL) en un outil complet :
suppression/sélection multiple des entités, propriétés personnalisées dynamiques sur les fiches,
réintégration des fonctionnalités IA dans l'UI, toggle de tâches, timeline d'activités,
corbeille/restauration et validation typée des champs personnalisés.

**Done :** 178 tests Feature verts, assets buildés, commits propres sur `master`.

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

**Bulk delete**
- `Alpine.store('bulk')` global avec `Set` séparé par entité (contact / company / deal)
- `<x-bulk-bar entity delete-action>` : barre flottante bottom-fixed, apparaît quand ≥ 1 sélection
- Checkboxes header (select-all) + row sur les 3 index, visibles admin/manager uniquement
- Routes `POST /*/bulk-destroy` sous `middleware('role:admin,manager')`
- Validation `ids: required, array, min:1`

**Custom fields**
- `CustomFieldRenderer::forEntity($type)` cache 60 s + `displayValue($field, $raw)`
- Cache invalidé à chaque store / update / destroy d'un champ
- Composants Blade : `<x-form-field>`, `<x-custom-fields-form>`, `<x-custom-fields-show>`
- `/settings/fields` : edit inline Alpine + delete par ligne
- Intégré dans tous les create/edit + affichage labellé dans les show

**`CustomValueValidator` (v1.7)**
- `CustomValueValidator::validationRules(string $entityType)` : génère les règles Laravel per-field
  (`numeric`, `date`, `in:0,1`, `Rule::in($options)`, `string|max:1000`) avec `required`/`nullable`
- `CustomValueValidator::cast(string $entityType, array $values)` : caste les valeurs au bon type PHP
  (`float`, `Y-m-d`, `bool`, `trim string`), `null` pour vide, clés inconnues silencieusement rejetées
- Câblé dans `ContactController`, `CompanyController`, `DealController` (store + update)

**IA Web**
- `AiInsightService` (logique partagée Web ↔ API) : summarizeDeal, nextActionDeal, scoreDeal,
  summarizeContact, summarizeCompany, dailySuggestions
- Cache 24 h par entité ; `?fresh=1` bypass cache (admin/manager seulement)
- `Web\AiController` : 4 endpoints `POST /web/ai/*` derrière `web.auth + throttle:20,1`
- `<x-ai-insight-card endpoint title>` Alpine : spinner → rendu adaptatif (texte / JSON score/next-action / suggestions)
- Intégré dans : deals/show (3 cards sidebar), contacts/show (1 card), companies/show (1 card),
  dashboard (widget Suggestions du jour)
- `Api\AiController` refactorisé → délègue à `AiInsightService`

**v1.6 — UX**
- Toggle tâches done/open : `ActivityController::toggleDone` + fetch Alpine dans `activities/index`
- `ActivityController::store` : crée activité morphée (contact / company / deal)
- `<x-activity-timeline showComposer>` : composer form + listing chrono avec toggle tâche inline
- Onglets Informations / Activité sur fiches contact et company

**Tri de colonnes (v1.8)**
- Composant `<x-sort-th column label :sort :dir>` : lien cliquable avec ▲/▼, `request()->fullUrlWithQuery()` pour préserver `?search=`
- Whitelist stricte par entité — colonne inconnue → fallback silencieux sur le défaut
- Contacts : tri `last_name` (défaut), `email`, `created_at`
- Companies : tri `name` (défaut), `industry`, `city`, `created_at`
- Deals : tri `close_date` (défaut), `name`, `amount` — le bandeau "Sort" reflète l'état courant

**Corbeille (v1.7)**
- `GET /trash` : liste les soft-deleted contacts / companies / deals (onglets Alpine)
- `POST /contacts/{id}/restore`, `/companies/{id}/restore`, `/deals/{id}/restore` : restauration
- Toutes les routes sous `middleware('role:admin,manager')`
- Icône Corbeille dans la rail nav (visible admin/manager uniquement)
- `TrashController` avec `index`, `restoreContact`, `restoreCompany`, `restoreDeal`

### Dernière action effectuée
v1.8 livré — Tri de colonnes (3 index) + Corbeille + CustomValueValidator — suite complète 178/178.

---

## 3. Fichiers concernés

### Contrôleurs Web
| Fichier | Rôle |
|---|---|
| `app/Http/Controllers/Web/AuthController.php` | Login — log password supprimé |
| `app/Http/Controllers/Web/ContactController.php` | CRUD + bulkDestroy + CustomValueValidator |
| `app/Http/Controllers/Web/CompanyController.php` | CRUD + bulkDestroy + CustomValueValidator |
| `app/Http/Controllers/Web/DealController.php` | Edit + destroy + bulkDestroy + CustomValueValidator |
| `app/Http/Controllers/Web/TrashController.php` | index + restore × 3 entités |
| (ContactController / CompanyController / DealController) | index() — tri dynamique whitelisté |
| `app/Http/Controllers/Web/ActivityController.php` | index + toggleDone + store |
| `app/Http/Controllers/Web/AiController.php` | 4 endpoints IA Web |
| `app/Http/Controllers/Web/Settings/CustomFieldController.php` | CRUD + cache invalidation |
| `app/Http/Controllers/Api/AiController.php` | Refactorisé → délègue à AiInsightService |

### Services / Support
| Fichier | Rôle |
|---|---|
| `app/Services/AiInsightService.php` | Logique IA partagée Web ↔ API |
| `app/Services/LlmService.php` | Client HTTP OpenRouter (inchangé) |
| `app/Support/CustomFieldRenderer.php` | Cache + formatage custom fields |
| `app/Support/CustomValueValidator.php` | Validation + cast per-type des custom_values |

### Middleware
| Fichier | Rôle |
|---|---|
| `app/Http/Middleware/RequireRole.php` | Dual-mode JSON/Blade |

### Routes
| Fichier | Rôle |
|---|---|
| `routes/web.php` | Toutes les routes Web (+ /trash + /*/restore) |

### Vues — composants
| Fichier | Rôle |
|---|---|
| `resources/views/components/layouts/app.blade.php` | Toast container injecté |
| `resources/views/components/app-shell.blade.php` | Rail nav + icône Corbeille admin/manager |
| `resources/views/components/bulk-bar.blade.php` | Barre bulk delete |
| `resources/views/components/form-field.blade.php` | Input générique typé |
| `resources/views/components/custom-fields-form.blade.php` | Section custom fields dans forms |
| `resources/views/components/custom-fields-show.blade.php` | Affichage labellé custom fields |
| `resources/views/components/ai-insight-card.blade.php` | Card IA Alpine fetch |
| `resources/views/components/activity-timeline.blade.php` | Timeline + composer |
| `resources/views/components/sort-th.blade.php` | En-tête triable avec ▲/▼ et URL preserving |

### Vues — pages
| Fichier | Rôle |
|---|---|
| `resources/views/pages/contacts/{index,show,create,edit}.blade.php` | CRUD + bulk + custom fields + IA + timeline |
| `resources/views/pages/companies/{index,show,create,edit}.blade.php` | idem |
| `resources/views/pages/deals/{index,show,edit}.blade.php` | idem (sans create — modal existante) |
| `resources/views/pages/activities/index.blade.php` | Toggle tâches |
| `resources/views/pages/settings/fields.blade.php` | Edit/delete inline custom fields |
| `resources/views/pages/dashboard.blade.php` | Widget suggestions IA |
| `resources/views/pages/trash/index.blade.php` | Corbeille — 3 onglets + bouton Restaurer |

### JS / Assets
| Fichier | Rôle |
|---|---|
| `resources/js/app.js` | Alpine.store('bulk') ajouté |
| `public/build/` | Assets compilés (vite build) |

### Tests
| Fichier | Rôle |
|---|---|
| `tests/Feature/RoleAccessTest.php` | 403 settings pour viewer |
| `tests/Feature/WebContactControllerTest.php` | CRUD contact Web |
| `tests/Feature/WebCompanyControllerTest.php` | CRUD company Web |
| `tests/Feature/WebDealControllerTest.php` | Edit + destroy + bulk deal |
| `tests/Feature/BulkActionsTest.php` | Bulk delete × 3 entités + 403 viewer |
| `tests/Feature/CustomFieldsWebTest.php` | Créer field → form → submit → persist → re-affichage |
| `tests/Feature/CustomValueValidatorTest.php` | cast par type, clés inconnues, validation, intégration DB |
| `tests/Feature/AiWebTest.php` | 8 endpoints IA Web, mock LlmService |
| `tests/Feature/ActivityToggleTest.php` | Toggle done (owner / admin / 403 autre) |
| `tests/Feature/ContactTimelineTest.php` | Onglet activité + store + affichage |
| `tests/Feature/TrashRestoreTest.php` | Vue corbeille + restore × 3 entités + 403 viewer |
| `tests/Feature/SortableIndexTest.php` | Tri asc/desc × 3 entités, fallback colonne invalide |

---

## 4. Ce qui a échoué

### CSRF dans les tests AJAX Web
**Tentative :** `postJson('/url')` → 419 car pas de token CSRF.
**Tentative :** `withHeaders(['X-CSRF-TOKEN' => 'test'])` chaîné après `withCookies` → 302 (cookie JWT perdu dans la chaîne).
**Tentative :** `$this->call('POST', $url, ['_token' => 'test'], [], [], ['CONTENT_TYPE' => 'application/json'])` → 419 car le corps JSON n'est pas parsé comme form data.
**Ce qui fonctionne :** `->post($url, ['_token' => 'test'])` avec `withSession(['_token' => 'test'])` dans `withAuth()`. Le `_token` dans le corps form-encoded = CSRF passe.

### `assertSee()` avec apostrophes dans les vues
**Tentative :** `assertSee("Modifier l'entreprise")` → fail car Blade encode `'` en `&#039;`.
**Solution :** `assertSeeText("Modifier l'entreprise")` (compare le texte décodé).

### `assertSame` sur custom_values numériques post-DB
**Tentative :** `assertSame(7500.0, $contact->custom_values['budget'])` → fail car JSON round-trip transforme `7500.0` en `int(7500)`.
**Solution :** `assertEquals(7500.0, ...)` pour les assertions post-DB (utilise `==` donc `7500.0 == 7500` = true). `assertSame` reste correct pour tester `cast()` directement (sans DB).

---

## 5. État du déploiement production (v1.9)

### Infrastructure déployée — VPS 51.38.99.226 (Ubuntu 22.04)

**URL de production : https://crm.nana-intelligence.fr** (HTTPS Let's Encrypt via Caddy)

#### Architecture VPS
- Caddy Docker **partagé** sur le réseau `web` (`/home/jimmy/docker/docker-compose.yml`)
- Caddyfile : `/opt/caddy/config/Caddyfile` (entrée CRM ajoutée)
- Repo CRM déployé dans : `/home/jimmy/crm-ultimate/`
- Compose prod : `docker compose -f /home/jimmy/crm-ultimate/docker-compose.prod.yml`

#### Conteneurs prod (tous `restart: unless-stopped`)
| Conteneur | Image | Rôle |
|---|---|---|
| `crm-app` | `crm-ultimate-app` | Laravel + php artisan serve :8080 |
| `crm-queue` | `crm-ultimate-queue` | Worker queue Redis |
| `crm-postgres` | `postgres:17-alpine` | Base de données (volume `pgdata`) |
| `crm-redis` | `redis:7-alpine` | Cache + sessions + queue |

#### Fichiers d'infrastructure créés (commités sur master)
- `docker/php/Dockerfile.prod` — multi-stage : node-builder + php-builder (--no-scripts) + runtime
- `docker-compose.prod.yml` — réseau `web` externe + réseau `internal` privé
- `docker/caddy/Caddyfile` — template (le vrai Caddyfile est dans `/opt/caddy/config/`)
- `.env.production.example` — template sans secrets
- `deploy.sh` — script de redéploiement (APP_DIR auto-détecté)
- `.dockerignore` — exclut node_modules, vendor, .env, .git

#### Variables `.env` sur le VPS (`/home/jimmy/crm-ultimate/.env`)
Toutes générées aléatoirement au déploiement. **À compléter** :
```
OPENROUTER_API_KEY=    ← à ajouter pour activer les features IA
```
Après ajout : `docker compose -f docker-compose.prod.yml restart app`

#### Bug connu : `route:cache` et closure dans `routes/api.php`
La route `Route::get('/', fn() => ...)` en ligne 23 de `api.php` est une closure.
Laravel ne peut pas cacher les closures. Fix propre = déplacer ce endpoint dans un contrôleur.
En attendant, `config:cache` et `view:cache` fonctionnent, seul `route:cache` est bloqué.
**Impact : nul sur le fonctionnement, léger sur la perf (routes non cachées = ~1-2ms par requête).**

#### Prochains déploiements (procédure)
Le repo est **privé** sur GitHub, le VPS ne peut pas faire `git pull` directement.
Workflow actuel : git archive local → SFTP upload → rebuild.

Pour automatiser, ajouter une clé SSH deploy :
```bash
# Sur le VPS :
ssh-keygen -t ed25519 -C "crm-deploy" -f ~/.ssh/crm_deploy -N ""
cat ~/.ssh/crm_deploy.pub
# → copier la clé publique dans GitHub > Settings > Deploy Keys
```
Puis dans `deploy.sh`, remplacer `git pull` par `GIT_SSH_COMMAND="ssh -i ~/.ssh/crm_deploy" git pull origin master`.

---

## 6. Backlog feature en attente

- **Export CSV** contacts/companies : `fputcsv` natif, inclure `custom_values` labellés
- **Palette ⌘K** : `<x-command-palette>` Alpine, endpoint `/search` déjà existant
- **Backup BDD** : script cron `pg_dump` quotidien → `/opt/backups/crm/` avec rotation 7 jours
- **Closure route:cache** : déplacer `Route::get('/', fn()=>...)` dans `Api\InfoController`
