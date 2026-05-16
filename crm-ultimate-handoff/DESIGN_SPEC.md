# CRM Ultimate — Design Spec

Tous les tokens visuels du design. **Ne pas inventer de couleurs/sizes en dehors de cette liste.**

---

## 1. Couleurs

### Light mode

| Token | Hex | Usage |
|---|---|---|
| `bg`         | `#fafaf7` | Fond principal de l'app (warm white) |
| `surface`    | `#ffffff` | Cards, tables, drawers, modals |
| `surface-2`  | `#f5f4f0` | Hover de row, fond du rail, panneau de propriétés du drawer |
| `border`     | `#e7e5df` | Toutes les bordures par défaut (1px) |
| `border-strong` | `#d4d1c8` | Bordures d'éléments interactifs (checkbox idle), hover de border |
| `text`       | `#1a1a17` | Texte principal |
| `text-2`     | `#5e5c55` | Texte secondaire (labels, body atténué) |
| `text-3`     | `#92908a` | Texte tertiaire (méta, placeholders, mono-labels) |

### Dark mode (toggle `class="dark"` sur `<html>`)

| Token | Hex | Usage |
|---|---|---|
| `bg`         | `#0e0e0c` | Fond principal (warm near-black) |
| `surface`    | `#181815` | Cards, tables |
| `surface-2`  | `#232220` | Hover, panneaux secondaires |
| `border`     | `#2e2c28` | Bordures |
| `border-strong` | `#3d3a35` | Bordures fortes |
| `text`       | `#f4f1ea` | Texte principal |
| `text-2`     | `#a8a59b` | Secondaire |
| `text-3`     | `#6e6a60` | Tertiaire |

### Accents (constants light + dark, sauf `*-soft`)

| Token | Light | Dark | Usage |
|---|---|---|---|
| `accent`       | `#ef6a2a` | `#ef6a2a` | CTA, KPI hero, hot deals, état actif |
| `accent-hover` | `#d85816` | `#ff7c3d` | Hover de CTA |
| `accent-soft`  | `#fff3eb` | `rgba(239,106,42,0.14)` | Backgrounds de chip orange, focus rings |
| `ok`           | `#2f8a5f` | `#2f8a5f` | Deals gagnés, statuts positifs |
| `ok-soft`      | `#e1f2e9` | `rgba(47,138,95,0.18)` | Background chip ok |
| `warn`         | `#d4a017` | `#f3c948` | Avertissements |
| `warn-soft`    | `#fbf3dc` | `rgba(212,160,23,0.18)` | Background chip warn |
| `err`          | `#c63d2f` | `#c63d2f` | Erreurs, deals perdus, overdue |
| `err-soft`     | `#fce8e5` | `rgba(198,61,47,0.18)` | Background chip err |
| `info`         | `#2a5fb4` | `#2a5fb4` | Stages "Qualified", emails dans timeline |
| `info-soft`    | `#e1eaf7` | `rgba(42,95,180,0.20)` | Background chip info |

### Couleurs d'avatar (5 palettes, déterministes par hash du nom)

| Class | BG light | FG light | BG dark | FG dark |
|---|---|---|---|---|
| `c1` orange | `#ffe7d8` | `#c44e10` | `#4a2410` | `#ffb084` |
| `c2` green  | `#d6efe0` | `#1d6b46` | `#1a3a28` | `#8de0b3` |
| `c3` blue   | `#dde8fa` | `#1f4b94` | `#1a2a4a` | `#9ab8e8` |
| `c4` purple | `#f4e0f7` | `#7d2a93` | `#3a1a44` | `#d99eea` |
| `c5` yellow | `#fbf3dc` | `#8a6700` | `#3a2e10` | `#f3c948` |

> En Blade : helper `App\Helpers\Avatar::color($name)` qui hash le nom → renvoie `c1`..`c5`.

### Couleurs d'étape de pipeline (configurables par utilisateur)

Défaut suggéré :
- Prospecting → `text-3` (`#92908a`)
- Qualified → `info` (`#2a5fb4`)
- Proposal → `accent` (`#ef6a2a`)
- Negotiation → `#7d2a93` (purple)
- Won → `ok` (`#2f8a5f`)
- Lost → `err` (`#c63d2f`)

---

## 2. Typographie

### Familles

```css
--font-sans:    'Patrick Hand', system-ui, sans-serif;   /* body, UI */
--font-display: 'Caveat', cursive;                        /* titres h1/h2/h3, labels visibles */
--font-num:     'Kalam', cursive;                         /* grands chiffres (KPI, montants en évidence) */
--font-mono:    'JetBrains Mono', monospace;              /* data dense, mono-labels, IDs, dates compactes */
```

Charger via `<link>` Google Fonts (déjà fait dans `hifi-reference.html`).

```html
<link href="https://fonts.googleapis.com/css2?family=Patrick+Hand&family=Caveat:wght@500;600;700&family=Kalam:wght@400;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
```

> **Note d'intention** : on prend la même stack typographique que les wireframes (Patrick Hand / Caveat / Kalam / JetBrains Mono). Le résultat est **chaleureux, personnel, à l'opposé des CRM corporate**. C'est volontaire. Si un titre rend mal en Caveat (trop fin/condensé), tester un poids supérieur (`font-weight: 700`) avant d'envisager une autre solution.

### Échelle

Patrick Hand a un x-height plus petit que Inter — on monte d'un cran toutes les tailles pour conserver la lisibilité.

| Class Tailwind | px | line-height | Usage |
|---|---|---|---|
| `text-[11px]` | 11 | 1.4 | mono-label small, méta dense |
| `text-[12px]` | 12 | 1.4 | méta dans tables (mono) |
| `text-[13px]` | 13 | 1.5 | méta secondaire, chips |
| `text-[14px]` | 14 | 1.5 | body small, table content (mono uniquement) |
| `text-[16px]` | 16 | 1.45 | **body par défaut** (Patrick Hand) |
| `text-[18px]` | 18 | 1.4 | row titles, label de button |
| `text-[22px]` | 22 | 1.15 | h3 (Caveat) — title de card-h |
| `text-[28px]` | 28 | 1.1 | h2 (Caveat) — sous-titres |
| `text-[36px]` | 36 | 1.05 | h1 (Caveat) — titre de page |
| `text-[34px]` | 34 | 1.0 | KPI standard (Kalam) |
| `text-[44px]` | 44 | 0.95 | KPI hero (Kalam) |

### Règles de typo

- **Body** : Patrick Hand 16px par défaut. C'est manuscrit, ça doit respirer — interligne 1.45 mini.
- **Titres h1/h2/h3** : Caveat 700. Pas de letter-spacing (Caveat est déjà calligraphique).
- **Grands chiffres** (KPI hero, montants en évidence, big numbers) : Kalam 700 avec `font-variant-numeric: tabular-nums`. Classe `.num`.
- **Data dense** (tables, métas, dates `DD/MM/YYYY`, scores 0–100, IDs `DEAL-2026-0142`, heures `14:30`) : JetBrains Mono. Classe `.num-mono` ou `.mono-label` selon contexte.
- **Mono labels** : JetBrains Mono uppercase, letter-spacing `0.06em`, taille `11px`, couleur `text-3`. Classe `.mono-label`. Utilisé pour les étiquettes de section, headers de table, légendes.
- **Pas de mix Caveat + Patrick Hand dans la même phrase**. Caveat pour les titres entiers, Patrick Hand pour le body entier.
- **JetBrains Mono est l'ancre froide** : il équilibre les polices manuscrites en injectant de la rigueur sur les données. Ne pas le supprimer "pour cohérence" — c'est le contraste qui rend l'aesthétique lisible.

---

## 3. Espacement

Échelle Tailwind standard (4px base) :

```
0.5 = 2px   1 = 4px   1.5 = 6px   2 = 8px   2.5 = 10px
3 = 12px   3.5 = 14px   4 = 16px   5 = 20px   6 = 24px
7 = 28px   8 = 32px   10 = 40px   12 = 48px
```

Padding standard :
- **Card padding** : `p-4` (16px) à `p-5` (20px)
- **Card header padding** : `px-4 py-2.5` (16/10) ou `px-3.5 py-3` (14/12)
- **Page padding horizontal** : `px-7` (28px)
- **Page padding top** : `pt-6` (24px)
- **Row padding** : `px-4 py-3` (16/12) pour table, `px-4 py-3` pour task list
- **Form field gap** : `gap-1.5` (6px) entre label et input

Gap entre éléments :
- **Cards adjacentes** : `gap-3` (12px)
- **Section verticale** : `gap-4` à `gap-5`
- **Icônes inline avec texte** : `gap-1.5` ou `gap-2`
- **Boutons d'action group** : `gap-2`

---

## 4. Radius

| Token | px | Usage |
|---|---|---|
| `rounded` | 4 | Checkbox, kbd, small chips |
| `rounded-md` | 6 | Inputs, buttons, segmented controls |
| `rounded-lg` | 8 | Boutons, KPI band cells, k-cards |
| `rounded-xl` | 12 | KPI hero, big cards |
| `rounded-2xl` | 16 | Modals (optionnel — on reste sur 12 pour cohérence) |
| `rounded-full` | 999 | Avatars, chips, dots |

**Règle** : ne JAMAIS mélanger plus de 2 valeurs de radius dans une même vue.

---

## 5. Bordures

- **Width** : 1px partout. Pas de 2px sauf checkbox idle (1.5px).
- **Style** : `solid`. Pas de `dashed` en hi-fi (réservé aux wireframes).
- **Couleur** : `border` par défaut, `border-strong` au hover ou pour éléments interactifs.

**Pattern card** :
```html
<div class="border border-default rounded-lg bg-surface">
```

**Pas d'ombre par défaut**. La séparation se fait par la bordure 1px.

---

## 6. Ombres

À utiliser avec parcimonie :

```css
--shadow-card: 0 1px 2px rgba(20,20,15,0.04), 0 0 0 1px var(--border);
--shadow-pop:  0 12px 32px -8px rgba(20,20,15,0.18), 0 0 0 1px var(--border);
```

| Token | Usage |
|---|---|
| `shadow-card` | Optionnel sur cards qui flottent (drawer, modal interne). Rare. |
| `shadow-pop`  | Modals, drawers, popovers, command palette, dropdowns. **Toujours.** |

En dark mode : ombres plus prononcées (rgba 0.6 au lieu de 0.18).

---

## 7. Z-index

| z | Usage |
|---|---|
| 0 | Default |
| 10 | Sticky cell headers |
| 20 | Header sticky |
| 30 | Tooltips, popovers |
| 40 | Drawer, modal backdrop |
| 50 | Modal, drawer content |
| 60 | Command palette (⌘K) |
| 100 | Toasts |

---

## 8. Breakpoints

L'app est **desktop-first** (1280px+). Mobile est optionnel pour la v1.

| Token | min-width | Notes |
|---|---|---|
| `sm` | 640 | Mobile (rail devient bottom nav) |
| `md` | 768 | Tablette (KPIs sur 2 colonnes) |
| `lg` | 1024 | Tablette landscape (rail apparaît) |
| `xl` | 1280 | Desktop standard |
| `2xl` | 1536 | Large desktop |

Layout reference : `1440 × 900` (taille du frame dans `hifi-reference.html`).

---

## 9. Animation

Garder léger. Pas de bounce, pas de spring.

```css
transition: all 120ms ease-out;
```

| État | Durée |
|---|---|
| Hover (bg, border, color) | 120ms |
| Drawer slide-in | 220ms cubic-bezier(0.32, 0.72, 0, 1) |
| Modal fade | 160ms |
| Skeleton pulse | 1.4s |

Pas d'animation sur les changements de stage (kanban drag) au-delà du déplacement natif du curseur.

---

## 10. Iconographie

**Lucide** (`https://lucide.dev`) — set d'icônes minimaliste, stroke 1.5–1.6, taille **16px** par défaut.

Pour Blade : utiliser le package `mallardduck/blade-lucide-icons` ou `blade-ui-kit/blade-icons` :
```php
@svg('lucide-search', 'w-4 h-4 stroke-current')
```

Tailles utilisées :
- 16px → icônes dans rows, boutons standard
- 20px → icônes dans le rail (vraie taille du clickable: 36px)
- 14px → icônes inline avec texte de chip

**Pas d'emoji** sauf dans la timeline d'activités où ils servent de pictogrammes typés (📧 email, 📞 appel, 📝 note, ✓ tâche, 🔄 changement d'étape, ➕ création, 📅 réunion). C'est volontaire — ça rend les rows lisibles en un coup d'œil sans avoir à styliser chaque type.

---

## 11. Données & formats

- **Montants** : `1 234 567 €` (espace insécable comme séparateur de milliers, symbole après, signe `€`). PHP : `number_format($n, 0, ',', "\xc2\xa0")`.
- **Pourcentages** : `50%` (pas d'espace).
- **Dates** : `DD/MM/YYYY` (court) ou `vendredi 16 mai` (long). PHP : Carbon `->isoFormat('DD/MM/YYYY')` ou `->isoFormat('dddd D MMMM')`.
- **Relatives** : `il y a 2h`, `hier`, `3 jours`. Carbon `->diffForHumans()`.
- **Téléphones** : format E.164 affiché avec espaces nationaux.

---

## 12. États

Pour chaque composant interactif :

- **idle** : couleur par défaut
- **hover** : `surface-2` en background, `text` en texte (vs `text-2`), `border-strong` sur bordure
- **active/pressed** : background `text` + foreground `bg` (le bouton "Table" actif dans le toggle Deals)
- **focus** : `outline: 2px solid accent-soft`, `border: accent`. **Toujours visible au clavier.**
- **disabled** : `opacity: 0.5`, `cursor: not-allowed`, pas de hover
- **loading** : skeleton shimmer ou spinner Lucide `lucide-loader-2` avec `animate-spin`

---

## 13. Anti-patterns (à éviter)

- ❌ Gradients (sauf KPI hero orange → orange foncé, et c'est tout)
- ❌ Drop shadows multiples ou colorées
- ❌ Bordures double-trait
- ❌ Border-radius asymétrique
- ❌ Couleurs hors palette (incluant les nouveaux greys "subtils")
- ❌ Fonts différentes (pas de Roboto, Helvetica, etc.)
- ❌ Emoji dans titres ou KPIs
- ❌ Animations de plus de 250ms
- ❌ Plus de 1 couleur d'accent par écran (l'orange domine, le reste est sémantique)
