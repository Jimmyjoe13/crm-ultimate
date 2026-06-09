# Modèle de données

## Entités principales

### contacts

| Colonne | Type | Notes |
|---------|------|-------|
| id | UUID | |
| first_name | string | index |
| last_name | string | index |
| email | string | unique |
| phone | string | |
| lifecycle_stage | enum(lead, mql, sql, customer, churned) | |
| lead_status | enum(new, open, closed) | |
| owner_id | FK users | eager-load recommandé |
| blacklisted_at | timestamp nullable | index |
| blacklist_reason | string(255) nullable | |
| ai_score | smallint nullable | index |
| ai_score_updated_at | timestamp nullable | |
| emelia_contact_id | string nullable | ID Emelia |
| emelia_campaign_id | string nullable | |
| emelia_campaign_name | string nullable | |
| custom_values | JSONB | champs personnalisés |

### companies

| Colonne | Type | Notes |
|---------|------|-------|
| id | UUID | |
| name | string | index |
| domain | string | unique |
| industry | string | |
| city | string | |
| owner_id | FK users | |

### deals

| Colonne | Type | Notes |
|---------|------|-------|
| id | UUID | |
| name | string | |
| amount | decimal | |
| status | enum(open, won, lost) | |
| pipeline_stage_id | FK pipeline_stages | |
| close_date | date | |
| owner_id | FK users | |
| company_id | FK companies | nullable |

### activities

| Colonne | Type | Notes |
|---------|------|-------|
| id | UUID | |
| type | enum(call, email, task, note, meeting, email_sent, email_opened, email_clicked, email_replied, email_bounced, email_unsubscribed) | |
| subject_type | string | FQCN obligatoire |
| subject_id | UUID | |
| owner_id | FK users | |
| occurred_at | timestamp nullable | index — timestamp réel de l'event |
| source | enum(crm, emelia, gmail, synthetic) | |
| external_id | string nullable | pour idempotence |
| metadata | JSONB | |
| due_date | date nullable | pour les tâches |
| status | enum(open, done) | pour les tâches |

### pipeline_stages

| Colonne | Type | Notes |
|---------|------|-------|
| id | UUID | |
| name | string | |
| position | integer | |
| is_won | boolean | index |
| is_lost | boolean | index |

### custom_fields

| Colonne | Type | Notes |
|---------|------|-------|
| id | UUID | |
| entity_type | enum(contact, company, deal) | |
| name | string | |
| field_type | enum(text, number, date, boolean, select) | |
| options | JSONB | pour les select |
| is_required | boolean | |

### import_jobs

| Colonne | Type | Notes |
|---------|------|-------|
| id | UUID | |
| duplicate_strategy | enum(skip, update, create) | défaut: skip |
| status | enum(pending, processing, completed, completed_with_errors, failed) | |
| total_rows | integer | |
| processed_rows | integer | |
| errors | JSONB | |

### console_runs

| Colonne | Type | Notes |
|---------|------|-------|
| id | UUID | |
| user_id | FK users | |
| command | string | |
| output | text | |
| exit_code | integer | |
| duration_ms | integer | |

## Relations clés

```
Contact → Company (many-to-many via company_contact)
Contact → Deal (many-to-many via contact_deal)
Deal → Company (many-to-many via company_deal)
Contact → Activity (polymorphique, subject_type/subject_id)
Deal → PipelineStage (N:1)
Contact → User (owner, N:1)
```

## Scopes Eloquent importants

```php
// Contact
Contact::blacklisted()          // whereNotNull('blacklisted_at')
Contact::contactable()          // whereNull('blacklisted_at')

// Activity — constantes de type
Activity::TYPE_EMAIL_SENT      // 'email_sent'
Activity::TYPE_EMAIL_OPENED    // 'email_opened'
Activity::TYPE_EMAIL_REPLIED   // 'email_replied'
Activity::TYPE_EMAIL_BOUNCED   // 'email_bounced'
Activity::TYPE_EMAIL_UNSUBSCRIBED // 'email_unsubscribed'
```

## Indexes de performance

| Table | Index | Type |
|-------|-------|------|
| contacts | first_name | B-tree |
| contacts | last_name | B-tree |
| contacts | ai_score | B-tree |
| contacts | (first_name \|\| last_name \|\| email) | GIN trigram |
| pipeline_stages | is_won | B-tree |
| pipeline_stages | is_lost | B-tree |
| activities | (subject_type, subject_id) | B-tree composite |
