# Stack technique & Infrastructure

## Stack applicatif

| Couche | Techno |
|--------|--------|
| Backend | Laravel 13 (PHP 8.3) |
| Frontend | Blade + Alpine.js + Tailwind CSS (Vite) |
| Base de données | PostgreSQL 17 |
| Cache / Queue / Sessions | Redis 7 |
| IA | OpenRouter API (Claude Haiku 4.5) |
| Emailing | Emelia (GraphQL + REST) |
| Reverse proxy | Caddy (HTTPS Let's Encrypt) |
| Conteneurisation | Docker + supervisord |

## Architecture Docker (production)

**Repo** : `/home/jimmy/crm-ultimate/`
**Compose** : `docker compose -f docker-compose.prod.yml`

| Conteneur | Image | Rôle |
|-----------|-------|------|
| `crm-app` | `crm-ultimate-app` | Nginx:8080 + PHP-FPM:9000 via supervisord |
| `crm-queue` | `crm-ultimate-queue` | Worker queue Redis |
| `crm-postgres` | `postgres:17-alpine` | Base de données (volume `pgdata`) |
| `crm-redis` | `redis:7-alpine` | Cache + sessions + queue |

Caddy Docker partagé sur réseau `web` (`/home/jimmy/docker/docker-compose.yml`).

## Variables d'environnement VPS

```
APP_URL=https://crm.nana-intelligence.fr
ASSET_URL=https://crm.nana-intelligence.fr
TRUSTED_PROXIES=*
OPENROUTER_API_KEY=sk-or-v1-...
OPENROUTER_MODEL=anthropic/claude-haiku-4-5
EMELIA_API_KEY=5jwwzTUNnb0IrdDEJtMVe1D0h8nsOgECa07X73IJsLozKq6U
EMELIA_WEBHOOK_SECRET=bc36d8f114a744e03e578c1b4f9380fbe416de780da2a8c4cebb271ee7a5d08e
EMELIA_BASE_URL=https://api.emelia.io
```

## Comptes production

```
Email    : admin@example.com
Password : password   ← à changer
```

## Comptes utilisateurs

- **Jimmy** (admin) — jimmygay13180@gmail.com
- **Jonathan** (manager) — jo.boetsch@gmail.com

## Campagnes Emelia connues

| Nom | UUID |
|-----|------|
| acquisition-agence-marketing | `69eb1cca5033df0a8663a88e` |
| conseiller-consultant-acquisition | `69ebb7281762b60a8169f625` |
