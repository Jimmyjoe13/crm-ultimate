# CRM Ultimate

API-first B2B CRM built with Laravel, PostgreSQL and Redis. The v1 focuses on the CRM core: companies, contacts, deals, configurable pipelines, activities, custom fields, saved views, CSV import/export, JWT authentication, roles and audit logs.

## Quick Start

```bash
cp .env.example .env
docker compose build
docker compose run --rm app composer install
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

The web CRM runs at `http://localhost:8080`.
The API runs at `http://localhost:8080/api/v1`.
PostgreSQL is exposed on local port `5433` by default to avoid conflicts with an existing local database.

Default seeded admin:

```text
email: admin@example.com
password: password
```

## Useful Commands

```bash
docker compose exec app php artisan test
docker compose exec app php artisan queue:work
docker compose exec app php artisan migrate:fresh --seed
```

## API Docs

The first OpenAPI contract is in [docs/openapi.yaml](docs/openapi.yaml).

## Browser Usage

Open `http://localhost:8080` to use the CRM interface. The page logs in through the same REST API and stores the JWT in browser local storage.

The interface includes:

- dashboard indicators
- tables for companies, contacts, deals, activities, pipelines and stages
- detail panels with edit/delete actions
- activity creation on company/contact/deal records
- deal Kanban board with stage movement
- modern dark-accent CRM styling optimized for daily sales work

`http://localhost:8080/api/v1` is not the CRM screen. It is the API index and returns JSON with useful endpoints. Use specific API routes such as:

```text
POST http://localhost:8080/api/v1/auth/login
GET  http://localhost:8080/api/v1/companies
POST http://localhost:8080/api/v1/contacts
POST http://localhost:8080/api/v1/deals
GET  http://localhost:8080/api/v1/deals/board
```

## Implementation Notes

See [docs/IMPLEMENTATION.md](docs/IMPLEMENTATION.md) for the full implementation summary, endpoints, architecture decisions and known limits.
