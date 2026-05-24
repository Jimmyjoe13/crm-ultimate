#!/bin/bash
# Lot 7 — P0 : backup quotidien PostgreSQL CRM
# Cron sur VPS : 0 3 * * * /home/jimmy/scripts/backup_db.sh >> /home/jimmy/logs/backup.log 2>&1
BACKUP_DIR="/opt/backups/crm"
mkdir -p "$BACKUP_DIR"
docker exec crm-postgres pg_dump -U crm crm_ultimate | gzip > "$BACKUP_DIR/crm_$(date +%Y%m%d_%H%M).sql.gz"
# Rotation : garder 7 jours
find "$BACKUP_DIR" -name "*.sql.gz" -mtime +7 -delete
echo "[$(date '+%Y-%m-%d %H:%M')] Backup terminé : $BACKUP_DIR/crm_$(date +%Y%m%d_%H%M).sql.gz"
