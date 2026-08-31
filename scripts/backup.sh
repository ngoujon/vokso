#!/usr/bin/env bash
# Sauvegarde de la base de données MySQL (via le conteneur Docker "mysql_db")
# et des fichiers utilisateurs (audios uploadés, médias générés).
#
# Utilisation :
#   ./scripts/backup.sh
#
# Variables d'environnement optionnelles :
#   BACKUP_DIR      Répertoire de destination des sauvegardes (défaut : ./backups)
#   RETENTION_DAYS  Nombre de jours de rétention (défaut : 14)
#
# Pour une exécution automatique quotidienne, ajouter à la crontab (crontab -e) :
#   0 3 * * * cd /Volumes/data/workspace-dev/apps/qwai-pod && ./scripts/backup.sh >> logs/backup.log 2>&1

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

BACKUP_DIR="${BACKUP_DIR:-$PROJECT_DIR/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
DEST="$BACKUP_DIR/$TIMESTAMP"

DB_CONTAINER="mysql_db"

if [ -f "$PROJECT_DIR/.env" ]; then
  # shellcheck disable=SC1091
  set -a; source "$PROJECT_DIR/.env"; set +a
fi

mkdir -p "$DEST"

echo "[backup] $(date -Iseconds) Démarrage de la sauvegarde vers $DEST"

# 1. Sauvegarde de la base de données
if docker ps --format '{{.Names}}' | grep -qx "$DB_CONTAINER"; then
  echo "[backup] Dump de la base de données ($DB_CONTAINER)..."
  docker exec "$DB_CONTAINER" mysqldump \
    -u root -p"${MYSQL_ROOT_PASSWORD:?MYSQL_ROOT_PASSWORD manquant dans .env}" \
    --single-transaction --routines --triggers \
    "${MYSQL_DATABASE:?MYSQL_DATABASE manquant dans .env}" \
    | gzip > "$DEST/database.sql.gz"
else
  echo "[backup] ERREUR : le conteneur $DB_CONTAINER n'est pas démarré, dump de la base ignoré." >&2
  exit 1
fi

# 2. Sauvegarde des fichiers utilisateurs (uploads audio, médias générés)
echo "[backup] Archivage des fichiers..."
tar -czf "$DEST/files.tar.gz" \
  --exclude='node_modules' \
  -C "$PROJECT_DIR" \
  webserver/api/storage \
  webserver/public/output

echo "[backup] Sauvegarde terminée : $DEST"

# 3. Purge des sauvegardes plus anciennes que RETENTION_DAYS
find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -mtime "+$RETENTION_DAYS" -print -exec rm -rf {} \;

echo "[backup] $(date -Iseconds) Fin"
