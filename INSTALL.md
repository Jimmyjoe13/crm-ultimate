# Installation — CRM Ultimate

## Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (inclut Docker Compose)
- Git

## Démarrage from scratch

### 1. Cloner le dépôt

```bash
git clone https://github.com/Jimmyjoe13/crm-ultimate.git
cd crm-ultimate
```

### 2. Configurer l'environnement

```bash
cp .env.example .env
```

Ouvrir `.env` et renseigner :

| Variable | Description | Exemple |
|---|---|---|
| `APP_KEY` | Clé de chiffrement Laravel — **laisser vide**, générée à l'étape 4 | |
| `JWT_SECRET` | Secret JWT — changer en production | `un-secret-long-et-aleatoire` |
| `OPENROUTER_API_KEY` | Clé API OpenRouter pour les fonctions IA | `sk-or-v1-...` |
| `OPENROUTER_MODEL` | Modèle LLM à utiliser | `openrouter/owl-alpha` |

Les variables base de données et Redis sont pré-remplies pour Docker et n'ont pas besoin d'être modifiées en local.

### 3. Démarrer les conteneurs

```bash
docker compose up -d
```

Quatre services démarrent :

| Service | Port local |
|---|---|
| Application Laravel | http://localhost:8080 |
| PostgreSQL | localhost:5433 |
| Redis | localhost:6380 |
| Queue worker | — |

### 4. Générer la clé applicative

```bash
docker compose exec app php artisan key:generate
```

### 5. Migrer et seeder la base de données

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Cela crée le schéma complet et insère un utilisateur admin par défaut.

### 6. Accéder à l'application

Ouvrir http://localhost:8080

| Champ | Valeur |
|---|---|
| Email | `admin@example.com` |
| Mot de passe | `password` |

## Commandes utiles

```bash
# Voir les logs de l'application
docker compose logs -f app

# Lancer les tests
docker compose exec app php artisan test

# Vérifier le style de code
docker compose exec app vendor/bin/pint --test

# Relancer les migrations sans seed
docker compose exec app php artisan migrate

# Arrêter les conteneurs
docker compose down

# Arrêter et supprimer les volumes (repart de zéro)
docker compose down -v
```

## Structure des rôles

| Rôle | Accès |
|---|---|
| `admin` | Tout, y compris pipelines, imports/exports, champs personnalisés |
| `manager` | Idem admin sauf gestion des utilisateurs |
| `commercial` | CRUD entreprises, contacts, deals, activités — lecture seule sur le reste |

## Fonctions IA

Les endpoints IA (`/api/v1/ai/...`) nécessitent une clé `OPENROUTER_API_KEY` valide dans `.env`.  
Sans clé, les appels retournent une erreur `503` avec le message `LLM provider not configured.`

Obtenir une clé sur https://openrouter.ai/keys.

## Variables d'environnement complètes

```env
APP_NAME="CRM Ultimate"
APP_ENV=local
APP_KEY=                          # généré par artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost:8080

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=crm_ultimate
DB_USERNAME=crm
DB_PASSWORD=crm

REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PORT=6379

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=array

JWT_SECRET=change-me-in-production
JWT_TTL_MINUTES=60

OPENROUTER_API_KEY=
OPENROUTER_MODEL=openrouter/owl-alpha
OPENROUTER_TIMEOUT=30
```
