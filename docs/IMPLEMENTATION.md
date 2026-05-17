# CRM Ultimate - Implementation Documentation

## What Was Built

This repository now contains an API-first Laravel CRM foundation for a B2B product inspired by HubSpot CRM core features. The implementation is backend-only and focuses on a professional REST API that can be consumed by an external app, frontend dashboard, automation tool or third-party integration.

An initial browser interface was also added at `/` so the CRM can be used directly without Postman or a separate frontend project. It now includes dashboard indicators, list/detail views, record editing, deletion, activity creation and a Kanban board for deals. The UI uses a modern CRM-style layout with a darker sidebar, high-contrast workspace, orange action accents and teal status accents.

## Technical Architecture

- Laravel 13 project structure with PHP 8.3 requirement.
- PostgreSQL as the main database.
- Redis configured for cache and queued jobs through the portable `predis/predis` client.
- Docker Compose stack with `app`, `queue`, `postgres` and `redis` services.
- JWT authentication implemented with an internal HS256 service using PHP standard HMAC functions.
- API versioning under `/api/v1`.
- Browser CRM interface served at `/`.
- OpenAPI documentation in `docs/openapi.yaml`.
- PHPUnit feature tests for authentication and core CRM creation flow.

## CRM Domain Implemented

The database schema and Eloquent models cover:

- `users` with roles: `admin`, `manager`, `commercial`.
- `companies` with owner and `custom_values`.
- `contacts` linked to companies and owners.
- `deals` linked to company, contact, pipeline, stage and owner.
- `pipelines` and `pipeline_stages` for configurable sales processes.
- `activities` for notes, tasks, calls and emails.
- `custom_fields` to define dynamic properties for companies, contacts and deals.
- `saved_views` to persist filters, sort and selected columns per user.
- `import_jobs` and `export_jobs` for CSV operations.
- `audit_logs` for essential create/update/delete history on companies, contacts and deals.

## API Endpoints

Public:

- `GET /api/v1`
- `POST /api/v1/auth/login`

Authenticated:

- `GET /api/v1/auth/me`
- `POST /api/v1/auth/refresh`
- `POST /api/v1/auth/logout`
- `GET|POST|GET:id|PUT/PATCH:id|DELETE:id /api/v1/companies`
- `GET|POST|GET:id|PUT/PATCH:id|DELETE:id /api/v1/contacts`
- `GET|POST|GET:id|PUT/PATCH:id|DELETE:id /api/v1/deals`
- `GET /api/v1/deals/board`
- `POST /api/v1/deals/{deal}/move`
- `GET|POST|GET:id|PUT/PATCH:id|DELETE:id /api/v1/activities`
- `GET|POST|GET:id|PUT/PATCH:id|DELETE:id /api/v1/tasks`
- `GET|POST|GET:id|PUT/PATCH:id|DELETE:id /api/v1/saved-views`

Admin and manager only:

- `GET|POST|GET:id|PUT/PATCH:id|DELETE:id /api/v1/pipelines`
- `GET|POST|GET:id|PUT/PATCH:id|DELETE:id /api/v1/pipeline-stages`
- `GET|POST|GET:id|PUT/PATCH:id|DELETE:id /api/v1/custom-fields`
- `GET|POST|GET:id /api/v1/imports`
- `GET|POST|GET:id /api/v1/exports`
- `GET /api/v1/exports/{export}/download`

## Query Features

List endpoints support:

- `search=term` for text search on selected fields.
- `filter[field]=value` for exact filters.
- `sort=field` or `sort=-field`.
- `per_page=25` pagination size.

## Auth And Roles

Authentication uses Bearer JWT:

```http
Authorization: Bearer <token>
```

Role behavior:

- `admin`: full CRM and configuration access.
- `manager`: full CRM and configuration access except future admin-only operations.
- `commercial`: operational CRM access to visible data, without configuration/import/export endpoints.

The chosen v1 visibility rule is simple: all authenticated users can see CRM records. Roles restrict sensitive operations.

## CSV Import And Export

Imports:

- Upload CSV to `/api/v1/imports`.
- Supported entities: `company`, `contact`, `deal`.
- Import is queued via `ProcessCsvImport`.
- CSV headers must match model fillable fields.
- Per-row errors are stored on `import_jobs.errors`.

Exports:

- Create export jobs at `/api/v1/exports`.
- Supported entities: `company`, `contact`, `deal`.
- Optional exact-match filters are accepted.
- Generated files are stored under `storage/app/private/exports`.

## Audit Logging

Companies, contacts and deals use an `Auditable` trait. The audit logger stores:

- event type: `created`, `updated`, `deleted`
- authenticated user id when available
- model type and id
- old and new values
- timestamp

This creates the first version of an essential CRM history trail.

## Deployment

The project includes Docker support:

```bash
cp .env.example .env
docker compose build
docker compose run --rm app composer install
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

The application container serves Laravel on port `8080`. Open `http://localhost:8080` for the web CRM interface.
PostgreSQL is exposed on local port `5433` by default, while the in-container database host remains `postgres:5432`.

## How To Use The CRM

For browser usage:

1. Start the stack with `docker compose up -d`.
2. Open `http://localhost:8080`.
3. Log in with `admin@example.com` and `password`.
4. Use the left navigation to create and list companies, contacts, deals, activities, pipelines and stages.
5. Open `Deals` to use the Kanban board and move deals between pipeline stages.
6. Click a row in a list to edit, delete, inspect audit history or add linked activities.

For API usage:

- `http://localhost:8080/api/v1` returns the API index as JSON.
- Authenticate with `POST /api/v1/auth/login`.
- Send the returned token with `Authorization: Bearer <token>`.
- Use the resource endpoints under `/api/v1`.

## Tests Added

- `tests/Feature/AuthTest.php`: login and authenticated profile read.
- `tests/Feature/CrmApiTest.php`: create company, contact, pipeline stage and deal through the API.

Run tests with:

```bash
docker compose exec app php artisan test
```

## Known Limits Of This First Implementation

- The Laravel dependencies are declared but not installed in this environment because local PHP/Composer are not available and Docker Desktop daemon is not running.
- OpenAPI is a first contract, not yet generated automatically from PHP annotations.
- JWT logout is stateless and client-side only; token blacklist can be added later.
- Custom field definitions exist, and values are stored in `custom_values`, but type enforcement against definitions is not yet centralized.
- Import validation is intentionally simple and should be hardened before production use.
- Saved views store filters/sort/columns but do not yet enforce ownership in every query.
- Webhooks, marketing automation, ticketing and a frontend dashboard are intentionally out of v1.
