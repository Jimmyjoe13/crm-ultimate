# CRM Ultimate — Rapport Technique

**Date de rédaction** : 15 mai 2026  
**Version analysée** : v1.0  
**Auteur** : Analyse automatisée Claude Code

---

## 1. Vue d'ensemble du projet

CRM Ultimate est un CRM B2B API-first construit avec Laravel 13, inspiré du cœur fonctionnel de HubSpot CRM. Le projet fournit une API REST complète consommable par des outils externes, accompagnée d'une interface web intégrée accessible directement depuis un navigateur.

**Objectifs initiaux retenus :**

- Backend API-first, REST versionnée sous `/api/v1`
- Stack Laravel 13 + PostgreSQL 17 + Redis 7
- Authentification JWT maison (HS256 via HMAC natif PHP)
- Trois rôles : `admin`, `manager`, `commercial`
- Instance unique (pas de multi-tenant en v1)
- CRM cœur : entreprises, contacts, deals, pipelines, activités, champs personnalisables, import/export CSV, audit logs

---

## 2. Architecture technique

### 2.1 Stack

| Composant      | Technologie               | Version |
|----------------|---------------------------|---------|
| Framework      | Laravel                   | ^13.0   |
| Langage        | PHP                       | ^8.3    |
| Base de données | PostgreSQL               | 17-alpine |
| Cache / Queue  | Redis + Predis            | 7-alpine / ^3.0 |
| Conteneurisation | Docker Compose          | —       |
| Tests          | PHPUnit                   | ^12.0   |
| Linter         | Laravel Pint              | ^1.25   |

### 2.2 Services Docker

```
app      → Laravel HTTP (php artisan serve)     port 8080
queue    → Laravel queue worker                 (interne)
postgres → PostgreSQL                           port 5433 (local)
redis    → Redis                                port 6380 (local)
```

Le port PostgreSQL local est `5433` (et non `5432`) pour éviter les conflits avec une éventuelle base locale existante.

### 2.3 Structure du code

```
app/
  Http/
    Controllers/
      Api/
        Concerns/CrudActions.php      ← trait CRUD générique
        AuthController.php
        CompanyController.php
        ContactController.php
        DealController.php            ← avec board() et move()
        ActivityController.php
        TaskController.php
        PipelineController.php
        PipelineStageController.php
        CustomFieldController.php
        SavedViewController.php
        ImportController.php
        ExportController.php
    Middleware/
      JwtMiddleware.php
      RequireRole.php
  Models/
    Concerns/Auditable.php            ← trait audit automatique
    User.php  Company.php  Contact.php  Deal.php
    Pipeline.php  PipelineStage.php
    Activity.php  CustomField.php
    SavedView.php  ImportJob.php  ExportJob.php  AuditLog.php
  Services/
    JwtService.php                    ← JWT HS256 maison
    AuditLogger.php
  Support/
    CrmQuery.php                      ← filtre/tri/recherche générique
  Jobs/
    ProcessCsvImport.php
    ProcessCsvExport.php
```

### 2.4 Patterns architecturaux notables

**Trait `CrudActions`** : tous les contrôleurs CRUD partagent le même trait (`index`, `store`, `show`, `update`, `destroy`). Chaque contrôleur déclare `$modelClass`, `$searchable` et implémente `rules()`. Cela supprime la duplication de code sur 8 ressources.

**`CrmQuery`** : classe statique qui applique uniformément sur n'importe quel `Builder` Eloquent les paramètres de requête `search`, `filter[field]`, `sort`, `per_page`. Utilisée dans tous les `index()`.

**`Auditable` trait** : hookés sur les événements Eloquent `created`, `updated`, `deleted`, les modèles Company, Contact, Deal enregistrent automatiquement leurs changements dans `audit_logs` via `AuditLogger`.

---

## 3. Modèle de données

### 3.1 Schéma complet (migration principale : `2026_05_15_000001_create_crm_schema.php`)

#### `users`
| Colonne     | Type       | Détail                                |
|-------------|------------|---------------------------------------|
| id          | bigint PK  |                                       |
| name        | string     |                                       |
| email       | string     | unique                                |
| password    | string     |                                       |
| role        | string     | default `commercial`, indexé          |
| manager_id  | FK users   | nullable, self-référence              |

Rôles : `admin`, `manager`, `commercial`

#### `companies`
| Colonne       | Type     | Détail                          |
|---------------|----------|---------------------------------|
| id            | bigint PK|                                 |
| name          | string   | indexé                          |
| domain        | string   | nullable, indexé                |
| industry      | string   | nullable                        |
| phone         | string   | nullable                        |
| website       | string   | nullable                        |
| city / country| string   | nullable                        |
| owner_id      | FK users | nullable                        |
| custom_values | jsonb    | default `{}`                    |
| deleted_at    | timestamp| soft deletes                    |

#### `contacts`
| Colonne    | Type       | Détail              |
|------------|------------|---------------------|
| first_name | string     |                     |
| last_name  | string     | nullable            |
| email      | string     | nullable, indexé    |
| phone      | string     | nullable            |
| job_title  | string     | nullable            |
| company_id | FK companies| nullable           |
| owner_id   | FK users   | nullable            |
| custom_values | jsonb   | default `{}`        |
| deleted_at | timestamp  | soft deletes        |

#### `pipelines` / `pipeline_stages`

Pipeline : `name`, `is_default`.  
Stage : `pipeline_id`, `name`, `position`, `probability` (0–100), `is_won`, `is_lost`. Unicité sur `(pipeline_id, position)`.

Pipeline seed par défaut : **Default Sales Pipeline** avec 5 étapes (Prospecting → Qualified → Proposal → Won → Lost).

#### `deals`
| Colonne           | Type     | Détail                    |
|-------------------|----------|---------------------------|
| name              | string   | indexé                    |
| amount            | decimal  | 15,2 — default 0          |
| currency          | char(3)  | default `EUR`             |
| close_date        | date     | nullable                  |
| status            | string   | `open`/`won`/`lost`, indexé|
| company_id        | FK       | nullable                  |
| contact_id        | FK       | nullable                  |
| pipeline_id       | FK       | cascade delete            |
| pipeline_stage_id | FK       | cascade delete            |
| owner_id          | FK users | nullable                  |
| custom_values     | jsonb    | default `{}`              |
| deleted_at        | timestamp| soft deletes              |

#### `activities`

Polymorphe via `nullableMorphs('subject')` : une activité peut être liée à une Company, un Contact ou un Deal.  
Types : `note`, `task`, `call`, `email`.  
Statuts : `open`, `completed`.  
Champs : `title`, `body`, `due_at`, `completed_at`, `owner_id`.

#### `custom_fields`

Définit les champs dynamiques par `entity_type` (`company`, `contact`, `deal`).  
Champs : `key`, `label`, `field_type`, `options` (jsonb), `is_required`, `position`.  
Les valeurs sont stockées dans `custom_values` (jsonb) sur chaque entité.

#### `saved_views`

Par utilisateur et `entity_type`. Persiste `filters`, `sort`, `columns` (tous jsonb).

#### `import_jobs` / `export_jobs`

Import : suivi du statut (`pending`, `processing`, `completed`, `completed_with_errors`), compteurs de lignes, erreurs JSON ligne par ligne.  
Export : filtres, `file_path`, statut, nombre de lignes.

#### `audit_logs`

Morphique sur `auditable` (company, contact, deal). Stocke `event` (created/updated/deleted), `old_values`, `new_values`, `user_id`.

---

## 4. Authentification et sécurité

### 4.1 JWT maison (JwtService)

La dépendance `firebase/php-jwt` a été écartée (advisory sécurité Composer). Le service `JwtService` implémente HS256 avec les fonctions HMAC natives PHP :

- `encode(array $payload): string` — construit `header.payload.signature` en base64url
- `decode(string $token): array` — vérifie la signature avec `hash_equals` (timing-safe), contrôle l'expiration via le claim `exp`
- Le secret est lu depuis `config('jwt.secret')`

### 4.2 Middleware

- **`JwtMiddleware`** : extrait le Bearer token, appelle `JwtService::decode()`, charge l'utilisateur depuis `sub`, l'injecte dans `Auth::login()`
- **`RequireRole`** : compare `auth()->user()->role` aux rôles autorisés passés en paramètre

### 4.3 Matrice des rôles

| Ressource                          | commercial | manager | admin |
|------------------------------------|:----------:|:-------:|:-----:|
| companies / contacts / deals       | ✓          | ✓       | ✓     |
| activities / tasks / saved-views   | ✓          | ✓       | ✓     |
| pipelines / pipeline-stages        | —          | ✓       | ✓     |
| custom-fields                      | —          | ✓       | ✓     |
| imports / exports                  | —          | ✓       | ✓     |

### 4.4 Limites connues

- Le logout est **stateless** (côté client uniquement) : le token reste valide jusqu'à expiration. Une blacklist Redis peut être ajoutée.
- Pas de rate limiting sur les endpoints d'authentification en v1.

---

## 5. API REST

### 5.1 Conventions

- Base URL : `http://localhost:8080/api/v1`
- Authentification : `Authorization: Bearer <token>`
- Réponses : JSON, wrapper `{ "data": ... }` pour les ressources uniques, pagination Laravel pour les listes
- Codes HTTP : 200 (OK), 201 (créé), 204 (supprimé), 401 (non authentifié), 403 (rôle insuffisant), 404 (non trouvé), 422 (validation)

### 5.2 Paramètres de liste universels

| Paramètre       | Exemple                    | Description                  |
|-----------------|----------------------------|------------------------------|
| `search`        | `?search=Acme`             | Recherche ilike multi-champs |
| `filter[field]` | `?filter[status]=open`     | Filtre exact                 |
| `sort`          | `?sort=-created_at`        | Tri (préfixe `-` = desc)     |
| `per_page`      | `?per_page=50`             | Taille de page (défaut 25)   |

### 5.3 Endpoints complets

**Publics :**
```
GET  /api/v1                    → index API (JSON)
POST /api/v1/auth/login         → { email, password } → { token }
```

**Authentifiés (tous rôles) :**
```
GET|POST                /api/v1/companies
GET|PUT|PATCH|DELETE    /api/v1/companies/{id}
GET|POST                /api/v1/contacts
GET|PUT|PATCH|DELETE    /api/v1/contacts/{id}
GET|POST                /api/v1/deals
GET|PUT|PATCH|DELETE    /api/v1/deals/{id}
GET                     /api/v1/deals/board
POST                    /api/v1/deals/{id}/move
GET|POST                /api/v1/activities
GET|PUT|PATCH|DELETE    /api/v1/activities/{id}
GET|POST                /api/v1/tasks
GET|PUT|PATCH|DELETE    /api/v1/tasks/{id}
GET|POST                /api/v1/saved-views
GET|PUT|PATCH|DELETE    /api/v1/saved-views/{id}
GET                     /api/v1/auth/me
POST                    /api/v1/auth/refresh
POST                    /api/v1/auth/logout
```

**Admin + Manager uniquement :**
```
GET|POST                /api/v1/pipelines
GET|PUT|PATCH|DELETE    /api/v1/pipelines/{id}
GET|POST                /api/v1/pipeline-stages
GET|PUT|PATCH|DELETE    /api/v1/pipeline-stages/{id}
GET|POST                /api/v1/custom-fields
GET|PUT|PATCH|DELETE    /api/v1/custom-fields/{id}
GET|POST                /api/v1/imports
GET                     /api/v1/imports/{id}
GET|POST                /api/v1/exports
GET                     /api/v1/exports/{id}
GET                     /api/v1/exports/{id}/download
```

### 5.4 Endpoint `deals/board`

Retourne la structure Kanban du pipeline par défaut :
```json
{
  "pipeline": { "id": 1, "name": "Default Sales Pipeline" },
  "columns": [
    { "stage": { ... }, "deals": [ ... ] },
    ...
  ]
}
```

### 5.5 Endpoint `deals/{id}/move`

Change l'étape d'un deal et recalcule automatiquement le statut :
- Si la stage a `is_won = true` → statut `won`
- Si `is_lost = true` → statut `lost`
- Sinon → statut `open`

---

## 6. Import / Export CSV

### 6.1 Import

1. `POST /api/v1/imports` avec le fichier CSV et `entity_type` (`company`, `contact`, `deal`)
2. Création d'un `ImportJob` en base
3. Job `ProcessCsvImport` dispatché sur la queue Redis
4. Le job lit le CSV ligne par ligne, mappe les headers aux champs du modèle, crée les enregistrements
5. Statuts : `pending` → `processing` → `completed` / `completed_with_errors`
6. Les erreurs par ligne sont stockées dans `import_jobs.errors` (JSON)

**Limitation actuelle** : la validation est basique (les headers doivent correspondre exactement aux champs fillable). Pas de mapping visuel.

### 6.2 Export

1. `POST /api/v1/exports` avec `entity_type` et filtres optionnels
2. Job `ProcessCsvExport` dispatché en queue
3. Fichier généré dans `storage/app/private/exports/`
4. Téléchargement via `GET /api/v1/exports/{id}/download`

---

## 7. Interface web

L'interface est une Single Page Application légère servie par la vue Blade `resources/views/crm.blade.php`. Elle consomme l'API REST et stocke le token JWT dans le `localStorage` du navigateur.

**Fonctionnalités :**
- Login via `/api/v1/auth/login`
- Dashboard avec indicateurs KPI
- Navigation latérale sombre (sidebar)
- Tables pour : entreprises, contacts, deals, activités, pipelines, étapes
- Création, édition et suppression d'enregistrements
- Fiches détaillées avec activités liées et historique d'audit
- Vue Kanban des deals avec déplacement d'étape (drag → appel `move`)
- Design inspiré des CRM modernes : palette sombre, accents orange (actions) et teal (statuts)

---

## 8. Tests

### 8.1 Suite existante

| Fichier de test              | Couverture                                         |
|------------------------------|----------------------------------------------------|
| `tests/Feature/AuthTest.php` | Login, lecture du profil authentifié               |
| `tests/Feature/CrmApiTest.php` | Création company → contact → pipeline stage → deal |

Résultat de référence : **3 tests passés, 19 assertions**.

### 8.2 Commandes

```bash
# Lancer les tests dans le conteneur
docker compose exec app php artisan test

# Vérifier le formatage
docker compose exec app vendor/bin/pint --test

# Valider composer.json
docker compose exec app composer validate --strict
```

---

## 9. Démarrage du projet

```bash
# 1. Copier les variables d'environnement
cp .env.example .env

# 2. Construire les images Docker
docker compose build

# 3. Installer les dépendances PHP
docker compose run --rm app composer install

# 4. Démarrer tous les services
docker compose up -d

# 5. Générer la clé d'application
docker compose exec app php artisan key:generate

# 6. Créer le schéma et les données seed
docker compose exec app php artisan migrate --seed
```

**Compte admin seed :**
```
email    : admin@example.com
password : password
```

**URLs d'accès :**
```
Interface web : http://localhost:8080
API           : http://localhost:8080/api/v1
PostgreSQL    : localhost:5433  (user: crm / pass: crm / db: crm_ultimate)
Redis         : localhost:6380
```

---

## 10. État actuel et limites connues

### Ce qui est opérationnel

- API REST complète avec CRUD sur toutes les entités CRM
- Authentification JWT fonctionnelle
- Contrôle d'accès par rôle (`admin`, `manager`, `commercial`)
- Vue Kanban des deals
- Import/Export CSV asynchrone via queue
- Audit automatique des modifications
- Interface web autonome
- Champs personnalisables (définition + stockage en jsonb)
- Vues sauvegardées par utilisateur

### Limites de la v1

| Domaine | Limitation |
|---------|-----------|
| JWT | Logout stateless — pas de blacklist token |
| Champs personnalisés | Les valeurs jsonb ne sont pas validées par rapport aux définitions `custom_fields` |
| Import CSV | Validation basique, pas de mapping visuel des colonnes |
| Vues sauvegardées | Propriété non entièrement vérifiée sur toutes les requêtes |
| OpenAPI | Contrat partiel, non généré automatiquement depuis les annotations |
| Tests | Couverture réduite : 3 tests feature, pas de tests unitaires, pas de tests frontend |
| Sécurité | Pas de rate limiting sur l'API d'authentification |

---

## 11. Prochaines évolutions recommandées

Classées par valeur métier décroissante :

1. **Gestion des utilisateurs depuis l'UI** — création, édition, attribution de rôle
2. **Champs personnalisés dans l'interface** — formulaires dynamiques selon les définitions
3. **Recherche globale multi-objets** — une seule barre de recherche
4. **Notifications et rappels de tâches** — alertes in-app ou email
5. **Blacklist JWT** — logout effectif côté serveur via Redis
6. **Import CSV assisté** — mapping visuel des colonnes CSV vers les champs du modèle
7. **Webhooks** — notifications sortantes pour les intégrations externes
8. **API keys** — authentification machine-to-machine sans JWT utilisateur
9. **OpenAPI exhaustif** — génération depuis les annotations PHP (`l5-swagger` ou équivalent)
10. **Tests E2E** — couverture Playwright de l'interface web
11. **Rate limiting** — protection des endpoints d'authentification

---

## 12. Références internes

| Document                   | Contenu                                          |
|----------------------------|--------------------------------------------------|
| `docs/IMPLEMENTATION.md`   | Résumé technique initial, architecture, limites  |
| `docs/SESSION_SUMMARY.md`  | Journal de la session de création du projet      |
| `docs/openapi.yaml`        | Contrat OpenAPI v1 (partiel)                     |
| `README.md`                | Démarrage rapide et commandes utiles             |
