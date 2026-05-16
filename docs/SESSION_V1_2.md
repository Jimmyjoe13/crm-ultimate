# Session v1.2 — Import robuste + Mapping CSV + Custom Fields UI

Date : 2026-05-16

---

## Contexte de départ

Après la v1.1 (AI résumés, dashboard KPI, recherche globale, badge tâches), l'usage réel a révélé trois problèmes critiques :

1. **Import tronqué** : sur ~1800 contacts, seuls ~50 étaient importés. Cause : queue worker tué après 60s (timeout par défaut Laravel), et chaque `create()` Eloquent dans une `DB::transaction()` provoquait une erreur PostgreSQL en cascade (voir plus bas).
2. **Aucun mapping manuel** : si une colonne CSV n'est pas reconnue automatiquement, elle est ignorée silencieusement.
3. **Aucun décompte réel** : l'UI affichait "50 element(s)" (limite per_page), l'utilisateur croyait que seuls 50 contacts avaient été importés.

Objectifs validés pour cette session :
- Rendre l'import robuste pour de gros volumes (1800+ lignes).
- Ajouter un mapping visuel : upload → preview 5 lignes → dropdowns pour mapper chaque colonne.
- Skip de doublons (email pour contacts, domain pour companies, name+company_id pour deals).
- Import HubSpot-style : une colonne "Société" dans un CSV contact crée/lie automatiquement l'entreprise.
- UI custom fields : admin/manager créent les champs, ils apparaissent dans tous les formulaires.
- Affichage correct du total importé avec pagination.

---

## Tranche 1 — Import robuste

### 1.1 Timeout queue worker

**`docker-compose.yml`** — service `queue` :
```yaml
command: php artisan queue:work --sleep=1 --tries=3 --timeout=600 --memory=512
```
Avant : timeout par défaut 60s, le job de 1800 lignes était tué en cours de route.

**`docker/php/php.ini`** :
```ini
memory_limit = 512M
```

### 1.2 Bug critique PostgreSQL — DB::transaction abortée

**Problème** : le batch de `create()` était enveloppé dans `DB::transaction()`. Quand la première ligne échouait (ex. valeur texte pour un champ `bigint`), PostgreSQL marquait la transaction comme ABORTED. Toutes les lignes suivantes échouaient avec `"current transaction is aborted, commands ignored until end of transaction block"`, même si elles étaient dans un try/catch individuel. Résultat : 1797/1801 lignes en erreur.

**Fix** : suppression totale de `DB::transaction` dans `flushBuffer()`. Chaque `create()` est maintenant indépendant avec son propre try/catch. Les ~4 lignes réellement invalides échouent seules, les 1797 autres passent.

**`app/Jobs/ProcessCsvImport.php` — `flushBuffer()`** :
```php
private function flushBuffer(array $buffer, string $model, int &$failed, array &$errors): void
{
    // Each create is independent — no wrapping transaction.
    // PostgreSQL aborts the whole transaction on any error, so wrapping
    // a batch in one transaction would silently kill all rows after the first failure.
    foreach ($buffer as ['row' => $row, 'payload' => $payload]) {
        try {
            $model::query()->create($payload);
        } catch (\Throwable $e) {
            $failed++;
            if (count($errors) < 100) {
                $errors[] = ['row' => $row, 'message' => $e->getMessage()];
            }
        }
    }
}
```

### 1.3 Déduplication en mémoire

Pour éviter 1800 requêtes SQL de type `exists()`, les clés existantes sont chargées en une seule requête au départ du job, puis enrichies au fil du traitement pour catcher aussi les doublons intra-fichier.

```php
private function loadExistingKeys(string $entityType): array
{
    return match ($entityType) {
        'contact' => Contact::query()->whereNotNull('email')->pluck('email')->flip()->toArray(),
        'company' => Company::query()->whereNotNull('domain')->pluck('domain')->flip()->toArray(),
        'deal'    => Deal::query()->selectRaw("name || '||' || COALESCE(company_id::text, '') AS k")
                        ->pluck('k')->flip()->toArray(),
        default => [],
    };
}
```

Clés de déduplication :
- `contact` → `email`
- `company` → `domain`
- `deal` → `name || company_id`

### 1.4 Migration : nouveaux champs import_jobs

**`database/migrations/2026_05_16_000001_add_import_job_columns.php`** :
```php
$table->unsignedInteger('duplicates_skipped')->default(0)->after('failed_rows');
$table->jsonb('mapping')->nullable()->after('duplicates_skipped');
```

**`app/Models/ImportJob.php`** : ajout de `duplicates_skipped` et `mapping` au `$fillable`, et `'mapping' => 'array'` dans `casts()`.

### 1.5 Tests feature

**`tests/Feature/CsvImportTest.php`** (nouveau fichier) :
- `test_import_large_csv_processes_all_rows_and_skips_duplicates` : 1510 lignes (1500 uniques + 10 doublons), dispatch synchrone, asserte 1500 processed + 10 duplicates_skipped + 0 failed.
- `test_import_applies_user_mapping` : mapping utilisateur explicite sur headers non-standards.
- `test_import_preview_endpoint_returns_headers_and_sample` : test HTTP du nouvel endpoint `/imports/preview`.

**Attention** : `RefreshDatabase` dans les tests efface la base. Après chaque `php artisan test`, relancer :
```bash
docker compose exec app php artisan migrate:fresh --seed
```

---

## Tranche 2 — Preview + mapping manuel CSV

### 2.1 Endpoint preview

**`POST /api/v1/imports/preview`** (protégé `role:admin,manager`)

Fonctionnement :
1. Reçoit `entity_type` + `file` (CSV).
2. Stocke le fichier dans `storage/app/private/imports/preview/`.
3. Lit les headers + 5 premières lignes de données.
4. Retourne un `preview_token` (path du fichier stocké) + headers + auto_mapping + available_fields + sample_rows.

```json
{
  "preview_token": "imports/preview/abc123.csv",
  "headers": ["Prénom", "Nom", "Email", "Société"],
  "auto_mapping": { "Prénom": "first_name", "Nom": "last_name", "Email": "email", "Société": "__company_name" },
  "available_fields": [
    { "key": "first_name", "label": "Prénom", "type": "core" },
    { "key": "__company_name", "label": "Nom entreprise (créer/lier)", "type": "core" },
    { "key": "custom_segment", "label": "Segment", "type": "custom" }
  ],
  "sample_rows": [["Marie", "Durand", "marie@acme.fr", "ACME"]]
}
```

**Sécurité** : `store()` valide que `preview_token` commence par `imports/preview/` et passe `Storage::exists()` pour éviter toute path traversal.

### 2.2 Import HubSpot-style : colonne Société → create/link company

**Problème initial** : la colonne "Société" d'un CSV contact était mappée à `company_id` (champ bigint), ce qui provoquait `SQLSTATE[22P02]: invalid input syntax for type bigint: "DETECTALENTS"`.

**Solution** : champ virtuel `__company_name` (non présent dans `$fillable`).

Dans `ProcessCsvImport::handle()` :
```php
$companyName = null;
if (isset($combined['__company_name']) && $combined['__company_name'] !== '') {
    $companyName = $combined['__company_name'];
}
$payload = array_filter(array_intersect_key($combined, $fillable), fn($v) => $v !== '' && $v !== null);

// Après construction du payload :
if ($companyName !== null && $job->entity_type === 'contact') {
    $company = Company::query()->firstOrCreate(['name' => $companyName]);
    $payload['company_id'] = $company->id;
}
```

**Auto-mapping** : `ImportController` détecte automatiquement les colonnes avec des noms courants de société et suggère `__company_name` :

```php
private const COMPANY_NAME_ALIASES = [
    'societe', 'société', 'entreprise', 'company', 'company_name',
    'nom_entreprise', 'nom_societe', 'organization', 'organisation',
    'account', 'account_name',
];
```

### 2.3 Endpoint store — deux modes

`POST /api/v1/imports` accepte maintenant deux modes :

- **Legacy** : `entity_type` + `file` → auto-mapping (compat v1.1).
- **Avec mapping** : `entity_type` + `preview_token` + `mapping` (JSON `{ "csv_header": "field_key" | null }`).

Le fichier uploadé lors du preview est déplacé de `imports/preview/` vers `imports/` au moment du store.

### 2.4 UI mapping dans crm.blade.php

Flux en 2 étapes :

**Étape 1 — Upload** : sélection entity_type + fichier → POST `/imports/preview` → affiche la table de mapping.

**Étape 2 — Mapping** : table avec une ligne par colonne CSV :
```
| Colonne CSV    | Mapper vers              | Aperçu (5 lignes)         |
| "Prénom"       | [Prénom (core) ▾]        | Marie, Jean, Alice, ...   |
| "Société"      | [Nom entreprise ✦ ▾]     | ACME, Dupont SAS, ...     |
| "Ref interne"  | [Ignorer ▾] [+ Créer]    | REF001, REF002, ...       |
```

Fonctions JS ajoutées dans `crm.blade.php` :
- `renderImportPanel()` — dispatch vers `renderUploadStep()` ou `renderMappingStep()`
- `previewImport(event)` — POST `/imports/preview`, stocke dans `state.importPreview`, re-render
- `renderMappingStep(p, entityType)` — génère la table avec selects pré-remplis par `auto_mapping`
- `createCustomFieldFromMapping(rowIdx, entityType, csvHeader)` — POST `/custom-fields`, met à jour tous les selects
- `cancelCreateField(rowIdx)` — masque le mini-formulaire inline
- `finishImport(entityType)` — POST `/imports` avec `preview_token` + mapping sélectionné
- `bindImportMappingEvents()` — wire les boutons reset/confirm et l'événement `change` pour `__create_new__`

Option `+ Créer un champ personnalisé` disponible dans chaque dropdown de mapping. Quand sélectionnée : mini-formulaire inline (key, label, type) → POST `/custom-fields` → option ajoutée à tous les selects de la session.

---

## Tranche 3 — Custom Fields UI

### 3.1 Page de gestion "Champs perso"

Nouvelle entrée dans le `resources` JS de `crm.blade.php`, visible uniquement pour `admin` et `manager` :

```js
'custom-fields': {
    label: 'Champs perso',
    icon: 'CF',
    adminOnly: true,
    endpoint: '/custom-fields',
    fields: [
        ['entity_type', 'Entité', 'select', true, ['company', 'contact', 'deal']],
        ['key', 'Clé', 'text', true],
        ['label', 'Label', 'text', true],
        ['field_type', 'Type', 'select', true, ['text', 'number', 'date', 'boolean', 'select']],
        ['is_required', 'Obligatoire', 'boolean', false],
        ['position', 'Position', 'number', false],
    ],
    details: ['id', 'entity_type', 'key', 'label', 'field_type', 'is_required', 'position'],
}
```

Filtre sidebar basé sur `adminOnly` :
```js
Object.entries(resources).filter(([, item]) =>
    !item.adminOnly || ['admin', 'manager'].includes(state.user?.role)
)
```

### 3.2 Champs custom dans les formulaires entités

`loadCustomFields(entityType)` — appelée non-bloquante depuis `loadList()` :
```js
async function loadCustomFields(entityType) {
    const data = await request(`/custom-fields?entity_type=${entityType}&per_page=100`);
    state.customFieldsCache[entityType] = data.data || [];
}
```

`renderForm()` injecte les custom fields après les champs core. `renderCustomField(cf, value)` génère l'input selon `cf.field_type` (`text`, `number`, `date`, `boolean`, `select`).

`formPayload()` gère le pattern `custom_values[key]` pour grouper les valeurs dans `payload.custom_values`.

---

## Tranche 4 — Affichage total et pagination

**Problème** : `state.rows.length` était limité à 50 (per_page), affiché tel quel → utilisateur croyait que seuls 50 contacts existaient.

**Fix** :
- `per_page` changé à 100.
- `state.totalRows` stocke `data.total` de la réponse API.
- `state.currentPage` gère la page courante.
- Header de liste : `"100 / 1797 element(s) — page 1 / 18"`.
- Boutons `←` / `→` affichés quand `totalRows > 100`.
- `navigate()` remet `state.currentPage = 1`.

---

## Fichiers modifiés ou créés

| Fichier | Action |
|---|---|
| `docker-compose.yml` | Modifié — `--timeout=600 --memory=512` sur le queue worker |
| `docker/php/php.ini` | Modifié — `memory_limit = 512M` |
| `database/migrations/2026_05_16_000001_add_import_job_columns.php` | Créé — `duplicates_skipped` + `mapping` sur `import_jobs` |
| `app/Models/ImportJob.php` | Modifié — fillable + casts |
| `app/Jobs/ProcessCsvImport.php` | Refactorisé — batch sans transaction, dedup, mapping utilisateur, `__company_name`, `getColumnMaps()` public |
| `app/Http/Controllers/Api/ImportController.php` | Refactorisé — preview(), store() 2 modes, COMPANY_NAME_ALIASES, buildAutoMapping(), buildAvailableFields() |
| `routes/api.php` | Modifié — `POST /imports/preview` dans le groupe `admin/manager` |
| `tests/Feature/CsvImportTest.php` | Créé — 3 tests feature import |
| `resources/views/crm.blade.php` | Modifié — mapping UI 2 étapes, custom fields UI, pagination, total réel |

**Non modifiés (backend déjà complet)** :
- `app/Http/Controllers/Api/CustomFieldController.php`
- `app/Models/CustomField.php`

---

## Commits de cette session

```
5566383  fix(import): 3 corrections post-test utilisateur
053aaa5  feat(v1.2): import robuste + mapping CSV + custom fields UI
b799fd9  fix: increase PHP upload limits to 50M for CSV imports
0f2598b  fix: repair CSV import header normalization and safety
17cfeae  fix(import+ui): affichage total réel + pagination + auto-mapping __company_name
```

---

## Points d'attention pour les prochaines sessions

### Base de données après les tests

Les tests PHPUnit utilisent `RefreshDatabase` qui efface la base. Après chaque run de tests, relancer le seed :
```bash
docker compose exec app php artisan migrate:fresh --seed
```
Identifiants par défaut : `admin@example.com` / `password`.

### Pattern PostgreSQL + DB::transaction

Ne jamais wrapper des `create()` Eloquent en boucle dans une seule `DB::transaction()`. Si une ligne échoue, PostgreSQL marque la transaction ABORTED et toutes les lignes suivantes échouent même avec try/catch. Traiter chaque ligne indépendamment.

### Champ virtuel __company_name

Ce champ n'existe pas en base. Il est traité AVANT le filtre `array_intersect_key($combined, $fillable)` dans `ProcessCsvImport::handle()`. L'ordre compte : extraire `$companyName` → filtrer avec `$fillable` → ensuite `firstOrCreate` company et injecter `company_id`.

### Auto-mapping Société → __company_name

`COMPANY_NAME_ALIASES` dans `ImportController` liste les variantes FR/EN de "nom d'entreprise". Si le snake_case d'un header CSV contact est dans cette liste, le mapping suggère automatiquement `__company_name`.

### Structure JS crm.blade.php

Le fichier est monolithique (~1500+ lignes). Pas de build frontend, tout est vanilla JS dans une seule vue Blade. Conventions :
- `state` : objet global de l'application.
- `resources` : config par section (endpoint, colonnes, fields, adminOnly).
- `renderShell()` : re-render complet après chaque changement d'état.
- `bindShellEvents()` : re-wire tous les event listeners après chaque render.
- `loadCustomFields(entityType)` : appelée en non-bloquant depuis `loadList()`, cache dans `state.customFieldsCache`.

### Endpoint preview_token sécurité

Le token est un chemin de fichier local. La validation dans `store()` exige que le token commence par `imports/preview/` ET que `Storage::disk('local')->exists($token)` soit vrai. Ne jamais relâcher ces deux vérifications.

---

## Ce qui reste hors scope (prévu v1.3)

- Mise à jour des enregistrements existants lors de l'import (update si doublon au lieu de skip).
- Webhooks pour intégrations externes.
- Filtrage des custom fields dans la recherche globale (stockés en JSONB, nécessite full-text).
- Gestion utilisateurs depuis l'UI.
- Permissions plus fines par rôle.
- Tests frontend end-to-end Playwright.
