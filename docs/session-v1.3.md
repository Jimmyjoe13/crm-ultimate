# Session v1.3 — Associations N-M, Pages détail HubSpot-style, Modal deal

**Date :** 16 mai 2026  
**Branches impactées :** `master`  
**Commits produits :** 3 (`a6a68e8`, `ac07777`, `d8ce178`) + isolement tests (`1aec0f0`)

---

## Contexte de départ

Le CRM était en v1.2 après avoir stabilisé l'import CSV et exposé les custom fields. Quatre limites bloquaient la scalabilité :

1. **Import société sous-exploité** — une colonne "Société" dans un CSV contact ne créait que le `name`, ignorant domain, industry, phone, etc.
2. **Cardinalité 1-N rigide** — un contact = 1 entreprise, un deal = 1 contact + 1 entreprise. Irréaliste en B2B.
3. **Pas de vue détail riche** — le panneau latéral 2 colonnes ne pouvait pas accueillir une timeline d'activités + propriétés + enregistrements associés.
4. **Pas de navigation URL** — impossible de partager ou recharger une fiche directement.

La session a livré **v1.3a** (refondation du modèle de données) et **v1.3b** (UX HubSpot-style), plus 4 corrections de bugs critiques.

---

## v1.3a — Foundation Data

### 1. Migration : 3 tables pivots + colonnes lifecycle

**Fichier :** `database/migrations/2026_05_17_000001_v1_3_a_associations_lifecycle.php`

#### Tables pivots créées

```
contact_company
  - contact_id (FK → contacts, cascade delete)
  - company_id (FK → companies, cascade delete)
  - role : string, default 'employee'   → employee | decision_maker | influencer | former
  - is_primary : boolean, default false
  - UNIQUE (contact_id, company_id)
  - INDEX (company_id, is_primary)

deal_contact
  - deal_id (FK → deals, cascade delete)
  - contact_id (FK → contacts, cascade delete)
  - role : string, default 'primary'    → primary | technical | billing | other
  - UNIQUE (deal_id, contact_id)

deal_company
  - deal_id (FK → deals, cascade delete)
  - company_id (FK → companies, cascade delete)
  - role : string, default 'customer'   → customer | partner | reseller
  - is_primary : boolean, default false
  - UNIQUE (deal_id, company_id)
```

#### Colonnes lifecycle ajoutées

```
contacts :
  - lifecycle_stage  string  default 'lead'  INDEX
    → lead | mql | sql | opportunity | customer | evangelist | other
  - lead_status      string  nullable
    → new | open | in_progress | connected | unqualified | bad_fit

companies :
  - lifecycle_stage  string  default 'lead'  INDEX
  - lead_status      string  nullable
```

#### Backfill puis drop des FK scalaires

La migration backfille les données existantes vers les pivots avant de supprimer les anciennes colonnes :

```sql
-- Backfill contact_company depuis contacts.company_id
INSERT INTO contact_company (contact_id, company_id, role, is_primary, ...)
SELECT id, company_id, 'employee', true, NOW(), NOW()
FROM contacts WHERE company_id IS NOT NULL;

-- Backfill deal_company depuis deals.company_id
INSERT INTO deal_company (deal_id, company_id, role, is_primary, ...)
SELECT id, company_id, 'customer', true, NOW(), NOW()
FROM deals WHERE company_id IS NOT NULL;

-- Backfill deal_contact depuis deals.contact_id
INSERT INTO deal_contact (deal_id, contact_id, role, ...)
SELECT id, contact_id, 'primary', NOW(), NOW()
FROM deals WHERE contact_id IS NOT NULL;
```

Puis `dropConstrainedForeignId` sur :
- `contacts.company_id`
- `deals.company_id`
- `deals.contact_id`

---

### 2. Trait HasLifecycle

**Fichier :** `app/Models/Concerns/HasLifecycle.php`

Trait appliqué sur `Contact` et `Company`. Fournit :

```php
const LIFECYCLE_STAGES = ['lead','mql','sql','opportunity','customer','evangelist','other'];
const LEAD_STATUSES    = ['new','open','in_progress','connected','unqualified','bad_fit'];

// Scopes
scopeLifecycle($query, $stage)
scopeLeadStatus($query, $status)
```

---

### 3. Modèles : relations BelongsToMany N-M

#### `Contact`
- Suppression de `company_id` du `$fillable` et de la relation `belongsTo Company`
- Ajout :
  ```php
  companies()      → BelongsToMany Company, pivot: role, is_primary
  primaryCompany() → BelongsToMany Company (wherePivot is_primary=true)
  deals()          → BelongsToMany Deal, pivot: role
  ```

#### `Company`
- Suppression de `hasMany contacts` et `hasMany deals` (scalaire)
- Ajout :
  ```php
  contacts() → BelongsToMany Contact, pivot: role, is_primary
  deals()    → BelongsToMany Deal, pivot: role, is_primary
  ```

#### `Deal`
- Suppression de `company_id`, `contact_id` du `$fillable` et des `belongsTo`
- Ajout :
  ```php
  contacts()      → BelongsToMany Contact, pivot: role
  companies()     → BelongsToMany Company, pivot: role, is_primary
  primaryContact()→ BelongsToMany Contact (wherePivot role='primary')
  primaryCompany()→ BelongsToMany Company (wherePivot is_primary=true)
  ```

---

### 4. AssociationAuditor

**Fichier :** `app/Services/AssociationAuditor.php`

Service statique qui écrit dans `audit_logs` lors des opérations sur les pivots. Le trait `Auditable` existant n'observait pas les `attach`/`detach`.

```php
AssociationAuditor::recordAttach($parent, $relation, $childId, $pivot)
AssociationAuditor::recordDetach($parent, $relation, $childId)
```

Chaque entrée dans `audit_logs` a :
- `event` = `'associated'` ou `'dissociated'`
- `new_values` = `{relation, child_type, child_id, pivot}`
- `auditable_type` / `auditable_id` du parent

---

### 5. Controllers — méthodes d'association

#### CompanyController — nouvelles méthodes

| Méthode | Description |
|---------|-------------|
| `attachContact(company, request)` | Attache un contact au pivot, valide le rôle, interdit les doublons |
| `detachContact(company, contact)` | Détache le pivot + audit |
| `updateContactAssoc(company, contact, request)` | Met à jour `role` et/ou `is_primary` sur le pivot |
| `show(id)` | Retourne la company avec `contacts`, `deals`, `owner` en eager-load N-M |

#### ContactController — nouvelles méthodes

| Méthode | Description |
|---------|-------------|
| `attachCompany(contact, request)` | Symétrique de `CompanyController::attachContact` |
| `detachCompany(contact, company)` | Symétrique |
| `updateCompanyAssoc(contact, company, request)` | Mise à jour pivot |
| `show(id)` | Retourne le contact avec `companies`, `deals`, `owner` en N-M |

#### DealController — nouvelles méthodes

| Méthode | Description |
|---------|-------------|
| `attachContact(deal, request)` | Attache un contact (rôle : primary/technical/billing/other) |
| `detachContact(deal, contact)` | Détache |
| `updateContactAssoc(deal, contact, request)` | Mise à jour rôle |
| `attachCompany(deal, request)` | Attache une entreprise (rôle : customer/partner/reseller) |
| `detachCompany(deal, company)` | Détache |
| `updateCompanyAssoc(deal, company, request)` | Mise à jour rôle + is_primary |
| `show(id)` | Retourne le deal avec `contacts`, `companies`, `stage`, `pipeline`, `owner` |

---

### 6. Nouvelles routes REST (12 routes)

Ajoutées dans `routes/api.php` sous le middleware `jwt` :

```
POST   /companies/{company}/contacts
DELETE /companies/{company}/contacts/{contact}
PATCH  /companies/{company}/contacts/{contact}

POST   /contacts/{contact}/companies
DELETE /contacts/{contact}/companies/{company}
PATCH  /contacts/{contact}/companies/{company}

POST   /deals/{deal}/contacts
DELETE /deals/{deal}/contacts/{contact}
PATCH  /deals/{deal}/contacts/{contact}

POST   /deals/{deal}/companies
DELETE /deals/{deal}/companies/{company}
PATCH  /deals/{deal}/companies/{company}
```

---

### 7. Import société enrichi

#### ImportController

**Fichier :** `app/Http/Controllers/Api/ImportController.php`

Ajouts dans `CORE_FIELD_LABELS['contact']` :
```
__company_domain    → "Domaine entreprise"
__company_industry  → "Secteur d'activité entreprise"
__company_phone     → "Téléphone entreprise"
__company_website   → "Site web entreprise"
__company_city      → "Ville entreprise"
__company_country   → "Pays entreprise"
__company_lifecycle → "Cycle de vie entreprise"
```

Nouvelle constante `COMPANY_FIELD_ALIASES` : mappe les noms FR/EN courants vers les champs `__company_*`. Exemple :
```
"domaine"     → __company_domain
"secteur"     → __company_industry
"site web"    → __company_website
```

La méthode `buildAutoMapping()` étend le parcours pour tester ces aliases.

#### ProcessCsvImport

**Fichier :** `app/Jobs/ProcessCsvImport.php`

Refactor de `handle()` pour extraire tous les champs `__company_*` avant le filtre `$fillable`, puis déléguer à `resolveCompany()` :

```php
private function resolveCompany(array $companyPayload): int
```

- Mappe les clés virtuelles `__company_*` vers les colonnes réelles (`name`, `domain`, `industry`, etc.)
- Cherche d'abord par `domain` (unique), sinon par `name`
- Utilise `Company::firstOrCreate()` pour éviter les doublons
- Enrichit la company existante avec les nouveaux champs découverts
- Retourne l'`id` pour l'attachement via pivot

Après `Contact::create()`, si un `attachCompanyId` est présent :
```php
$contact->companies()->attach($id, ['role' => 'employee', 'is_primary' => true]);
```

---

### 8. Factories & Seeder

**Fichiers créés :**
- `database/factories/CompanyFactory.php` — génère 20 industries/villes/pays aléatoires
- `database/factories/ContactFactory.php` — génère prénom, nom, email, poste, téléphone
- `database/factories/DealFactory.php` — génère nom, montant, devise, statut, dates

**DatabaseSeeder enrichi** :
- 20 companies avec lifecycle_stage variés
- 50 contacts, chacun attaché à 1 company (90%) ou 2 companies (10%) via pivot `contact_company`
- 30 deals, chacun associé à 1-3 contacts et 1-2 companies via pivots

---

### 9. Tests v1.3a

**`tests/Feature/AssociationTest.php`** — 11 cas :
- Attach contact ↔ company (rôle valide, doublon rejeté)
- Detach contact ↔ company
- Update rôle sur pivot
- Attach deal ↔ contact, deal ↔ company
- Vérification `is_primary` unique par deal
- Présence d'une entrée `audit_logs` après attach/detach

**`tests/Feature/CsvImportTest.php`** — 2 cas supplémentaires :
- Import contact avec `__company_domain` + `__company_industry` → company créée avec `domain` et `industry` renseignés, contact attaché via pivot
- Import contact avec domain déjà existant → réutilise la company, n'en crée pas de nouvelle

**`tests/Feature/CrmApiTest.php`** — adapté au N-M :
- Les endpoints `show` retournent `companies` et `contacts` en tableaux (plus de `company_id` scalaire)
- `POST /contacts` n'accepte plus `company_id`

**Résultat :** 23/23 tests verts.

---

## v1.3b — Foundation UX

### 1. Page détail 3 colonnes

**Fichier :** `resources/views/crm.blade.php` (+751 lignes)

Nouveau `mode: 'detail'` dans l'objet `state`. Navigation déclenchée par `data-open-detail="id"` sur les lignes de tableau.

```
┌───────────────────────────────────────────────────────────┐
│  Topbar (breadcrumb : Entreprises > Acme Corp)            │
├─────────────────┬──────────────────────────┬──────────────┤
│ Propriétés      │  Onglets :               │  Associés    │
│  éditables      │   - Aperçu               │  contacts(N) │
│  Lifecycle      │   - Activités (timeline) │  deals(N)    │
│  Lead status    │   - Historique           │  companies   │
│  Owner          │   - IA                   │  + Ajouter   │
│  Custom fields  │                          │  + édit rôle │
└─────────────────┴──────────────────────────┴──────────────┘
```

CSS : `.layout-detail { display:grid; grid-template-columns:300px 1fr 320px; gap:16px }`

#### Fonctions JavaScript créées

| Fonction | Description |
|----------|-------------|
| `openDetailPage(id)` | Charge la fiche via API, passe en mode `detail`, appelle `renderShell()` |
| `renderDetailPage()` | Orchestrateur : appelle les 3 panneaux |
| `renderPropertiesPanel(record)` | Colonne gauche — propriétés core + lifecycle dropdown + lead_status |
| `renderCenterPanel(record)` | Colonne centrale — onglets avec contenu dynamique |
| `renderAssociationsPanel(record)` | Colonne droite — liste N-M avec chips |
| `renderAssocChip(item, type)` | Chip individuel : nom, badge rôle, badge Princ., bouton ✕ |
| `bindDetailPageEvents()` | Lie tous les event listeners de la page détail |
| `goBackToList()` | Retour : mode board → `loadCurrent()`, sinon `renderShell()` avec rows existants |

---

### 2. Lifecycle badges dans les listes

La fonction `renderCell()` dans `renderTable()` détecte le champ `lifecycle_stage` et génère un badge coloré :

```html
<span class="lc-badge lc-lead">lead</span>
<span class="lc-badge lc-customer">customer</span>
<!-- etc. -->
```

CSS : `.lc-lead { background:#e8f4fd; color:#1a6fa8 }`, `.lc-customer { background:#d4edda; color:#155724 }`, etc.

---

### 3. Panneau associations N-M

#### openAssociationPicker(parentType, parentId, childType)

Modal générique avec :
- Input texte debounced (300ms) → `GET /<childType>?search=...`
- Dropdown de résultats
- Select de rôle (valeurs selon la relation)
- Bouton "Associer" → `POST /<parentType>/:id/<childType>s` + refresh de la page détail

#### Chips d'association

Chaque association affichée comme chip :
- Nom cliquable (ouvre la page détail de l'associé)
- Badge rôle (ex: "employee")
- Badge "Princ." si `is_primary = true`
- Bouton ✕ → `DELETE` sur le pivot + refresh

---

### 4. Modal quick-create deal

**Fonction :** `openDealModal(prefill = {})`

Champs :
- `name` (texte)
- `amount` + `currency`
- `pipeline` : select chargé async depuis `GET /pipelines`
- `stage` : select dépendant, rechargé async depuis `GET /pipelines/:id` lorsque le pipeline change
- `owner` : select chargé depuis `GET /users`
- `companies` : autocomplete async `GET /companies?search=...`, multi-sélection
- `contacts` : autocomplete async `GET /contacts?search=...`, multi-sélection

Flux de création :
1. `POST /deals` → crée le deal
2. Pour chaque company sélectionnée : `POST /deals/:id/companies`
3. Pour chaque contact sélectionné : `POST /deals/:id/contacts`
4. Fermeture de la modal + message de succès

Le `prefill` permet de pré-remplir depuis une page détail company ou contact :
```javascript
openDealModal({ companies: [{ id: 8, name: 'Acme Corp' }] })
```

---

### 5. UserController + route GET /users

**Fichier :** `app/Http/Controllers/Api/UserController.php` (nouveau)

```php
public function index(): JsonResponse
{
    return response()->json([
        'data' => User::query()->select(['id','name','email','role'])->orderBy('name')->get()
    ]);
}
```

Route ajoutée dans `routes/api.php` (sous middleware `jwt`) :
```php
Route::get('/users', [UserController::class, 'index']);
```

---

### 6. PipelineController::show()

**Fichier :** `app/Http/Controllers/Api/PipelineController.php`

Override de `show()` pour charger les stages ordonnés par position :

```php
public function show(int $id): JsonResponse
{
    $pipeline = Pipeline::query()
        ->with(['stages' => fn ($q) => $q->orderBy('position')])
        ->findOrFail($id);
    return response()->json(['data' => $pipeline]);
}
```

Nécessaire pour que le select dépendant dans la modal deal affiche les étapes dans le bon ordre.

---

## Bugs corrigés

### Bug 1 — Board bloqué après retour depuis la page détail

**Symptôme :** Cliquer "Retour" depuis une page détail deal relançait `renderShell()` avec `state.board = null`, affichant "Chargement du board." indéfiniment.

**Cause :** `goBackToList()` vérifiait `state.rows.length` pour décider de recharger ou non, mais en mode board `state.rows` pouvait être peuplé sans que `state.board` le soit.

**Fix :**
```javascript
function goBackToList() {
    state.mode = state.current === 'deals' ? 'board' : 'list';
    state.detailId = null;
    state.detailData = null;
    if (state.mode === 'board' || !state.rows.length) {
        loadCurrent();   // recharge toujours en mode board
    } else {
        renderShell();
    }
}
```

---

### Bug 2 — IA TypeError : `int` attendu, `string "undefined"` reçu

**Symptôme :** Cliquer "Résumer" ou "Prochain action" sur une page détail déclenchait :
```
App\Http\Controllers\Api\AiController::summarizeContact():
Argument #1 ($id) must be of type int, string given
```

**Cause :** `bindAiButtons()` utilisait `state.selected?.id`, qui vaut `undefined` en mode `detail` (car `state.selected` n'est pas peuplé sur une page détail). La route `/ai/summarize/contact/undefined` envoyait la string `"undefined"` à PHP.

**Fix :**
```javascript
const id = state.detailId ?? state.selected?.id;
```

`state.detailId` est l'entier de la fiche ouverte en mode détail, `state.selected?.id` reste le fallback pour l'ancien mode panneau latéral.

---

### Bug 3 — CSV import : contacts invisibles après import

**Symptôme :** Après import d'un CSV de 1800 contacts, aucun contact n'apparaissait dans la liste.

**Cause :** La colonne `industry` (et d'autres) recevait des valeurs de plusieurs centaines de caractères (ex: descriptions longues dans un export HubSpot), dépassant la limite `varchar(255)` de PostgreSQL → `PDOException: value too long for type character varying(255)` → tous les contacts de chaque batch échouaient.

**Fix :** Ajout du helper `truncateStrings()` dans `ProcessCsvImport` :

```php
private function truncateStrings(array $data, int $max = 255): array
{
    return array_map(
        fn ($v) => is_string($v) ? mb_substr($v, 0, $max) : $v,
        $data
    );
}
```

Appliqué à deux endroits :
1. Payload contact/company avant insertion : `$payload = $this->truncateStrings(...)`
2. Payload company dans `resolveCompany()` : `$mapped = $this->truncateStrings($mapped)`

---

### Bug 4 — Tests qui vidaient la base de données de développement

**Symptôme :** Lancer `php artisan test` exécutait des migrations de reset sur `crm_ultimate` (la DB de dev), effaçant toutes les données.

**Cause :** `phpunit.xml` ne précisait que `DB_CONNECTION=pgsql` sans `DB_DATABASE`, donc Laravel utilisait la valeur de `.env` (`crm_ultimate`).

**Fix :**

1. Créer la base de test : `CREATE DATABASE crm_test;`

2. Ajouter dans `phpunit.xml` :
   ```xml
   <env name="DB_CONNECTION" value="pgsql"/>
   <env name="DB_DATABASE" value="crm_test"/>
   ```

Les tests utilisent maintenant `crm_test` (avec `RefreshDatabase` qui la réinitialise à chaque run) et `crm_ultimate` reste intacte.

---

### Bug 5 — Dashboard bloqué sur "Chargement des indicateurs..."

**Symptôme :** Après un redémarrage Docker, le dashboard affichait "Chargement des indicateurs..." indéfiniment sans jamais afficher les KPIs.

**Cause :** Après `docker compose restart`, la base de données `crm_ultimate` était vide (les données n'avaient pas été re-seedées). Les appels API `/auth/me` et `/dashboard` retournaient des erreurs, et `state.dashboardData` restait `null`.

**Diagnostic effectué :**
```bash
# Vérification que l'API est accessible
curl -X POST http://localhost:8080/api/v1/auth/login ...
# → 200 OK avec token

# Test du endpoint dashboard avec le token
curl http://localhost:8080/api/v1/dashboard -H "Authorization: Bearer $TOKEN"
# → 200 OK avec les KPIs
```

**Fix :** Re-seeder la base après chaque `migrate:fresh` :
```bash
docker compose exec app php artisan migrate:fresh --seed
```

Le dashboard affiche ensuite : 24 deals ouverts, 1 762 706,93 EUR de pipeline, 50% taux de conversion sur 30j.

**Bonne pratique :** Toujours exécuter `migrate:fresh --seed` après un restart Docker si le volume PostgreSQL a été recréé.

---

## Résumé des fichiers modifiés/créés

### v1.3a

| Fichier | Action |
|---------|--------|
| `database/migrations/2026_05_17_000001_v1_3_a_associations_lifecycle.php` | Créé |
| `app/Models/Concerns/HasLifecycle.php` | Créé |
| `app/Services/AssociationAuditor.php` | Créé |
| `database/factories/CompanyFactory.php` | Créé |
| `database/factories/ContactFactory.php` | Créé |
| `database/factories/DealFactory.php` | Créé |
| `tests/Feature/AssociationTest.php` | Créé |
| `tests/Feature/CsvImportTest.php` | Étendu (+2 cas) |
| `app/Models/Contact.php` | Relations N-M |
| `app/Models/Company.php` | Relations N-M |
| `app/Models/Deal.php` | Relations N-M |
| `app/Http/Controllers/Api/CompanyController.php` | +attachContact/detach/update/show |
| `app/Http/Controllers/Api/ContactController.php` | +attachCompany/detach/update/show |
| `app/Http/Controllers/Api/DealController.php` | +attachContact/Company/detach/update/show |
| `app/Http/Controllers/Api/ImportController.php` | +COMPANY_FIELD_ALIASES, 7 labels |
| `app/Jobs/ProcessCsvImport.php` | resolveCompany(), COMPANY_VIRTUAL_FIELDS |
| `app/Http/Controllers/Api/AiController.php` | Adapté identifiants N-M |
| `database/seeders/DatabaseSeeder.php` | Factories + pivots N-M |
| `routes/api.php` | +12 routes pivot |
| `tests/Feature/CrmApiTest.php` | Adapté N-M |

### v1.3b

| Fichier | Action |
|---------|--------|
| `resources/views/crm.blade.php` | +751 lignes (detail page, modal deal, badges, picker) |
| `app/Http/Controllers/Api/UserController.php` | Créé |
| `app/Http/Controllers/Api/PipelineController.php` | Override show() |
| `routes/api.php` | +GET /users |

### Corrections

| Fichier | Correction |
|---------|------------|
| `resources/views/crm.blade.php` | goBackToList() + bindAiButtons() detailId |
| `app/Jobs/ProcessCsvImport.php` | truncateStrings() |
| `phpunit.xml` | DB_DATABASE=crm_test |

---

## Architecture du modèle de données final

```
companies ──────────────────────────────────────────────────
    │                                                       │
    │ contact_company (role, is_primary)                    │
    │                                                       │
contacts ──────────────────────────────────────────────────
    │                                                       │
    │ deal_contact (role)          deal_company (role, is_primary)
    │                                   │
    └──────────── deals ────────────────┘

Tous les modèles :
- Company  : lifecycle_stage, lead_status
- Contact  : lifecycle_stage, lead_status
- Deal     : status (open/won/lost)
```

---

## Commandes utiles

```bash
# Relancer l'environnement proprement
docker compose restart
docker compose exec app php artisan migrate:fresh --seed

# Lancer les tests (sur crm_test, sans toucher crm_dev)
docker compose exec app php artisan test

# Vérifier les associations en base
docker compose exec postgres psql -U crm -d crm_ultimate -c "SELECT * FROM contact_company LIMIT 10;"
docker compose exec postgres psql -U crm -d crm_ultimate -c "SELECT * FROM deal_contact LIMIT 10;"

# Tester l'API manuellement
TOKEN=$(curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}' | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)
curl http://localhost:8080/api/v1/dashboard -H "Authorization: Bearer $TOKEN"
curl http://localhost:8080/api/v1/companies/1 -H "Authorization: Bearer $TOKEN"
```

---

## Prochaine étape : v1.3c — Segments dynamiques

La v1.3c (non démarrée) prévoit :
- Table `segments` avec règles JSON (arbre AND/OR)
- `SegmentQuery` engine : opérateurs `eq`, `neq`, `in`, `contains`, `gt`, `between`, `days_ago_*`, etc.
- `SegmentController` + routes `/segments` + `/segments/:id/records` + `/segments/fields/:type`
- UI builder visuel (field select + operator + value input dynamiques)
- Lifecycle automation : deal `won` → contact primaire passe en `customer`
