# Guide de déploiement

> **⚠️ RÈGLE CRITIQUE** : Le code PHP et les vues Blade sont **embarqués dans les images Docker** au moment du `build`. Un simple SCP ne met PAS à jour le code en production.

## Procédure complète

```bash
# 1. Copier les fichiers modifiés sur le VPS (SCP ou paramiko)
# 2. Rebuilder les images
cd ~/crm-ultimate
docker compose -f docker-compose.prod.yml build app queue
docker compose -f docker-compose.prod.yml up -d app queue

# 3. Migrations + clear cache
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T app php artisan config:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan view:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan cache:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache
```

## Règles de cohabitation

| Assistant | Périmètre SCP |
|-----------|---------------|
| Claude Code | `app/`, `routes/`, `database/`, `tests/`, `config/`, `docker/` |
| Gemini / OWL | `resources/views/pages/`, `resources/views/components/`, CSS |

**Ne jamais SCP les fichiers du périmètre de l'autre.**

Le `docker compose build` recompile les assets Vite automatiquement (Tailwind scanne toutes les blade files).

## Tests en production

PHPUnit et tinker sont **absents** en prod (`composer install --no-dev`).

Utiliser des scripts PHP temporaires :

```bash
docker cp /home/jimmy/crm-ultimate/_test.php crm-app:/var/www/html/_test.php
docker exec crm-app php /var/www/html/_test.php
docker exec crm-app rm /var/www/html/_test.php
```

## Backup base de données

```bash
# Script : /home/jimmy/scripts/backup_db.sh
# Cron : 0 3 * * * (quotidien 3h)
# Rétention : 7 jours
# Destination : /opt/backups/crm/
```

## Rollback

En cas de problème après déploiement :

```bash
# Restaurer un backup DB
docker exec crm-postgres pg_restore -U crm -d crm_ultimate /opt/backups/crm/YYYYMMDD_HHMM.sql.gz

# Restaurer un fichier spécifique (depuis le repo local)
scp fichier.php jimmy@51.38.99.226:~/crm-ultimate/chemin/vers/fichier.php
```
