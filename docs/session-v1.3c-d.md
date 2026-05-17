# Session v1.3c-d — Architecture Blade multi-pages + Segments dynamiques + Import CSV

**Date :** 16 mai 2026  
**Branches impactées :** `master`  
**Commits produits :** `d8ce178` (tronquer CSV), correctifs inline non commités (voir "À commiter")

---

## Contexte de départ

Le CRM était sur l'ancien `crm.blade.php` — une SPA monofichier de ~2000 lignes pilotée par AlpineJS sans URLs dédiées. Plusieurs problèmes bloquaient la suite :

1. **Pas de deep-link** — impossible de partager une fiche contact ou deal par URL.
2. **Segments (v1.3c)** déjà planifiés : moteur `SegmentQueryEngine` + modèle `Segment` existaient côté backend mais l'UI était absente.
3. **Import CSV** présent dans l'ancienne SPA mais perdu lors de la transition Blade.
4. **Route naming conflict** — `Route::apiResource()` générait des noms (`deals.index`, `contacts.show`, etc.) qui shadowing les routes web, rendant `route()` inutilisable dans les vues.

---

## v1.3c — Architecture Blade multi-pages

### 1. Structure de fichiers créée

```
resources/views/
  components/
    app-shell.blade.php       ← layout principal (sidebar + header)
    rail-icon.blade.php       ← item sidebar (href OU route)
  pages/
    dashboard/index.blade.php
    contacts/
      index.blade.php
      show.blade.php
    companies/
      index.blade.php
      show.blade.php
    deals/
      index.blade.php         ← table Kanban → table + rows cliquables
      show.blade.php          ← drawer 720px fixe droite (z-40)
    pipeline/index.blade.php
    activities/index.blade.php
    search/index.blade.php
    segments/
      index.blade.php
      create.blade.php        ← builder visuel AND/OR
      show.blade.php
    imports/
      create.blade.php        ← wizard 3 étapes
    settings/
      stages/index.blade.php
      fields/index.blade.php
```

### 2. Contrôleurs Web créés / modifiés

| Contrôleur | Méthodes ajoutées |
|-----------|-------------------|
| `Web\DealController` | `show()`, `markWon()`, `markLost()` |
| `Web\SegmentController` | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `preview`, `loadAllFields` |
| `Web\ImportController` | `create($entityType)`, `preview()`, `store()`, `status($id)` |

### 3. Routes web (routes/web.php)

```php
// Deals détail
Route::get('/deals/{deal}',       [DealController::class, 'show'])->name('deals.show');
Route::post('/deals/{deal}/won',  [DealController::class, 'markWon'])->name('deals.won');
Route::post('/deals/{deal}/lost', [DealController::class, 'markLost'])->name('deals.lost');

// Segments
Route::get('/segments',                 [SegmentController::class, 'index']);
Route::get('/segments/create',          [SegmentController::class, 'create']);
Route::post('/segments',                [SegmentController::class, 'store']);
Route::post('/segments/preview',        [SegmentController::class, 'preview']);
Route::get('/segments/{segment}',       [SegmentController::class, 'show']);
Route::get('/segments/{segment}/edit',  [SegmentController::class, 'edit']);
Route::put('/segments/{segment}',       [SegmentController::class, 'update']);
Route::delete('/segments/{segment}',    [SegmentController::class, 'destroy']);

// Import CSV
Route::get('/imports/{entityType}/create', [ImportController::class, 'create']);
Route::post('/imports/preview',            [ImportController::class, 'preview']);
Route::post('/imports',                    [ImportController::class, 'store']);
Route::get('/imports/{id}/status',         [ImportController::class, 'status']);
```

**Règle critique — ne jamais utiliser `route()` dans les vues web** : `Route::apiResource()` (api.php) génère des noms identiques (`deals.index`, `contacts.show`…) qui shadowing les routes web. Utiliser des URLs hardcodées partout : `'/contacts/' . $contact->id`.

### 4. Layout app-shell

**Fichier :** `resources/views/components/app-shell.blade.php`

- Props : `$active` (nom de la section active, cible `rail-ic on`), `$breadcrumb`
- Sidebar rail : Dashboard, Deals, Pipeline, Contacts, Entreprises, Activités, **Segments** (icône sunburst), Settings
- Header : searchbar → `/search`, bouton "Nouveau deal", bouton "Tâches"
- Raccourci ⌘K : `window.location.href = '/search'`
- URLs toutes hardcodées (pas de `route()`)

**Fichier :** `resources/views/components/rail-icon.blade.php`

```blade
@props(['route' => null, 'href' => null, 'active' => false, 'tooltip' => ''])
<a href="{{ $href ?? ($route ? route($route) : '#') }}" class="rail-ic {{ $active ? 'on' : '' }}">
    {{ $slot }}
    @if($tooltip)<span class="tt">{{ $tooltip }}</span>@endif
</a>
```

### 5. Page détail Deal (drawer)

**Fichier :** `resources/views/pages/deals/show.blade.php`

- Table deals en arrière-plan (opacity-30, pointer-events-none)
- Backdrop z-30 cliquable → retour `/deals`
- Drawer 720px fixe droite, z-40, overflow-y-auto
- En-tête : avatar coloré + nom deal + chip étape + méta (DEAL-XXXX, date, activités, close date)
- Barre de progression étapes (couleurs selon position relative)
- Corps 2 colonnes : timeline activités (gauche) + sidebar propriétés (droite, bg surface2)
- Actions : "Marquer gagné" / "Marquer perdu" via formulaires POST séparés
- Bouton ✕ → `href="/deals"`

---

## v1.3c — Segments dynamiques (UI)

Le backend existait déjà (`SegmentQueryEngine`, `Segment`, migrations). Cette session a livré l'UI complète.

### 6. Liste des segments

**Fichier :** `resources/views/pages/segments/index.blade.php`

- Table : nom, entité (chip coloré), membres (num-mono), calculé (diffForHumans), créé par (avatar)
- Boutons éditer/supprimer (admin/manager uniquement via `auth()->user()?->role`)
- Bouton "+ Nouveau segment" visible seulement pour admin/manager

### 7. Builder visuel de segments

**Fichier :** `resources/views/pages/segments/create.blade.php`

#### Problème JSON dans x-data (fix critique)

`@json($fieldsByEntity)` dans un attribut `x-data` casse Alpine quand le JSON contient des guillemets. Fix : passer via `window.__*` dans un `<script>` séparé.

```blade
<script>
window.__segFields = @json($fieldsByEntity);
window.__segRules  = {!! json_encode($segment?->rules ?: ['op'=>'AND','rules'=>[]]) !!};
</script>
<div x-data="segmentBuilder(window.__segRules, '{{ $initEntity }}', window.__segFields)" x-init="init()">
```

#### Composant Alpine `segmentBuilder()`

Fonctions clés :
- `renderGroup(node, path)` — rendu récursif HTML de l'arbre AND/OR
- `renderLeaf(rule, path)` — une ligne : select champ + select opérateur + input valeur
- `opsForField(fieldMeta)` — opérateurs adaptatifs selon `fieldMeta.type` (text/number/date/rel)
- `addRule(path)` / `addGroup(path)` / `removeNode(path)` / `updateLeaf(path, key, val)` / `toggleOp(path)`
- `refreshPreview()` — `POST /segments/preview` avec XSRF-TOKEN cookie, retourne `{count, sample}`
- `schedulePreview()` — debounce 500ms
- `submitForm()` — remplit le formulaire caché et le soumet

#### Preview endpoint (web, session auth)

`POST /segments/preview` → `Web\SegmentController::preview()` — délègue directement au `SegmentQueryEngine`, pas besoin de JWT. Renvoie `{count, sample}`.

**Pourquoi pas `/api/v1/segments/preview`** : les routes API nécessitent `Authorization: Bearer`, inaccessible depuis JS côté web (token dans cookie HttpOnly).

### 8. Page affichage segment

**Fichier :** `resources/views/pages/segments/show.blade.php`

- En-tête : nom, chip entité, count membres, last computed
- Bloc JSON des règles (code monospace)
- Table paginée avec colonnes adaptées : contact (email, lifecycle) / company (industrie, ville) / deal (montant, étape)
- Pagination manuelle `/segments/{id}?page=X`

---

## v1.3d — Import CSV (restauration)

### 9. Contrôleur web ImportController

**Fichier :** `app/Http/Controllers/Web/ImportController.php`

Réimplémentation autonome (pas d'injection de l'API controller) avec session auth.

Méthodes :
- `create($entityType)` → rendu vue, valide que `$entityType` ∈ {contact, company, deal}
- `preview(Request $r)` → upload CSV, parse headers + 5 lignes sample, auto-mapping, retourne JSON
- `store(Request $r)` → valide `preview_token` (doit commencer par `imports/preview/`), déplace le fichier, crée `ImportJob`, dispatch `ProcessCsvImport`
- `status($id)` → retourne `{status, total_rows, processed_rows, failed_rows, duplicates_skipped, errors}`

#### Fix auto-mapping : champs exacts non reconnus

**Problème** : `buildAutoMapping()` cherche dans `ProcessCsvImport::COLUMN_MAPS` (qui ne contient que des *alias*). Une colonne nommée `email` dans le CSV ne matchait pas car `email` est un nom de champ direct, pas un alias.

**Fix** : après les alias, vérifier si le header matche directement une clé de `CORE_FIELD_LABELS[$entityType]` :

```php
} elseif (in_array($lower, $coreKeys, true)) {
    $mapping[$header] = $lower;
} elseif (in_array($snake, $coreKeys, true)) {
    $mapping[$header] = $snake;
```

### 10. Wizard import 3 étapes (Alpine.js)

**Fichier :** `resources/views/pages/imports/create.blade.php`

**Étape 1 — Upload :**
- Select entité (pré-rempli depuis URL param `$entityType`)
- Zone drag & drop + input file caché (`$refs.fileInput`)
- `uploadAndPreview()` → `fetch('/imports/preview', FormData)` avec XSRF-TOKEN

**Étape 2 — Mapping :**
- Tableau aperçu (5 lignes sample)
- Tableau mapping : colonne CSV → `<select>` dropdown champ CRM
- Auto-mapping pré-rempli (modifiable manuellement)

**Fix `x-model` + `x-for` imbriqué :**
`x-model` sur un `<select>` avec options générées par `x-for` ne synchronise pas correctement (le modèle est appliqué avant que les `<option>` existent). Fix : utiliser `:selected="mapping[header] === f.key"` + `@change="mapping[header] = $event.target.value"` à la place.

**Étape 3 — Progression :**
- Polling toutes les 1,5s sur `/imports/{id}/status`
- Affiche : en attente / en cours (X/N lignes) / terminé / échoué
- Boutons : "Voir les contacts/entreprises" + "Nouvel import"

### 11. Boutons Importer CSV

Ajoutés dans les headers des pages contacts et companies, visibles uniquement pour admin/manager :

```blade
@if(in_array(auth()->user()?->role, ['admin','manager']))
<a href="/imports/contact/create" class="btn sm ghost">
    <svg ...>upload icon</svg>
    Importer CSV
</a>
@endif
```

---

## Bugs corrigés

| Symptôme | Cause | Fix |
|----------|-------|-----|
| Sidebar → `/api/v1/deals` au lieu de `/deals` | `route('deals.index')` résolvait vers l'API resource | URLs hardcodées partout dans les vues |
| Login Playwright timeout | `browser_click` submit → navigation bloquée | `fetch('/login', { redirect: 'manual' })` + `opaqueredirect` = succès |
| `@json()` dans `x-data` casse Alpine | JSON avec guillemets incompatible attribut HTML | `window.__segFields = @json(...)` dans `<script>` séparé |
| Mapping dropdowns bloqués sur "Ignorer" | `x-model` + `x-for` imbriqué : modèle appliqué avant options | `:selected` sur `<option>` + `@change` |
| `email` non mappé automatiquement | COLUMN_MAPS ne contient que les alias, pas les noms exacts | Fallback sur `CORE_FIELD_LABELS` keys |

---

## État technique à la fin de la session

### Ce qui fonctionne ✅

- Pages multi-Blade complètes : Dashboard, Contacts, Companies, Deals (list + show drawer), Pipeline, Activities, Search, Segments (list + builder + show), Import (wizard 3 étapes), Settings
- Segment builder : arbre AND/OR illimité, opérateurs adaptatifs, preview live
- Import CSV : upload → mapping auto → job asynchrone → polling statut → résultat
- Auth web : cookie `crm_jwt` (HttpOnly), middleware `web.auth`
- Endpoints web parallèles aux API (session auth) : `/segments/preview`, `/imports/preview`, `/imports`, `/imports/{id}/status`

### À faire / backlog v1.3e

| Priorité | Tâche |
|----------|-------|
| **Critique** | Commiter les fichiers non versionnés (voir git status) |
| **Haute** | Tests Feature : un test par contrôleur web (ContactController, CompanyController, DealController, SegmentController, ImportController) |
| **Haute** | Tests unitaires SegmentQueryEngine (moteur déjà écrit, tests pas encore lancés) |
| **Moyenne** | `<x-drawer>` component avec animation Alpine slide-in 220ms |
| **Moyenne** | Atomic Blade components : `<x-button>`, `<x-chip>`, `<x-avatar>` |
| **Moyenne** | SortableJS pour drag-reorder des étapes dans settings/stages |
| **Moyenne** | Toasts Alpine store (créé dans app.js, pas encore branché à l'UI) |
| **Basse** | DemoSeeder complet (CLAUDE.md : 5 étapes, 8 champs, 10 companies, 15 contacts, 24 deals, 50 activités) |
| **Basse** | Dark mode : vérification sur tous les écrans |
| **Basse** | Export CSV membres d'un segment (route `/segments/{id}/export`) |
| **Basse** | Suppression de l'ancien `crm.blade.php` (SPA monofichier) ou archive |

### Fichiers non commités à la fin de session

```
app/Http/Controllers/Web/
  ├── DealController.php         (méthodes show/markWon/markLost ajoutées)
  ├── SegmentController.php      (nouveau)
  └── ImportController.php       (nouveau)

resources/views/
  ├── components/app-shell.blade.php   (segments ajoutés, URLs hardcodées)
  ├── components/rail-icon.blade.php   (prop href ajoutée)
  ├── pages/contacts/index.blade.php   (bouton import)
  ├── pages/companies/index.blade.php  (bouton import)
  ├── pages/deals/show.blade.php       (nouveau)
  ├── pages/segments/                  (index + create + show nouveaux)
  └── pages/imports/create.blade.php   (nouveau)

routes/web.php                   (routes deals/segments/imports ajoutées)
```

---

## Patterns à retenir pour la prochaine session

### Auth web vs API
- Routes web : session cookie `crm_jwt`, middleware `web.auth`
- Routes API : JWT Bearer dans header `Authorization`
- **Ne jamais appeler une route API depuis le JS côté web** — créer un contrôleur web miroir qui délègue au service PHP

### URLs dans les vues Blade
```blade
{{-- ❌ Casse quand apiResource shadowing les noms --}}
{{ route('contacts.show', $contact) }}

{{-- ✅ Toujours hardcoder --}}
'/contacts/' . $contact->id
```

### JSON dans Alpine
```blade
{{-- ❌ Casse avec guillemets dans le JSON --}}
<div x-data="comp(@json($data))">

{{-- ✅ Passer par window.__ --}}
<script>window.__data = @json($data);</script>
<div x-data="comp(window.__data)">
```

### x-model avec options dynamiques (x-for)
```blade
{{-- ❌ x-model résolu avant que les <option> existent --}}
<select x-model="mapping[header]">
    <template x-for="f in fields"><option :value="f.key"></option></template>
</select>

{{-- ✅ :selected sur chaque option --}}
<select @change="mapping[header] = $event.target.value">
    <option value="" :selected="!mapping[header]">— Ignorer —</option>
    <template x-for="f in fields">
        <option :value="f.key" :selected="mapping[header] === f.key"></option>
    </template>
</select>
```
