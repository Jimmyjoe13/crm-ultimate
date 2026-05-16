# CRM Ultimate — Écrans & Routes

Spec détaillée de chacun des 10 écrans, avec les routes Laravel attendues et les composants à utiliser.

> **Référence visuelle** : ouvrir `hifi-reference.html` dans un navigateur, utiliser le menu de navigation flottant en bas-droite pour passer d'un écran à l'autre. Tester aussi le toggle Light/Dark dans le header.

---

## Plan des routes (toutes en `auth` middleware sauf login)

```php
// routes/web.php
Route::middleware('auth:jwt')->group(function () {
    Route::get('/',                    [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/deals',               [DealController::class, 'index'])->name('deals.index');
    Route::get('/deals/{deal}',        [DealController::class, 'show'])->name('deals.show');
    Route::post('/deals',              [DealController::class, 'store'])->name('deals.store');
    Route::patch('/deals/{deal}',      [DealController::class, 'update'])->name('deals.update');
    Route::patch('/deals/{deal}/stage',[DealController::class, 'updateStage'])->name('deals.stage');
    Route::post('/deals/{deal}/won',   [DealController::class, 'markWon'])->name('deals.won');
    Route::post('/deals/{deal}/lost',  [DealController::class, 'markLost'])->name('deals.lost');

    Route::get('/pipeline',            [PipelineController::class, 'index'])->name('pipeline.index');

    Route::get('/contacts',            [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/{contact}',  [ContactController::class, 'show'])->name('contacts.show');

    Route::get('/companies',           [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');

    Route::get('/activities',          [ActivityController::class, 'index'])->name('activities.index');
    Route::post('/activities',         [ActivityController::class, 'store'])->name('activities.store');

    Route::get('/settings/stages',     [StageController::class, 'index'])->name('stages.index');
    Route::post('/settings/stages',    [StageController::class, 'store']);
    Route::patch('/settings/stages/{stage}', [StageController::class, 'update']);

    Route::get('/settings/fields',     [CustomFieldController::class, 'index'])->name('fields.index');
    Route::post('/settings/fields',    [CustomFieldController::class, 'store']);

    Route::get('/search',              [SearchController::class, 'index'])->name('search'); // command palette
});
```

---

## 1. Dashboard — `GET /`

**Layout** : `<x-app-shell active="dashboard">` + 4 sections verticales.

### Section 1 : Salutation
- H1 "Bonjour {{ $user->first_name }} —"
- Sous-titre : "Voici l'état de ton pipeline ce {{ today()->isoFormat('dddd D MMMM') }}."
- Boutons à droite : Filtrer, période (30j default), pill "Temps réel" si websocket connecté.

### Section 2 : KPI band (4 colonnes)
1. **Hero (orange)** : Pipeline total 30j en € + delta vs mois précédent + sparkline 9 points.
2. **Conversion 30j** : pourcentage + delta + progress bar vers objectif (par défaut 55%).
3. **Gagnés ce mois** : nombre vert + montant total + liste des 3 noms.
4. **Perdus ce mois** : nombre rouge + montant total + liste des 3 noms (ou "+ N autres").

### Section 3 : Grid 3 colonnes
- **Col 1-2 (2/3 width) — Prochaines actions** : liste de tâches dues aujourd'hui/demain, ordre suggéré par score × proximité.
  - Une row par tâche : checkbox, avatar, titre + sous-ligne mono (étape · montant · score), heure due, chip d'étiquette (Hot/Due/Cold/Prep/Sign).
  - Tâches complétées (state `done`) en bas, rayées, opacité 60%.
- **Col 3 (1/3 width) — Stack vertical** :
  - **Pipeline par étape** : pbar par étape avec count + montant.
  - **Activité du jour** : timeline compacte (3-5 items max).

### Données

```php
return view('dashboard', [
    'kpis' => [
        'pipeline_total' => Deal::open()->sum('amount'),
        'pipeline_delta' => /* vs M-1 */ 12.4,
        'conversion'     => /* won / closed sur 30j */ 50,
        'won_count'      => Deal::wonThisMonth()->count(),
        'won_amount'     => Deal::wonThisMonth()->sum('amount'),
        'lost_count'     => Deal::lostThisMonth()->count(),
        'lost_amount'    => Deal::lostThisMonth()->sum('amount'),
    ],
    'tasks'      => Task::dueSoon()->with('deal.company', 'assignee')->orderBy('priority_score', 'desc')->limit(10)->get(),
    'pipeline'   => Stage::withCount('openDeals')->withSum('openDeals as total', 'amount')->orderBy('position')->get(),
    'activity'   => Activity::today()->with('subject')->orderBy('created_at', 'desc')->limit(5)->get(),
]);
```

---

## 2. Deals — `GET /deals`

Table dense filtrable.

### Header
- H1 "Deals" + sous-ligne "24 deals ouverts · 1 853 972 € en pipeline"
- À droite : toggle `Table | Kanban` (Kanban renvoie vers `/pipeline`).

### Filter bar (chips horizontaux)
- All · {count} (solid)
- Mine · {count}
- Hot · {count} (accent)
- Closing this week · {count}
- Overdue tasks · {count} (err si > 0)
- À droite : sort indicator + bouton "Saved view".

### Table
Colonnes :
| col | label | content |
|---|---|---|
| checkbox | — | bulk select |
| Deal | text | Titre `font-medium` + sub mono "Created DD Mon · N activities" |
| Company | text | `text-secondary` |
| Amount | num | `font-semibold num` aligné droite |
| Stage | chip | dot couleur étape + nom |
| Close date | num | rouge si overdue |
| Owner | avatar | sm |
| Score | chip | accent si ≥ 80 |
| — | menu | dots verticaux |

### Pagination
Footer : "8 sur 24" + Précédent / Suivant.

### Backend hints
```php
Deal::query()
    ->filter($request->only(['filter', 'sort', 'search']))
    ->with('company', 'owner', 'stage')
    ->paginate(20);
```

---

## 3. Pipeline (Kanban) — `GET /pipeline`

5 colonnes (Prospecting / Qualified / Proposal / Negotiation / Won — Lost masqué par défaut).

### Header
- H1 "Pipeline Commerce"
- Sub : "Glisse les deals d'une étape à l'autre. {{ amount }} total."
- Sélecteur de pipeline si plusieurs (`<select>` Pipeline Commerce / SAV / ...).

### Column header
- Dot couleur (étape) + nom + chip count + total (`text-tertiary num`).

### Card (`<x-kanban.card>`)
- Titre (company name) + score chip à droite (si `hot`).
- Montant `num` `text-[12px]`.
- Bottom row : avatar sm + tag mono ("retainer", "platform"...) + temps depuis / urgence.
- `:hot="true"` ajoute border-left orange 3px.

### Interactions
- Drag-drop via SortableJS, POST à `/deals/{id}/stage` avec `stage_id` cible.
- Optimistic UI (déplacer la card immédiatement, revert en cas d'erreur 4xx/5xx).
- Click sur card → ouvre `<x-drawer name="deal-detail">` chargé via fetch `/deals/{id}` qui renvoie HTML partiel (`@if(request()->ajax())`).

### Backend
```php
$stages = Stage::orderBy('position')->get();
$deals  = Deal::open()->with('company', 'owner')->orderBy('amount', 'desc')->get()->groupBy('stage_id');
```

---

## 4. Contacts — `GET /contacts`

Table similaire à Deals mais colonnes différentes.

### Filtres
- Tous · 187 (solid)
- Décideurs · 28 (filtre `role IN ('CEO', 'CTO', 'COO', ...)`)
- Sans activité 30j · 41 (`last_activity_at < now()->subDays(30)`)
- Champions · 12 (filtre `is_champion = true`)

### Colonnes
| col | content |
|---|---|
| checkbox | bulk |
| Nom | avatar c{n} + nom font-medium |
| Rôle | text-secondary |
| Entreprise | text-secondary (link) |
| Email | font-mono text-[12px] |
| Tél | font-mono text-[12px] text-tertiary |
| Deals | chip num (accent si activeDeals > 0 sur ce contact) |
| Dernière activité | font-mono text-[12px] text-tertiary (rouge si > 7 jours) |

---

## 5. Entreprises — `GET /companies`

Table compagnies.

### Colonnes
| col | content |
|---|---|
| checkbox | bulk |
| Entreprise | avatar lg rounded-md + nom + sub mono "{domain}" |
| Secteur | text-secondary |
| Taille | "120 emp." font-mono |
| Pays | "🇫🇷 FR" font-mono |
| Deals | chip num |
| Pipeline | num font-semibold (total des deals ouverts) |
| Owner | avatar sm |

---

## 6. Activités — `GET /activities`

Vue timeline 7 jours.

### Layout
2 colonnes :
- **Col 1-2 (2/3) — Timeline** : items groupés par date, chaque groupe préfixé par `mono-label` "AUJOURD'HUI · VENDREDI 16 MAI".
- **Col 3 (1/3)** : 2 cards stack
  - Stat card : "Activité · 7j" + nombre total + grid 2×2 (emails / appels / notes / changements).
  - "Top deals actifs" : liste des deals avec count d'activité.

### Types d'activité (emoji + variant timeline)
- Email envoyé/reçu : 📧 + variant `info`
- Note ajoutée : 📝 + variant `default`
- Deal créé : ➕ + variant `accent`
- Appel : 📞 + variant `info`
- Étape changée : 🔄 + variant `default`
- Deal gagné : ✓ + variant `ok`
- Deal perdu : ✕ + variant `err`
- Réunion : 📅 + variant `accent`

### Filtres header
- Type (multiselect Email/Appel/Note/...)
- Période (7j, 30j, 90j, Custom)

---

## 7. Étapes (config) — `GET /settings/stages`

Liste de stages réorganisable (drag handle à gauche).

### Layout
Une seule grosse card, table sans header explicite, colonnes :
| col | content |
|---|---|
| drag handle | Lucide `lucide-grip-vertical` text-tertiary cursor-grab |
| position | num "01..05" |
| Étape | nom font-medium + description font-mono text-[11.5px] text-tertiary |
| Probabilité | chip num "10%..100%" |
| Couleur | swatch 12×12 cercle + hex font-mono |
| Deals | num count |
| menu | dots verticaux |

### États système
- "Won" et "Lost" sont des stages spéciaux, **non drag**, opacité 75%, badge "verrouillée".

### Header
- H1 "Étapes du pipeline" + bouton `+ Ajouter étape` primary qui ouvre une modal.

---

## 8. Champs perso — `GET /settings/fields`

Table de configuration des custom fields.

### Filtres
- Deals · 8 (solid)
- Contacts · 5
- Entreprises · 3

### Colonnes
| col | content |
|---|---|
| Label | font-medium |
| Clé API | font-mono text-[12px] text-tertiary |
| Type | chip (Select / Number / Date / Multi-select / Text long / Boolean / URL) |
| Obligatoire | chip "oui" accent / "non" default |
| Valeurs | font-mono text-[12px] text-tertiary (liste séparée par "·") |
| Utilisé | "11/24" num text-[12px] |

### Header
- H1 "Champs personnalisés"
- Bouton `+ Nouveau champ` primary → ouvre modal de création.

---

## 9. Deal Detail — Drawer (overlay sur deals/pipeline)

Pas une route séparée. Ouvre via `?deal={id}` ou via fetch partiel.

### Structure
**Header (px-6 py-4 border-b)** :
- Avatar lg rounded-md (logo company) + titre "{{ company }} — {{ deal_name }}" + chip stage + chip score "★ 92".
- Sous-ligne mono : "DEAL-2026-0142 · créé 03 mai · 14 activités · close 22/05".
- Actions à droite : assigner, marquer comme suivi, menu, fermer (X).

**Stage progress (px-6 py-4 border-b)** :
- 5 segments. Voir `<x-drawer.stage-progress>`.
- Sous la barre : boutons "Étape précédente" / "Marquer gagné ✓" (primary) / "Marquer perdu" + indicateur `⌘ ↵`.

**Body — grid `[1fr_280px]`** :

#### Col gauche : Activité
- Tabs : Activité (14) / Notes (3) / Emails (6) / Tâches (4) / Fichiers (2)
- Composer : card avec "Ajouter une note, un email, un appel…" + boutons type + bouton primary "Enregistrer".
- Timeline (voir composant section 12 dans COMPONENTS.md).

#### Col droite : Propriétés (`bg-surface-2`)
Sections séparées par `mono-label` :
- **Propriétés** : montant, étape, probabilité, close date, owner, source, type.
- **Contact principal** : avatar + nom + rôle + email mono.
- **Entreprise** : avatar lg rounded-md + nom + meta.
- **Champs perso** : key/value list.

### Interactions
- Touche `Esc` ferme le drawer.
- `⌘ ↵` avance d'une étape (POST `/deals/{id}/stage`).
- Click hors drawer ferme.

---

## 10. Nouveau deal — Modal

Pas une route séparée. Trigger : bouton "Nouveau deal" partout dans l'app, ou raccourci `⌘ N`.

### Modal width 580px

**Header** :
- "Nouveau deal" + sub mono "Ajoute un deal à ton pipeline · ⌘ N"
- Croix close

**Body — grid 2 cols** :
- **Nom du deal** (full width, required) — input text
- **Montant** — input text num
- **Devise** — select (EUR / USD / GBP)
- **Entreprise** — autocomplete (utilise `<input>` + dropdown async) ; preview pill avec avatar + ✕
- **Contact principal** — autocomplete filtré sur les contacts de l'entreprise sélectionnée
- **Étape** — select (par défaut "Qualified" si l'entreprise a déjà des deals, sinon "Prospecting")
- **Close date** — input date avec format `DD/MM/YYYY`
- **Source du lead** (full width) — `<x-form.chip-group>` (Inbound / Outbound / Referral / Event / Cold)
- **Note initiale** (full width) — textarea 2 rows

**Footer (`bg-surface-2 border-t px-6 py-4`)** :
- Helper `⌘ ↵ pour créer` à gauche
- Boutons "Annuler" + "Créer le deal" (primary) à droite

### Validation backend
```php
$request->validate([
    'name' => 'required|string|max:200',
    'amount' => 'required|numeric|min:0',
    'currency' => 'required|in:EUR,USD,GBP',
    'company_id' => 'required|exists:companies,id',
    'contact_id' => 'nullable|exists:contacts,id',
    'stage_id' => 'required|exists:stages,id',
    'close_date' => 'required|date|after_or_equal:today',
    'lead_source' => 'nullable|string',
    'notes' => 'nullable|string|max:2000',
]);
```

Après création : redirect vers `/deals` avec toast success "Deal créé · {nom}".

---

## Tâches transverses

### Command palette (`⌘K`)
Modal centré avec input search au top + résultats groupés (Deals / Contacts / Companies / Actions). Backend `/search?q=...` qui renvoie JSON groupé. Pas critique pour la v1 — peut être un placeholder.

### Raccourcis clavier
| Raccourci | Action |
|---|---|
| `⌘ K` | Ouvrir search |
| `⌘ N` | Nouveau deal |
| `Esc` | Fermer modal/drawer |
| `g d` | Aller à Deals |
| `g p` | Aller à Pipeline |
| `g c` | Aller à Contacts |
| `⌘ ↵` | Action principale du drawer (avancer étape) |

À implémenter avec Alpine + listener global sur `keydown`.

### Toasts
Stack en bas-droite. Après chaque mutation (POST/PATCH/DELETE), backend renvoie un flash session lu par un middleware → injecté dans une `window.__toasts` au render → Alpine consomme.

---

## Ordre d'implémentation suggéré (pour Claude Code)

1. **Setup** : Tailwind config + tokens CSS dans `app.css` + fonts Google + layouts Blade vides.
2. **Composants atomiques** : `<x-button>`, `<x-chip>`, `<x-avatar>`, `<x-kbd>`, `<x-pbar>`, `<x-form.field>`, `<x-form.checkbox>`.
3. **`<x-app-shell>`** : rail + header + theme toggle. Vérifier light/dark.
4. **`<x-card>`** et `<x-table>`.
5. **Dashboard** (route 1) : c'est l'écran qui valide tous les patterns.
6. **Deals** (route 2) : table grosse.
7. **Drawer Deal Detail** (route 9) : pattern overlay, à valider tôt car réutilisé.
8. **Modal Nouveau deal** (route 10).
9. **Pipeline Kanban** (route 3) + SortableJS.
10. **Contacts / Entreprises / Activités** (4, 5, 6) : variations de patterns déjà établis.
11. **Settings** (Étapes + Champs perso) (7, 8).
12. Polish : raccourcis clavier, command palette (peut être stub), toasts.
