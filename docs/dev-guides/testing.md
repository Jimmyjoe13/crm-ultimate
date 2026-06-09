# Guide de tests

## Tests locaux (PHPUnit)

```bash
# Tous les tests
php artisan test

# Par fichier
php artisan test tests/Feature/EmeliaIntentWebhookTest.php

# Par méthode
php artisan test --filter=test_admin_can_view_reports
```

## Tests en production

PHPUnit absent en prod → utiliser des scripts PHP temporaires :

```bash
docker cp _test.php crm-app:/var/www/html/_test.php
docker exec crm-app php /var/www/html/_test.php
docker exec crm-app rm /var/www/html/_test.php
```

## Tests E2E (Playwright)

Configuration de connexion E2E avec 100% de réussite.

## Conventions de test

### CSRF dans les tests AJAX Web
```php
->post($url, ['_token' => 'test'])
->withSession(['_token' => 'test'])
```

### Apostrophes dans les vues
```php
// ❌ assertSee("Modifier l'entreprise") — échoue à cause du HTML encoding
// ✅ assertSeeText("Modifier l'entreprise") — compare le texte décodé
```

### Comparaison numérique post-DB
```php
// ❌ assertSame(7500.0, $dbValue) — JSON round-trip transforme en int
// ✅ assertEquals(7500.0, $dbValue) — comparaison souple
```

## Suites de tests par version

| Version | Fichier(s) | Tests |
|---------|-------------|-------|
| v3.5 | `tests/Feature/EmeliaIntentWebhookTest.php` | 11 tests (stop/duplicate/already-blacklisted/no-email/unknown-contact/invalid-sig/interested/not_interested/out_of_office/unknown-intent/scopeContactable) |
| v3.1 | `tests/Feature/ReportControllerTest.php` | 7 tests (admin/manager/commercial/vue/entonnoir/cache/invalidation) |
| v3.0 | `tests/Feature/AiPrecomputeTest.php` | 9 tests |
| v2.8 | `tests/Feature/AnalyzeReplySentimentTest.php` | 8 tests |
| v2.5 | `tests/Feature/EmeliaEventDispatcherTest.php` | 8 tests (REPLIED → tâche + lifecycle + idempotence) |
| v2.5 | `tests/Feature/EmeliaSyncContactEventsTest.php` | 7 tests (polling + idempotence + dry-run + mock) |
| v2.3 | `tests/Feature/BulkActionsTest.php` | Bulk delete × 3 entités + 403 viewer |
| v2.1 | `tests/Feature/ImportCustomFieldsTest.php` | custom_values + cast types |
| v2.1 | `tests/Feature/ImportRequiredFieldsTest.php` | Validation requis |
| v2.1 | `tests/Feature/ImportDuplicateStrategyTest.php` | skip/update/create + merge |
