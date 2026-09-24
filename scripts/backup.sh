#!/usr/bin/env bash
# Sauvegarde de la base de données et des fichiers utilisateurs (audios
# uploadés, médias générés).
#
# Cible automatiquement le bon backend selon l'environnement :
#   - dev local  : MySQL via le conteneur Docker "mysql_db" (docker-compose)
#   - production : MariaDB installée en paquet système (scripts/setup-server.sh),
#                   dump en local via mysqldump avec les identifiants de
#                   webserver/api/.env (DB_HOST/DB_NAME/DB_USER/DB_PASS)
#
# Utilisation :
#   ./scripts/backup.sh
#
# Variables d'environnement optionnelles :
#   BACKUP_DIR      Répertoire de destination des sauvegardes (défaut : ./backups)
#   RETENTION_DAYS  Nombre de jours de rétention (défaut : 14)
#
# Pour l'exécution automatique quotidienne en production, voir la crontab
# installée par scripts/setup-server.sh (cible le répertoire de déploiement
# réel du serveur, ex. /var/www/html).

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

BACKUP_DIR="${BACKUP_DIR:-$PROJECT_DIR/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
DEST="$BACKUP_DIR/$TIMESTAMP"

DB_CONTAINER="mysql_db"

# Dev local (docker-compose) : variables MYSQL_* dans le .env à la racine.
if [ -f "$PROJECT_DIR/.env" ]; then
  # shellcheck disable=SC1091
  set -a; source "$PROJECT_DIR/.env"; set +a
fi

# Production (MariaDB en paquet système) : variables DB_* dans
# webserver/api/.env, cf. scripts/setup-server.sh.
if [ -f "$PROJECT_DIR/webserver/api/.env" ]; then
  # shellcheck disable=SC1091
  set -a; source "$PROJECT_DIR/webserver/api/.env"; set +a
fi

mkdir -p "$DEST"

echo "[backup] $(date -Iseconds) Démarrage de la sauvegarde vers $DEST"

# 1. Sauvegarde de la base de données
if docker ps --format '{{.Names}}' 2>/dev/null | grep -qx "$DB_CONTAINER"; then
  echo "[backup] Dump de la base de données ($DB_CONTAINER, via Docker)..."
  docker exec "$DB_CONTAINER" mysqldump \
    -u root -p"${MYSQL_ROOT_PASSWORD:?MYSQL_ROOT_PASSWORD manquant dans .env}" \
    --single-transaction --routines --triggers \
    "${MYSQL_DATABASE:?MYSQL_DATABASE manquant dans .env}" \
    | gzip > "$DEST/database.sql.gz"
elif [ -n "${DB_NAME:-}" ]; then
  echo "[backup] Dump de la base de données ($DB_NAME, via MariaDB locale)..."
  mysqldump \
    -h "${DB_HOST:-localhost}" \
    -u "${DB_USER:?DB_USER manquant dans webserver/api/.env}" \
    -p"${DB_PASS:?DB_PASS manquant dans webserver/api/.env}" \
    --single-transaction --routines --triggers \
    "$DB_NAME" \
    | gzip > "$DEST/database.sql.gz"
else
  echo "[backup] ERREUR : ni le conteneur Docker $DB_CONTAINER ni une config MariaDB locale (webserver/api/.env) n'ont été trouvés." >&2
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
