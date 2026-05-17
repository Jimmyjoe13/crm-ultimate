# Session v1.3f + v1.4a — SortableJS, Drawer, OR Fix, Toasts, Pagination, Avatar, CI, E2E

**Date :** 17 mai 2026  
**Branche :** `master`  
**Commits produits :** 4 (de `86bd680` à `a6d264e`)

---

## Commits de la session

| Hash | Message |
|------|---------|
| `86bd680` | feat(v1.3f): SortableJS stages, x-drawer deals, OR fix, toasts, pagination, avatar, dark mode |
| `4f35193` | ci: GitHub Actions — tests PHPUnit + Laravel Pint lint |
| `a6d264e` | feat(v1.4a): Tests E2E Playwright + CI GitHub Actions + fix bugs deals |

---

## v1.3f — Détail des livrables

### 1. SortableJS — Drag-reorder des étapes pipeline

**Fichiers modifiés :**
- `app/Http/Controllers/Web/Settings/StageController.php` — méthode `reorder()` ajoutée
- `resources/views/pages/settings/stages.blade.php` — handles drag + Alpine.js `stagesSort()`
- `routes/web.php` — route `POST /settings/stages/reorder` ajoutée AVANT `{stage}` pour éviter le shadowing

**Comportement :**
- Colonne "⠿" drag handle dans le tableau des étapes
- SortableJS chargé via CDN (évite le bundle Vite — une seule page en a besoin)
- `saveOrder()` : met à jour les numéros de position visuels + POST `/settings/stages/reorder` avec CSRF depuis `meta[name="csrf-token"]`
- Contrôleur : `foreach ($data['ids'] as $position => $id)` → `update(['position' => $position + 1])`

**Gotcha :** La route `reorder` doit être déclarée **avant** `Route::patch('/settings/stages/{stage}', ...)` sinon Laravel interprète `reorder` comme un ID de stage.

---

### 2. `<x-drawer>` enrichi — prop `closeUrl` + slot `$body`

**Fichier :** `resources/views/components/drawer.blade.php`

**Nouveautés :**
- Prop `closeUrl` (nullable) : au lieu de juste `open = false`, la méthode `close()` attend 180ms (animation) puis `window.location = closeUrl`
- Slot nommé `$body` : remplace le div scrollable entier (permet au caller de contrôler la structure flex/overflow — utile pour deals/show)
- Méthode Alpine `close()` centralisée — remplace tous les `open = false` individuels
- `flex-shrink-0` ajouté sur le header pour éviter le rétrécissement

```blade
x-data="{
    open: true,
    close() {
        this.open = false;
        @if($closeUrl)
            setTimeout(() => { window.location = @js($closeUrl); }, 180);
        @endif
    }
}"
```

---

### 3. `deals/show.blade.php` refactorisé avec `<x-drawer>`

**Fichier :** `resources/views/pages/deals/show.blade.php`

- Remplace le backdrop + `<aside>` manuels par `<x-drawer close-url="/deals" width="720px">`
- `<x-slot:header>` : avatar deal + nom + chip stage + ID
- `<x-slot:body>` : barre de progression stage (flex-shrink-0) + grille 2 colonnes (flex-1 overflow-hidden)
- Le bouton fermer est géré par le composant via `close()` (pas de lien manuel)

---

### 4. Fix OR dans `SegmentQueryEngine`

**Fichier :** `app/Services/SegmentQueryEngine.php`

**Bug :** Les groupes `op: "OR"` utilisaient `$query->orWhere()` directement, ce qui produisait `WHERE A OR (B AND C)` au lieu de `WHERE A AND (B OR C)`.

**Fix :** Tous les groupes (AND et OR) sont wrappés dans un `where(function($sub) {...})`. Pour les groupes OR, les enfants après le premier utilisent `$sub->orWhere(function($inner) {...})`.

```php
private function applyNode(Builder $query, array $node, string $entityType): void
{
    if (isset($node['op'])) {
        $isOrGroup = strtoupper($node['op'] ?? 'AND') === 'OR';
        $query->where(function (Builder $sub) use ($node, $entityType, $isOrGroup): void {
            $first = true;
            foreach ($node['rules'] ?? [] as $child) {
                if ($isOrGroup && ! $first) {
                    $sub->orWhere(function (Builder $inner) use ($child, $entityType): void {
                        $this->applyNode($inner, $child, $entityType);
                    });
                } else {
                    $this->applyNode($sub, $child, $entityType);
                }
                $first = false;
            }
        });
    } else {
        $this->applyLeaf(...);
    }
}
```

**Test mis à jour :** `test_or_group_children_are_ored_together` — Alice (customer), Bob (customer), Charlie (lead). Requête `AND[lifecycle=customer, OR[first_name=Alice, first_name=Charlie]]` → retourne Alice seulement.

---

### 5. Flash Toasts — `flash_toast` session + `alpine:initialized`

**Mécanisme :**
1. Le contrôleur redirige avec `->with('flash_toast', ['message' => '...', 'type' => 'success'])`
2. Le layout (`resources/views/layouts/app.blade.php`) détecte la session et injecte :
   ```blade
   @if(session('flash_toast'))
   <script>
   document.addEventListener('alpine:initialized', function () {
       window.toast(@js(session('flash_toast.message')), @js(session('flash_toast.type', 'success')));
   });
   </script>
   @endif
   ```
3. L'événement `alpine:initialized` garantit que `<x-toast-container>` est monté avant l'appel

**Branché sur :**
- `Web\DealController` : `store()` (créé), `markWon()` (gagné ✓), `markLost()` (perdu)
- `Web\SegmentController` : `store()` (créé), `update()` (mis à jour)
- `resources/views/pages/imports/create.blade.php` : dans `pollStatus()` lors des transitions `done`/`failed`

---

### 6. `<x-pagination>` — composant unifié

**Fichier :** `resources/views/components/pagination.blade.php` (NOUVEAU)

Deux modes :
- **Paginator Laravel** : `<x-pagination :paginator="$contacts" />`
- **Mode manuel** : `<x-pagination :page="$page" :last-page="$lastPage" :total="$total" :per-page="$perPage" base-url="/segments/42" />`

Remplace le code dupliqué dans : contacts/index, companies/index, deals/index, activities/index, segments/show.

---

### 7. `<x-avatar>` — composant Blade

**Fichier :** `resources/views/components/avatar.blade.php` (NOUVEAU)

```blade
@props(['name' => '', 'size' => '', 'square' => false, 'initials' => null, 'color' => null])
```

Utilise `\App\Helpers\Avatar::initials()` et `::color()`. Props : `name`, `size` (sm/lg/''), `square` (border-radius 6px), `initials` override, `color` override (c1..c5).

---

### 8. Fix dark mode — imports/create.blade.php

- `text-[var(--text-tertiary)]` → `text-tertiary` (la variable CSS `--text-tertiary` n'existe pas, c'est une classe Tailwind utilitaire)
- `text-[var(--text)]` → `text-primary`

---

## v1.4a — Détail des livrables

### 1. GitHub Actions CI

**Fichier :** `.github/workflows/ci.yml`

Trois jobs déclenchés sur `push/PR master` :

**Job `tests` (PHP 8.3 + PostgreSQL 17) :**
```yaml
services:
  postgres:
    image: postgres:17
    env: { POSTGRES_DB: crm_test, POSTGRES_USER: crm, POSTGRES_PASSWORD: secret }
```
- Setup PHP + extensions pdo_pgsql
- Cache Composer via `actions/cache@v4`
- `php artisan migrate --force`
- `php artisan test`

**Job `e2e` (needs: tests) :**
- PostgreSQL 17 séparé (`crm_e2e`)
- Node 20 + `npm ci`
- `npx playwright install chromium --with-deps`
- `php artisan db:seed --class=DemoSeeder --force`
- `php artisan serve --port=8080 &`
- `npx wait-on http://localhost:8080/login`
- `npx playwright test`
- Upload artifact `playwright-report/` sur échec

**Job `lint` :**
- `./vendor/bin/pint --test` (dry-run, ne modifie pas les fichiers)

---

### 2. Tests E2E Playwright

**Installation :** `@playwright/test` en devDependency, `npx playwright install chromium`

**Config :** `playwright.config.ts`
```typescript
use: {
    baseURL: process.env.APP_URL ?? 'http://localhost:8080',
    screenshot: 'only-on-failure',
}
```
Workers: 1, fullyParallel: false (tests séquentiels pour partager la session).

**Scripts npm ajoutés :**
```json
"e2e": "playwright test",
"e2e:ui": "playwright test --ui",
"e2e:headed": "playwright test --headed"
```

**Commande locale :**
```bash
APP_URL=http://localhost:8080 npx playwright test --reporter=list
```
⚠️ Prérequis : DemoSeeder lancé (`docker exec crm_ultimate-app-1 php artisan db:seed --class=DemoSeeder`)

**Fichiers :**

`tests/e2e/helpers.ts` :
```typescript
export const ADMIN = { email: 'admin@demo.com', password: 'password' };
// admin@demo.com = DemoSeeder (PAS admin@example.com)

export async function login(page: Page, user = ADMIN) {
    await page.goto('/login');
    await page.fill('input[name="email"]', user.email);
    await page.fill('input[name="password"]', user.password);
    await page.click('button[type="submit"]');
    await page.waitForURL(url => !url.pathname.includes('/login'));
    // login redirige vers / (route 'dashboard' = /)
}
```

`tests/e2e/auth.spec.ts` — 3 tests :
- login admin réussit
- mauvais password → erreur
- logout → `/login` (via fetch POST /logout + goto /login)

`tests/e2e/deal.spec.ts` — 4 tests :
- liste accessible
- créer via modal (⚠️ voir gotchas ci-dessous)
- ouvrir par URL directe (extrait l'ID depuis `onclick` du `<tr>`)
- marquer gagné (`force: true` pour bypasser l'animation drawer)

`tests/e2e/segment.spec.ts` — 2 tests :
- liste visible
- créer segment (champ nom = `input[x-model="name"]`, bouton = `@click="submitForm()"`)

---

### 3. Bugs deals fixés

**Bug 1 : `route()` dans Web controllers résout vers l'API**

`route('deals.index')` → `/api/v1/deals` (pas `/deals`) car les routes API enregistrées après écrasent le nom dans le registre Laravel.

**Fix :** Tous les `redirect()->route('deals.index')` dans `Web\DealController` remplacés par `redirect('/deals')`.

**Règle confirmée :** Ne jamais utiliser `route()` dans les contrôleurs Web (ni dans les vues). Hardcoder l'URL.

---

**Bug 2 : `pipeline_id` NOT NULL violation dans `store()`**

La table `deals` a `pipeline_id NOT NULL`. Le contrôleur ne le peuplait pas.

**Fix :**
```php
$stage = PipelineStage::findOrFail($data['pipeline_stage_id']);
$deal = Deal::create([
    'pipeline_id'       => $stage->pipeline_id,
    'pipeline_stage_id' => $data['pipeline_stage_id'],
    // ...
]);
```

---

**Bug 3 : Click bubbling vers le backdrop Alpine.js dans la modal deals**

La modal "Nouveau deal" a un backdrop `<div @click="open = false" class="absolute inset-0">`. Les clics sur le bouton "Créer le deal" bubblaient jusqu'au backdrop, fermant la modal et annulant la navigation du formulaire.

**Fix :** `@click.stop` sur le div contenant le formulaire :
```blade
<div @click.stop class="relative card shadow-pop z-50" style="width: 580px; ...">
```

---

## État des tests à la fin de la session

```
PHPUnit  : 84 tests, 227 assertions, 0 échecs
Playwright: 9 tests (chromium), 0 échecs
Total    : 93 tests, 0 échecs
```

**Commandes :**
```bash
# PHPUnit
docker exec crm_ultimate-app-1 php artisan test

# E2E (instance Docker déjà lancée)
APP_URL=http://localhost:8080 npx playwright test --reporter=list

# E2E avec UI interactif
APP_URL=http://localhost:8080 npx playwright test --ui
```

---

## Gotchas et règles apprises

### Routing
- `route('deals.index')` → `/api/v1/deals` (shadowing API > Web car API chargée en dernier)
- **Règle :** Dans les contrôleurs Web ET les vues, toujours hardcoder : `redirect('/deals')`, `'/contacts/' . $id`

### Alpine.js modals
- Le backdrop `@click="open = false"` intercepte les événements bubblés depuis l'intérieur de la modal
- **Fix :** `@click.stop` sur le conteneur du contenu de la modal

### Playwright — sélecteurs spécifiques à ce projet
| Élément | Sélecteur correct |
|---------|-------------------|
| Input nom segment | `input[x-model="name"]` (pas `input[name="name"]` — c'est un hidden) |
| Bouton submit segment | `button:has-text("Créer le segment")` (utilise `@click="submitForm()"`, pas `type="submit"`) |
| Ligne table deal | `table.t tbody tr` → click sur `td.nth(1)` OU via `onclick` attr → URL directe |
| Login redirect | Redirige vers `/` (dashboard), pas `/dashboard` ou `/contacts` |
| Credentials démo | `admin@demo.com / password` (DemoSeeder) — PAS `admin@example.com` |

### `waitForURL` dans Playwright
```typescript
// ✅ Correct — reçoit un objet URL
await page.waitForURL(url => !url.pathname.includes('/login'));

// ❌ Incorrect — url est un objet URL, pas une string
await page.waitForURL(url => !url.includes('/login'));
```

### DemoSeeder
```bash
docker exec crm_ultimate-app-1 php artisan db:seed --class=DemoSeeder
# Crée : admin@demo.com + user@demo.com, 20 companies, 50 contacts, 30 deals, 60 activities, 3 segments
```

---

## Backlog — prochaines priorités

| Priorité | Feature | Notes |
|----------|---------|-------|
| 🔴 Haute | Recherche globale améliorée | Fuzzy search, résultats groupés par entité, raccourci ⌘K |
| 🟠 Moyenne | Automation lifecycle | Règles auto-trigger : ex. "si deal > 10k€ → passer à Qualifié" |
| 🟠 Moyenne | Page détail contacts/companies | Drawer style HubSpot avec activités, deals liés, associations |
| 🟡 Basse | Notifications in-app | Centre de notifications, badge non-lu |
| 🟡 Basse | Vues sauvegardées (saved_views) | Filtres persistants par utilisateur |

---

## Arborescence des fichiers modifiés / créés

```
.github/
  workflows/
    ci.yml                              ← NOUVEAU

app/Http/Controllers/Web/
  DealController.php                    ← fix redirect() + pipeline_id
  SegmentController.php                 ← flash_toast store/update
  Settings/
    StageController.php                 ← méthode reorder()

app/Services/
  SegmentQueryEngine.php               ← fix OR group semantics

playwright.config.ts                   ← NOUVEAU

public/build/
  manifest.json                        ← rebuild CSS

resources/views/
  components/
    avatar.blade.php                    ← NOUVEAU
    drawer.blade.php                    ← closeUrl + $body slot
    pagination.blade.php                ← NOUVEAU
  layouts/
    app.blade.php                       ← flash_toast detection
  pages/
    deals/
      index.blade.php                   ← @click.stop + action="/deals"
      show.blade.php                    ← x-drawer refactoring
    imports/
      create.blade.php                  ← toasts + fix dark mode
    segments/
      show.blade.php                    ← x-pagination
    settings/
      stages.blade.php                  ← SortableJS + drag handles
    activities/index.blade.php          ← x-pagination
    companies/index.blade.php           ← x-pagination
    contacts/index.blade.php            ← x-pagination

routes/web.php                         ← POST /settings/stages/reorder

tests/
  e2e/
    helpers.ts                          ← NOUVEAU
    auth.spec.ts                        ← NOUVEAU (3 tests)
    deal.spec.ts                        ← NOUVEAU (4 tests)
    segment.spec.ts                     ← NOUVEAU (2 tests)
  Feature/
    SegmentQueryEngineTest.php          ← test OR reécrit
```
