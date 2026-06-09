# Handoff — CRM Ultimate

## État courant

- **Version** : v4.1 (IA améliorée + UI alertes proactives) — ✅ déployé
- **Prod** : https://crm.nana-intelligence.fr ✅
- **Tests** : 140+ PASS
- **Dernier déploiement** : 2026-06-09 (rebuild Docker v4.1.1)
- **Modèle IA** : `openrouter/owl-alpha` (gratuit)

## Dernière session (2026-06-09)

| Version | Assistant | Contenu |
|---------|-----------|---------|
| v4.1.1 | OWL | **Fix cookie JWT `secure: false`** : le cookie JWT était créé avec `secure: false` → navigateur refusait de le stocker en HTTPS → boucle de login. Fix: `→ secure: true` dans `AuthController::login()`. Rebuild Docker effectué. |
| v4.1.1 | OWL | **Incohérence BDD détectée** : la migration `add_fulltext_search_to_activities` ajoutait `ALTER TABLE deals ADD COLUMN ai_score` puis la retirait… mais le container de prod tournait encore avec l'ancien code qui utilisait `ai_score` sur deals. Le rebuild a résolu le problème (code actuel = pas de `ai_score` sur deals). |
| v4.1.1 | OWL | **Bug login utilisateur non résolu** : Jimmy ne peut toujours pas se connecter. Hash Bcrypt en DB correct, mot de passe semble mauvais ou incertain. **À reset si nécessaire.** |
| v4.1 | OWL | **Refonte IA complète** : parser JSON robuste, cache adaptatif (TTL variables), contexte enrichi (tendances SQL), 8 system prompts spécialisés, batching score contacts, RAG léger, alertes proactives |
| v4.1 | OWL | **UI alertes proactives** : bandeau dashboard (Alpine, expandable, dismiss), badge orange sidebar |
| — | OWL | **Refonte docs/** : structure découpée (architecture/, features/, dev-guides/), handoff.md léger |

## Fichiers modifiés/créés (v4.1 IA)

| Fichier | Action |
|---------|--------|
| `app/Services/AiInsightService.php` | Refonte complète (793 lignes, 29 méthodes) |
| `app/Services/LlmService.php` | Support response_format JSON |
| `app/Http\Controllers\Web\AiController.php` | + proactiveAlerts() |
| `app\Console\Commands\AiScoreContacts.php` | Batching |
| `app\Jobs\AiProactiveAlertsJob.php` | Nouveau |
| `app\Notifications\AiProactiveAlertNotification.php` | Nouveau |
| `app\Console\Commands\AiProactiveAlertsCommand.php` | Nouveau |
| `database\migrations\2026_06_09_000001_add_fulltext_search_to_activities.php` | Nouveau |
| `routes\web.php` | + GET /web/ai/proactive-alerts |
| `routes\console.php` | + schedule proactives 2h |

## Prochains pas

### Priorité immédiate
1. ~~Reset mot de passe Jimmy~~ — ✅ Résolu (mauvais mot de passe, pas un bug)
2. **🔄 Améliorer UX/UI connexion Emelia ↔ CRM** — Prochaine session demandée par Jimmy. Améliorer l'expérience de liaison entre les campagnes Emelia et les contacts/deals du CRM (mapping visuel, feedback, détection auto, etc.)

### v4.2 — Intégrations tierces
3. **Google Calendar** — OAuth2 + sync événements
4. **Gmail sync** — Réutilise OAuth2 Calendar

### Backlog moyen terme
5. Optimisation performance Timeline (lazy load, pagination infinie)
6. Sélection globale companies/deals (même pattern que contacts)
7. Supprimer compte `admin@example.com` / `password`

## Documentation

| Sujet | Fichier |
|-------|---------|
| Stack & infra | `docs/architecture/stack.md` |
| Conventions | `docs/architecture/conventions.md` |
| Modèle de données | `docs/architecture/data-model.md` |
| Changelog | `docs/features/changelog.md` |
| Blacklist | `docs/features/blacklist.md` |
| Emelia | `docs/features/emelia-integration.md` |
| IA | `docs/features/ai-features.md` |
| Intégrations tierces | `docs/features/integrations.md` |
| Déploiement | `docs/dev-guides/deployment.md` |
| Tests | `docs/dev-guides/testing.md` |
| Bugs connus | `docs/dev-guides/troubleshooting.md` |

## Règles de cohabitation

| Assistant | Périmètre |
|-----------|-----------|
| **Claude Code** | Backend : `app/`, `routes/`, `database/`, `tests/`, `config/`, `docker/` |
| **OWL (moi)** | Frontend : `resources/views/`, CSS, JS, docs |

→ Ne jamais SCP les fichiers du périmètre de l'autre. Toujours rebuild Docker après modif.
