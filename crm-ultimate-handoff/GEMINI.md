# CRM Ultimate — Instructions Gemini

> **DIRECTIVE ABSOLUE :** Lis et respecte [`collaboration.md`](../collaboration.md) avant toute action.  
> Ce fichier définit les règles de cohabitation entre toi (Gemini) et Claude Code. Le non-respect de ces règles peut casser le projet.

---

## Ton périmètre

Tu es responsable des **vues uniquement** :

- `resources/views/**/*.blade.php` — Templates Blade
- Alpine.js inline dans les vues (`x-data`, `x-show`, `x-text`, `@click`, etc.)
- Classes Tailwind CSS (pas de CSS custom hors tokens définis)

Tu ne touches **jamais** à :
- `app/` (PHP — Controllers, Models, Jobs, Services, Middleware)
- `routes/web.php`
- `database/migrations/`
- `tests/`
- Les scripts de déploiement

---

## Source de vérité UI

Lis `CLAUDE.md` dans ce même dossier pour :
- Le design system (tokens, composants, conventions Blade)
- La liste des écrans et leur structure attendue
- Les règles typographiques et d'espacement

---

## Contrats backend disponibles (ne pas modifier)

| Feature | Route | Méthode | Auth |
|---|---|---|---|
| Export CSV segment | `GET /segments/{segment}/export` | — | admin/manager |
| Insights IA rapports | `POST /web/ai/report-insights` | JSON | admin/manager |
| Console Admin | `GET /settings/console` | — | admin uniquement |
| Status run console | `GET /settings/console/run/{run}` | JSON | admin uniquement |

---

## Règles de développement UI

1. **Alpine uniquement** pour l'interactivité — pas de jQuery, pas de Vue, pas de React.
2. **CSRF** : toujours via `document.querySelector('meta[name=csrf-token]')?.content` — jamais en dur.
3. **Spinner** : utiliser le pattern SVG `animate-spin` déjà présent dans les vues existantes.
4. **Erreurs** : toujours afficher un message d'erreur lisible en cas d'échec réseau.
5. **Responsive** : les vues doivent fonctionner sur mobile (breakpoints Tailwind `sm:`, `md:`).
6. Valider visuellement dans le navigateur (golden path + cas limites) avant de déclarer terminé.

---

## Handoff

Après chaque livraison, mets à jour `GEMINI_handoff.md` à la racine du projet :
- Ce qui a été livré
- Ce qui reste
- Ce qui est bloquant (backend manquant, contrat flou, etc.)
