# CRM Ultimate — Documentation

> **URL Production** : https://crm.nana-intelligence.fr
> **Stack** : Laravel 13 + PostgreSQL + Redis + Blade + Alpine.js + Docker
> **VPS** : 51.38.99.226 (Ubuntu 22.04)

## Structure

```
docs/
├── architecture/        # Stack technique, infra, conventions, modèle de données
│   ├── stack.md
│   ├── conventions.md
│   └── data-model.md
├── features/            # Changelog + specs par fonctionnalité
│   ├── changelog.md
│   ├── blacklist.md
│   ├── emelia-integration.md
│   ├── ai-features.md
│   └── integrations.md
├── dev-guides/          # Guides opérationnels
│   ├── deployment.md
│   ├── testing.md
│   └── troubleshooting.md
└── handoff.md           # Passation entre sessions (état courant + prochains pas)
```

## Dernières versions

| Version | Date | Contenu | Statut |
|---------|------|---------|--------|
| v4.0 | 2026-06-08 | Audit sécurité, rate limit, webhook sig, owner scope | ✅ Prod |
| v3.9 | 2026-05-25 | Badge blacklist + toggle liste contacts | ✅ Prod |
| v3.5 | 2026-05-25 | Blacklist contacts + multi-intent Emelia | ✅ Prod |
| v3.4 | 2026-05-24 | Console Artisan admin | ✅ Prod |
| v3.3 | 2026-05-24 | IA Rapports insights | ✅ Prod |
| v3.2 | 2026-05-24 | Export CSV segments | ✅ Prod |
| v3.1 | 2026-05-24 | Page Rapports & Analytics | ✅ Prod |
| v3.0 | 2026-05-24 | Optimisations performances backend (7 lots) | ✅ Prod |

## Contributeurs

- **Claude Code** : Backend (PHP, migrations, services, routes, jobs, tests)
- **Gemini / OWL** : Frontend (vues Blade, composants, UI/UX, CSS)
