#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE="docker compose -f docker-compose.prod.yml"

cd "$APP_DIR"

echo "==> [1/6] Git pull"
git pull origin master

echo "==> [2/6] Build des images Docker"
$COMPOSE build

echo "==> [3/6] Démarrage des services"
$COMPOSE up -d

echo "==> [4/6] Migrations"
$COMPOSE exec -T app php artisan migrate --force

echo "==> [5/6] Cache Laravel (config / routes / vues)"
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache

echo "==> [6/6] État des conteneurs"
$COMPOSE ps

echo ""
echo "=== Déploiement terminé ==="
