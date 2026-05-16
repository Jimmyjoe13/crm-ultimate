# CRM Ultimate — Handoff Package

Tout ce qu'il faut pour que Claude Code (dans ton terminal) implémente le design.

## Comment utiliser ce dossier

1. **Télécharge** et dézippe ce dossier dans la racine de ton projet Laravel.
2. **Ouvre `hifi-reference.html`** dans ton navigateur — c'est la référence visuelle.
3. Dans ton terminal, lance Claude Code à la racine du projet :
   ```bash
   claude
   ```
4. Donne-lui cette instruction :
   > Lis `crm-ultimate-handoff/CLAUDE.md` en entier, puis suis les étapes d'implémentation dans l'ordre. Avant chaque écran, ouvre `crm-ultimate-handoff/hifi-reference.html` comme référence et navigue jusqu'à l'écran correspondant.

5. Claude Code lira `CLAUDE.md` (instructions), `DESIGN_SPEC.md` (tokens), `COMPONENTS.md` (composants), `SCREENS.md` (écrans), et `tailwind.config.js` (config prête).

## Fichiers du package

| Fichier | Rôle |
|---|---|
| `CLAUDE.md` | **À LIRE EN PREMIER.** Instructions pour Claude Code : workflow, règles strictes, ordre d'implémentation. |
| `DESIGN_SPEC.md` | Tokens : couleurs (light + dark), typographie, spacing, radius, shadows, animations. |
| `COMPONENTS.md` | Liste des composants Blade à créer (`<x-button>`, `<x-card>`, `<x-kanban>`, etc.) avec leur API. |
| `SCREENS.md` | Détail des 10 écrans + routes Laravel + données à charger. |
| `tailwind.config.js` | Config Tailwind prête à coller à la racine du projet. |
| `hifi-reference.html` | **Maquette HTML interactive** des 10 écrans — référence visuelle absolue. Toggle light/dark, navigation flottante en bas-droite. |

## Stack ciblée (côté frontend)

- **Blade** (server-rendered, Laravel 13)
- **Tailwind CSS 3** + variables CSS pour light/dark
- **Alpine.js 3** pour interactivité
- **Lucide icons** via `mallardduck/blade-lucide-icons`
- **SortableJS** (CDN) pour le drag-drop du kanban

Backend déjà existant : Laravel 13 / PHP 8.3 / PostgreSQL / Redis / JWT.

## Conseils pour la session Claude Code

- Donne-lui **accès au repo** complet — il a besoin de lire `composer.json`, `package.json`, les migrations existantes.
- **Ne lui demande pas tout d'un coup.** Procède par étape (`CLAUDE.md` les liste).
- À chaque étape, **compare visuellement** avec `hifi-reference.html`. Si c'est pas pixel-près, fais corriger.
- Garde un fichier `NOTES.md` au fur et à mesure pour noter les décisions et les écarts éventuels.

## Bon courage 🟧
