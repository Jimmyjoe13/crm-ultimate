# CRM Ultimate — Components

Liste des composants Blade à créer. Chaque composant vit dans `resources/views/components/` et s'utilise avec `<x-...>`.

> **Note** : tous les exemples supposent Tailwind configuré (voir `tailwind.config.js` à la racine du handoff) + Alpine.js pour l'interactivité.

---

## 1. Layout

### `<x-app-shell>`

Shell global : icon rail à gauche + header sticky + slot principal.

```blade
<x-app-shell :active="'dashboard'">
  <x-slot:title>Dashboard</x-slot:title>
  <x-slot:crumb>Workspace / Dashboard</x-slot:crumb>

  {{-- contenu de la page --}}
</x-app-shell>
```

**Structure** :
- `<aside>` 56px de large, dark `#181815`, items `<x-rail-icon>` empilés verticalement.
- `<main>` flex-1, scroll vertical, contient :
  - `<header>` sticky avec crumbs + search + actions globales (Tâches, Nouveau deal).
  - Slot par défaut (le contenu).

### `<x-rail-icon route="dashboard" :active="$active === 'dashboard'" tooltip="Dashboard">`

Icône cliquable du rail. 36×36, rounded 9px. Tooltip à droite au hover.

- État `idle` : `text-2` sur fond transparent
- État `hover` : background `surface-2`, color `text`
- État `active` : background `accent`, color white

---

## 2. Buttons

### `<x-button variant="default|primary|ghost" size="sm|md" icon="lucide-plus" >`

Tailles :
- `sm` : `px-2 py-1 text-xs`
- `md` (défaut) : `px-3 py-1.5 text-sm`

Variants :
- `default` : `bg-surface border-default text-text` (hover : `bg-surface-2`)
- `primary` : `bg-accent text-white border-accent` (hover : `bg-accent-hover`)
- `ghost` : `bg-transparent border-transparent text-text-2` (hover : `bg-surface-2 text-text`)

Toujours rounded `lg` (8px). Icône + label avec `gap-1.5`.

### `<x-icon-button icon="lucide-more-vertical" tooltip="Options">`

Carré ~28×28, padding `p-1.5`, sinon comme `<x-button variant="ghost">`.

---

## 3. Chip / Badge

### `<x-chip variant="default|accent|ok|err|warn|info|solid" :dot="true">`

Pillule compacte. Hauteur ~20px.

- `default` : `bg-surface-2 text-text-2 border-default`
- `accent` : `bg-accent-soft text-accent`, dot accent
- `ok` : `bg-ok-soft text-ok`, dot ok
- `err` : `bg-err-soft text-err`, dot err
- `warn` : `bg-warn-soft`, text `#8a6700` (light) / `#f3c948` (dark)
- `info` : `bg-info-soft text-info`
- `solid` : `bg-text text-bg` (utilisé pour le filtre actif "All · 24")

Le dot (•) est optionnel, taille 6px, même couleur que le texte par défaut, accent du chip s'il y a une variant.

Numéraire toujours en `font-mono`.

---

## 4. Avatar

### `<x-avatar :name="$contact->name" size="sm|md|lg" :rounded="false">`

- Récupère les initiales (`AB` pour "Alex Bernard", `JD` pour "Julien Dupont").
- Helper `App\Helpers\Avatar::color($name)` hash le nom → renvoie `c1..c5`.
- Tailles : `sm` 20px, `md` 24px (défaut), `lg` 32px.
- `:rounded="true"` (défaut) = cercle. `:rounded="false"` = `rounded-md` (utilisé pour les logos d'entreprise).

---

## 5. Card

### `<x-card>`

```blade
<x-card>
  <x-slot:header>
    <x-slot:title>Prochaines actions</x-slot:title>
    <x-slot:meta>5 dues · 1 retard</x-slot:meta>
  </x-slot:header>

  {{-- contenu --}}
</x-card>
```

- `border border-default rounded-lg bg-surface`
- Header optionnel : `flex justify-between items-center px-4 py-2.5 border-b border-default`
  - Title : `text-[13px] font-semibold text-text`
  - Meta : `text-[11.5px] text-text-3 font-mono`
- Body : pas de padding par défaut (le composant qui consomme gère son padding).

### `<x-card.kpi label="Pipeline total" :hero="false">`

Sous-composant pour les cards de KPI.

Structure :
```
mono-label (10.5px uppercase, text-3)
big number (text-3xl font-semibold, num font-mono)
meta line (text-[12px], delta + comparaison)
[optional pbar or sparkline]
```

Si `:hero="true"` :
- Background `linear-gradient(135deg, #ef6a2a, #d85816)`, texte blanc.
- Number 36px (text-4xl).
- Petit sparkline SVG en bas à droite.

---

## 6. Table

### `<x-table>`

```blade
<x-table>
  <x-slot:head>
    <th class="w-8"><x-checkbox /></th>
    <th>Deal</th>
    <th>Company</th>
    <th>Amount</th>
    {{-- ... --}}
  </x-slot:head>

  @foreach($deals as $deal)
    <x-table.row :record="$deal" />
  @endforeach
</x-table>
```

**Style** :
- Wrapped dans `<x-card>` sans padding.
- `<thead>` : `bg-surface-2`, `<th>` en `mono-label`, `text-left`, `px-4 py-2.5`, `border-b border-default`.
- `<tbody> <tr>` : `border-b border-default`, hover `bg-surface-2`, dernier row sans border.
- `<td>` : `px-4 py-3 text-[13px] align-middle`.

### `<x-table.empty :columns="9">`

Empty state (illustration discrète + texte).

---

## 7. Kanban

### `<x-kanban :stages="$stages">`

Container horizontal avec scroll-x.

### `<x-kanban.column :stage="$stage" :deals="$deals">`

- Width fixe `280px`, flex-shrink-0.
- Header : dot couleur étape + nom + count chip + total montant à droite.
- Liste de `<x-kanban.card>`.
- Footer : bouton ghost `+ Ajouter deal` full-width.

### `<x-kanban.card :deal="$deal" :hot="false">`

- `bg-surface border-default rounded-lg p-3`
- Si `:hot="true"` : `border-l-3 border-accent pl-[9px]` (3px de bordure orange à gauche, padding compensé).
- Contenu :
  ```
  [title]                  [star/score chip]
  [montant]                              ← num text-[12px] text-secondary
  ─────────────────────────────────
  [av] [tag mono]                   [j ago]
  ```

Drag-and-drop : utiliser `SortableJS` (CDN) en mode Alpine. POST à `/api/deals/{id}/stage` au drop.

---

## 8. Forms

### `<x-form.field name="amount" label="Montant" type="text">`

Wrapper `flex flex-col gap-1.5` avec label + input.

- Label : `text-[12px] text-text-2 font-medium`
- Input : `px-2.5 py-1.5 border-default rounded-md bg-surface text-[13px]`
- Focus : `border-accent` + `box-shadow: 0 0 0 3px var(--accent-soft)`

### `<x-form.select :options="..." :selected="...">`

`appearance: none` + flèche SVG en background-image (voir CSS `.select-arrow` dans la hifi).

### `<x-form.checkbox :checked="false">`

- 14×14, `border-1.5 border-strong rounded`, bg `surface`.
- Checked : `bg-text border-text`, check Lucide blanc 9×9.

### `<x-form.chip-group name="lead_source" :options="..." :selected="...">`

Une rangée de chips cliquables. Le chip sélectionné devient `solid`. C'est un picker pour des champs select avec ≤6 options.

---

## 9. Search & Command

### `<x-global-search>`

Le faux input dans le header. Au clic ou `⌘K`, ouvre la command palette.

```html
<button class="flex items-center gap-2 px-3 py-1.5 border border-default rounded-lg bg-surface w-80">
  <lucide-search />
  <span class="flex-1 text-left text-sm text-text-3">Rechercher…</span>
  <span class="kbd">⌘ K</span>
</button>
```

### `<x-command-palette>` (modale)

Modal centré, 600×auto. Liste de résultats groupés (Deals, Contacts, Entreprises, Actions). Backend : route GET `/api/search?q=...` (Laravel Scout ou ILIKE).

Pas critique pour la v1 — peut être un faux pop-up qui dit "bientôt".

---

## 10. Drawer

### `<x-drawer name="deal-detail" width="720px">`

- Slide-in depuis la droite.
- Backdrop : `bg-[rgba(20,20,15,0.45)]` (light) / `bg-[rgba(0,0,0,0.65)]` (dark).
- Drawer : `bg-surface border-l border-default` avec `shadow-pop`.
- Alpine state : `x-data="{ open: false }"` + `x-show="open"` + transition 220ms.
- Ouverture par `<x-drawer.trigger target="deal-detail" :data="['id' => $deal->id]">`.
- Fermeture : croix, clic backdrop, Escape.

Structure du drawer "deal detail" : header (titre + actions) + stage progress (5 segments) + body 2 colonnes (activity tabs + properties sidebar).

### `<x-drawer.stage-progress :stages="..." :current="...">`

Barre segmentée en 5 (ou N) parties.
- Étapes passées : `bg-text` full
- Étape actuelle : `bg-accent` + outline `outline-2 outline-accent-soft`
- Étapes futures : `bg-surface-2`
- Sous chaque segment : `mono-label` avec nom de l'étape.

---

## 11. Modal

### `<x-modal name="new-deal" width="580px">`

Modal centré classique. Même backdrop que le drawer. Sticky footer avec boutons Annuler / Créer.

Header : titre `text-lg font-semibold` + sous-titre `text-[12px] font-mono text-text-3`.

Body : `px-6 py-5`, grid `grid-cols-2 gap-4` pour les champs.

Footer : `px-6 py-4 border-t bg-surface-2 flex justify-between`.

---

## 12. Timeline

### `<x-timeline>` & `<x-timeline.item :time="..." :icon="..." :variant="default|accent|ok|info">`

```blade
<x-timeline>
  <x-timeline.item time="11:24" variant="info">
    📧 Email envoyé à <b>Lumio Studio</b>
    <x-slot:sub>Maya Rousseau · proposal v2</x-slot:sub>
  </x-timeline.item>
</x-timeline>
```

- Grid `80px 24px 1fr` par item, gap 0.
- Axe : ligne verticale 1px `bg-border` qui relie les dots.
- Dot : 10×10 cercle coloré selon `variant`, border 2px `bg`, halo 1px `border`.
- Time : `font-mono text-[11px] text-text-3 pt-0.5`.
- Group headers (dates) : `mono-label pt-4 pb-1`, "AUJOURD'HUI · VENDREDI 16 MAI".

---

## 13. Progress bar

### `<x-pbar :value="62" variant="default|accent|ok">`

```html
<div class="h-1.5 bg-surface-2 rounded-full overflow-hidden">
  <div class="h-full bg-text rounded-full" style="width: 62%;"></div>
</div>
```

Variants : `default` (bar = `text`), `accent` (bar = `accent`), `ok` (bar = `ok`).

---

## 14. KBD

### `<x-kbd>⌘ K</x-kbd>`

```html
<span class="font-mono text-[10.5px] px-1.5 py-px border border-default border-b-[1.5px] rounded bg-surface-2 text-text-2">⌘ K</span>
```

---

## 15. Toast (notification système)

### `<x-toast>`

Stack en bas-droite, z 100. Slide-in 220ms. Auto-dismiss 4s.

Variants :
- `success` : icône check ok, accent ok
- `error` : icône alert err
- `info` : icône info

Géré par Alpine + un store global `Alpine.store('toasts', { items: [], push(t) {...} })`.

---

## 16. Theme toggle

### `<x-theme-toggle>`

Bouton dans le header (ou dans un menu user). Au clic :
```js
document.documentElement.classList.toggle('dark');
localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
```

Au boot (dans le `<head>` pour éviter le flash) :
```html
<script>
  const t = localStorage.getItem('theme');
  if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
  }
</script>
```

---

## 17. Composants à NE PAS créer (overkill v1)

- ❌ DataTable virtualisé (Inertia + TanStack) → on reste en server-rendered avec pagination.
- ❌ Charts avancés (Chart.js, ApexCharts) → la pbar et le funnel suffisent.
- ❌ Date picker custom → utiliser `<input type="date">` natif stylé.
- ❌ Multi-select avec autocomplete avancé → Choices.js si vraiment besoin, sinon `<select multiple>`.

---

## Convention de nommage

- Composants Blade : kebab-case dans le fichier (`kanban-card.blade.php`), `<x-kanban.card>` à l'usage.
- Slots nommés : `<x-slot:name>` (Blade 8+).
- Props : `camelCase` côté PHP, `kebab-case` côté HTML attribute.
- Classes Tailwind : pas d'`@apply` sauf pour les patterns ultra-répétés (chip, btn, av). Garder le HTML lisible.
