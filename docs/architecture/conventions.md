# Conventions de code

## Règles de cohabitation (CRITIQUE)

Le VPS (`/home/jimmy/crm-ultimate/`) est un **dossier flat sans git** : chaque assistant y SCP ses fichiers directement.

| Assistant | Périmètre |
|-----------|-----------|
| **Claude Code** | Backend : `app/`, `routes/`, `database/`, `tests/`, `config/`, `docker/` |
| **Gemini / OWL** | Frontend : `resources/views/pages/`, `resources/views/components/`, CSS |

### Règles impératives

- **Ne jamais SCP** les fichiers du périmètre de l'autre assistant lors d'un déploiement.
- Le `docker compose build` recompile les assets Vite depuis les fichiers VPS en place (Tailwind scanne toutes les blade files).
- Si un fichier est partagé (ex. un contrôleur modifié par les deux), synchroniser via le repo local avant de SCP.

## Conventions Laravel

- **FQCN pour les types polymorphes** : `subject_type` doit être `App\Models\Contact` (pas `contact`)
- **Cache** : utiliser les tags Redis (`Cache::tags(['entity.index'])`) avec invalidation via model events (`saved`/`deleted`)
- **Tests** : `assertSeeText()` pour les vues avec apostrophes (compare le texte décodé, pas le HTML)
- **Tests AJAX** : `->post($url, ['_token' => 'test'])` avec `withSession(['_token' => 'test'])`

## Conventions Blade

- Composants réutilisables dans `resources/views/components/`
- Layout principal : `resources/views/components/app-shell.blade.php`
- Classes CSS custom dans `resources/css/app.css` (variables CSS pour le thème)

## Conventions JavaScript

- Alpine.js pour la réactivité front
- Composants globaux via `window.componentName = function() { ... }` dans des balises `<script>`
- Éviter les fonctions fléchées inline dans les attributs `@click` → déporter dans des méthodes
