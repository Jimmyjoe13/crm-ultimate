# CRM Ultimate — Instructions Claude Code

> **Ce fichier est ta source de vérité.** Lis-le en entier avant de toucher au code.

Tu travailles sur le frontend de **CRM Ultimate**, un CRM personnel/freelance. Le backend Laravel existe déjà — tu construis l'UI par-dessus selon le design fourni dans ce dossier.

## Ton mandat

Implémenter fidèlement la maquette **`hifi-reference.html`** dans le projet Laravel existant, en respectant **scrupuleusement** les tokens et conventions documentés ici.

Pas de "j'ai trouvé mieux". Pas de "j'ai ajouté un peu de fun". Reproduction fidèle, dans l'ordre, avec validation visuelle à chaque étape.

---

## Stack technique (à utiliser)

| Côté | Choix | Notes |
|---|---|---|
| Templates | **Blade** (Laravel 13) | Server-rendered, pas d'Inertia/SPA. |
| CSS | **Tailwind CSS 3.x** | Config fournie dans `tailwind.config.js`. |
| JS | **Alpine.js 3.x** | Pour l'interactivité (drawers, modals, toggles). |
| Build | **Vite** | Déjà configuré normalement dans Laravel 13. |
| Icônes | **Lucide** via `mallardduck/blade-lucide-icons` | `composer require mallardduck/blade-lucide-icons` |
| Drag-drop | **SortableJS** via CDN | Pour le Kanban. |
| Dates | **Carbon** (déjà inclus dans Laravel) | Locale `fr`. |

**Ne pas introduire** : React, Vue, Inertia, Livewire (sauf si déjà présent — vérifier `composer.json`), Bootstrap, jQuery, autre framework CSS.

---

## Fichiers à lire avant de coder

Dans l'ordre :

1. **`hifi-reference.html`** — Ouvre-le dans un navigateur. Navigue tous les écrans avec le panneau flottant en bas-droite. Toggle light/dark. C'est la **référence visuelle non négociable**.
2. **`DESIGN_SPEC.md`** — Tous les tokens (couleurs, typo, spacing, radius). Copie ces valeurs telles quelles dans `tailwind.config.js` et `app.css`.
3. **`COMPONENTS.md`** — La liste des composants Blade à créer, avec leur API et leur style.
4. **`SCREENS.md`** — Le détail de chacun des 10 écrans + les routes Laravel à brancher.
5. **`tailwind.config.js`** — Config prête à copier dans la racine du projet.

---

## Structure cible

```
app/
  Helpers/
    Avatar.php             # initials() + color() (hash → c1..c5)
    Money.php              # format(int $cents, string $currency)
  Http/
    Controllers/
      DashboardController.php
      DealController.php
      PipelineController.php
      ContactController.php
      CompanyController.php
      ActivityController.php
      Settings/
        StageController.php
        CustomFieldController.php
      SearchController.php
    Middleware/
      InjectToasts.php     # flash → window.__toasts
resources/
  css/
    app.css                # tokens CSS + base styles + utilities custom
  js/
    app.js                 # Alpine init + stores + raccourcis clavier
    kanban.js              # SortableJS wiring
  views/
    layouts/
      app.blade.php        # html shell + head + theme boot script
    components/
      app-shell.blade.php
      rail-icon.blade.php
      button.blade.php
      icon-button.blade.php
      chip.blade.php
      avatar.blade.php
      card.blade.php
      card/
        kpi.blade.php
      table.blade.php
      table/
        row.blade.php
        empty.blade.php
      kanban.blade.php
      kanban/
        column.blade.php
        card.blade.php
      form/
        field.blade.php
        select.blade.php
        checkbox.blade.php
        chip-group.blade.php
      drawer.blade.php
      drawer/
        stage-progress.blade.php
      modal.blade.php
      timeline.blade.php
      timeline/
        item.blade.php
      pbar.blade.php
      kbd.blade.php
      toast.blade.php
      theme-toggle.blade.php
      global-search.blade.php
    pages/
      dashboard.blade.php
      deals/
        index.blade.php
        _drawer.blade.php   # partial pour fetch ajax du drawer deal
      pipeline/
        index.blade.php
      contacts/
        index.blade.php
      companies/
        index.blade.php
      activities/
        index.blade.php
      settings/
        stages.blade.php
        fields.blade.php
      partials/
        new-deal-modal.blade.php
routes/
  web.php                  # voir SCREENS.md pour la liste
```

---

## Étapes d'implémentation (à suivre dans l'ordre)

### Étape 0 — Audit
- [ ] Lire `composer.json` et `package.json` pour comprendre l'existant.
- [ ] Vérifier que Tailwind / Alpine / Vite sont déjà installés. Sinon : `npm i -D tailwindcss@3 postcss autoprefixer @tailwindcss/forms` et `composer require mallardduck/blade-lucide-icons`.
- [ ] Lire les migrations existantes (`database/migrations/`) pour comprendre les modèles. Lister ce que tu trouves dans un commentaire au début du PR.
- [ ] Identifier le state actuel du frontend (templates Blade existants ?). Lister ce qui sera supprimé/remplacé.

### Étape 1 — Tokens & shell
- [ ] Copier `tailwind.config.js` à la racine. Adapter le `content` si nécessaire.
- [ ] Créer `resources/css/app.css` avec :
  - `@tailwind base; @tailwind components; @tailwind utilities;`
  - Le block `:root { --bg: ...; ... }` light + `html.dark { ... }` dark (copier depuis `hifi-reference.html`).
  - Les `@layer components` pour `.chip`, `.btn`, `.kbd`, `.av`, `.mono-label`, `.num`, `.pbar`, `.tl-item`, `.k-col`, `.k-card`, etc. (copier les styles depuis `hifi-reference.html`).
- [ ] Ajouter Google Fonts dans `resources/views/layouts/app.blade.php` :
  ```html
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Patrick+Hand&family=Caveat:wght@500;600;700&family=Kalam:wght@400;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  ```
- [ ] Theme boot script dans `<head>` (avant le body, pour éviter le flash) :
  ```html
  <script>
    const t = localStorage.getItem('theme');
    if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    }
  </script>
  ```
- [ ] Créer `<x-app-shell>` avec rail + header. Vérifier dans le navigateur : ouvrir une page vide rendue via le layout doit donner le même rail + header que `hifi-reference.html`.

### Étape 2 — Composants atomiques
Créer dans cet ordre, en testant chaque composant dans une route `/dev/components` (qu'on supprimera ensuite) :
- [ ] `<x-button>` (3 variants, 2 tailles) + `<x-icon-button>`
- [ ] `<x-chip>` (7 variants, dot optionnel)
- [ ] `<x-avatar>` (3 tailles, rounded/square, helper Avatar::color)
- [ ] `<x-kbd>`
- [ ] `<x-pbar>` (3 variants)
- [ ] `<x-form.field>`, `<x-form.select>`, `<x-form.checkbox>`, `<x-form.chip-group>`

Chaque composant doit matcher pixel-près celui de `hifi-reference.html`. **Screenshot comparatif obligatoire** avant de passer au suivant.

### Étape 3 — Card & Table
- [ ] `<x-card>` + `<x-card.kpi>` (avec variant `:hero`)
- [ ] `<x-table>` + `<x-table.row>` (slot avec données dynamiques)

### Étape 4 — Dashboard
- [ ] Brancher `DashboardController@index` avec les vraies données du DB (voir `SCREENS.md` section 1).
- [ ] Vérifier visuellement contre l'écran 01 de `hifi-reference.html`.

### Étape 5 — Deals
- [ ] `DealController@index` avec filtres, sort, pagination.
- [ ] Vérifier contre l'écran 02.

### Étape 6 — Drawer & Modal
- [ ] `<x-drawer>` avec Alpine state + slide-in animation 220ms.
- [ ] `<x-modal>` centré.
- [ ] `<x-drawer.stage-progress>`.
- [ ] Brancher la modale "Nouveau deal" sur le bouton du header.
- [ ] Brancher l'ouverture du drawer "Deal detail" sur le clic d'une row de la table Deals.

### Étape 7 — Pipeline Kanban
- [ ] `PipelineController@index` qui group les deals par stage.
- [ ] `<x-kanban>` + `<x-kanban.column>` + `<x-kanban.card>`.
- [ ] Câbler SortableJS pour le drag-drop entre colonnes.
- [ ] POST optimiste vers `/deals/{id}/stage` au drop.

### Étape 8 — Contacts, Entreprises, Activités
- [ ] Réutiliser `<x-table>` pour Contacts et Companies.
- [ ] `<x-timeline>` + `<x-timeline.item>` pour Activités.

### Étape 9 — Settings
- [ ] Étapes (drag-réorderable avec SortableJS aussi).
- [ ] Champs perso (table simple).

### Étape 10 — Polish
- [ ] Raccourcis clavier (Alpine global listener).
- [ ] Toasts (Alpine store).
- [ ] Theme toggle dans le user menu.
- [ ] Vérifier dark mode sur les 10 écrans.

---

## Règles strictes

### ✅ À FAIRE
- Lis **chaque** fichier .md de ce dossier avant de commencer.
- Vérifie chaque écran visuellement contre `hifi-reference.html`. Idéalement, ouvre les deux côte-à-côte.
- Utilise **uniquement** les tokens documentés. Si une couleur manque, demande — ne l'invente pas.
- Respecte la convention de nommage des composants (`<x-...>` Blade).
- Garde le HTML lisible : préfère des classes Tailwind explicites à des `@apply` partout. Seuls les patterns ultra-répétés (chip, btn, av) méritent un `@layer components`.
- Commits petits et thématiques (`feat(ui): add Card component`, `feat(deals): table view with filters`).

### ❌ À NE PAS FAIRE
- Pas de couleur inventée. Pas d'`#hex` qui n'est pas dans `DESIGN_SPEC.md`.
- Pas de nouvelle police. **Patrick Hand** (body) + **Caveat** (titres) + **Kalam** (gros chiffres) + **JetBrains Mono** (data dense). C'est tout.
- Pas de gradient sauf le KPI hero (orange → orange foncé).
- Pas d'animation au-delà de 250ms. Pas de spring, pas de bounce.
- Pas d'emoji dans les titres, KPIs ou boutons. Emoji **uniquement** dans la timeline d'activités (📧 📞 📝 ➕ ✓ ✕ 🔄 📅).
- Pas de border-radius asymétrique.
- Pas de drop-shadow coloré.
- Pas de bibliothèque de composants externe (shadcn, Flowbite, DaisyUI…). On construit nos propres composants pour avoir le contrôle exact.
- Pas de framework JS lourd (React, Vue). Alpine suffit.
- Pas d'Inertia.js, on reste server-rendered.
- **Pas de "j'ai amélioré le design"**. Si tu vois un truc qui te semble suboptimal, ouvre une issue/note dans `NOTES.md` — n'agis pas dessus sans validation.

---

## Comment vérifier ton travail

Pour **chaque** écran, fais cette checklist :

1. [ ] Layout général (rail + header + content) matche `hifi-reference.html` ?
2. [ ] Espacement vertical et horizontal identique ?
3. [ ] Couleurs : zéro hex différent ?
4. [ ] Polices : Inter pour body, JetBrains Mono pour data + labels ?
5. [ ] Light mode OK ?
6. [ ] Dark mode OK ? (Toggle dans le header.)
7. [ ] Interactions : hover, focus, click fonctionnent ?
8. [ ] Pas de console error ?
9. [ ] Pas de bouton ou lien sans handler ?
10. [ ] Responsive minimal (≥ 1024px) : pas de scroll horizontal involontaire ?

---

## Données de seed (pour développer)

Crée un `database/seeders/DemoSeeder.php` qui peuple :
- 5 étapes (Prospecting / Qualified / Proposal / Negotiation / Won) avec les couleurs et probabilités du `SCREENS.md` section 7.
- 8 champs perso (voir `SCREENS.md` section 8).
- ~10 entreprises (Acme SA, Lumio Studio, Stelar Corp, Novia, Halo Robotics, Pixel Lab, Mantis Group, Foxtrot, Quanta, Bluemark).
- ~15 contacts répartis sur ces entreprises.
- ~24 deals ouverts répartis sur les stages (voir `hifi-reference.html` écran Pipeline pour la répartition exacte) + 3 won + 3 lost ce mois.
- ~50 activités sur les 7 derniers jours.

Lancer avec `php artisan db:seed --class=DemoSeeder`.

---

## Sécurité & qualité

- **CSRF** : `@csrf` sur tous les forms POST/PATCH/DELETE.
- **Auth** : middleware `auth:jwt` sur toutes les routes sauf `/login`.
- **Validation** : `FormRequest` pour chaque route mutate.
- **N+1** : `with()` systématique sur les relations affichées. Vérifier avec Telescope ou debugbar.
- **Tests** : un Feature test par controller, minimum smoke test (route renvoie 200, contient le H1 attendu).

---

## En cas de doute

- Réfère-toi à `hifi-reference.html` (référence visuelle absolue).
- Réfère-toi à `DESIGN_SPEC.md` (tokens).
- Réfère-toi à `COMPONENTS.md` (API des composants).
- Réfère-toi à `SCREENS.md` (routes + données).
- Pose la question dans `NOTES.md` (crée le fichier si besoin). Ne devine pas.

---

## Premier commit attendu

```bash
git checkout -b feat/ui-redesign
# Étape 0 + 1
git add tailwind.config.js resources/css/app.css resources/views/layouts/app.blade.php resources/views/components/app-shell.blade.php resources/views/components/rail-icon.blade.php
git commit -m "feat(ui): scaffold app shell + design tokens"
```

Bonne implémentation. 🟧
