#!/usr/bin/env bash
# Restauration d'une sauvegarde produite par scripts/backup.sh
# (base de données + fichiers utilisateurs).
#
# Cible automatiquement le bon backend selon l'environnement, comme
# scripts/backup.sh : conteneur Docker "mysql_db" en dev local, ou MariaDB
# en paquet système en production (identifiants dans webserver/api/.env).
#
# Utilisation :
#   ./scripts/restore.sh backups/20260831_120000
#
# ATTENTION : écrase la base de données et les fichiers actuels.

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

DB_CONTAINER="mysql_db"

BACKUP_PATH="${1:-}"
if [ -z "$BACKUP_PATH" ]; then
  echo "Usage: $0 <chemin_du_dossier_de_sauvegarde>" >&2
  echo "Exemple : $0 backups/20260831_120000" >&2
  exit 1
fi

if [ ! -d "$BACKUP_PATH" ]; then
  echo "ERREUR : dossier de sauvegarde introuvable : $BACKUP_PATH" >&2
  exit 1
fi

if [ -f "$PROJECT_DIR/.env" ]; then
  # shellcheck disable=SC1091
  set -a; source "$PROJECT_DIR/.env"; set +a
fi

if [ -f "$PROJECT_DIR/webserver/api/.env" ]; then
  # shellcheck disable=SC1091
  set -a; source "$PROJECT_DIR/webserver/api/.env"; set +a
fi

echo "[restore] Restauration depuis $BACKUP_PATH"

# 1. Restauration de la base de données
if [ -f "$BACKUP_PATH/database.sql.gz" ]; then
  if docker ps --format '{{.Names}}' 2>/dev/null | grep -qx "$DB_CONTAINER"; then
    echo "[restore] Restauration de la base de données ($DB_CONTAINER, via Docker)..."
    gunzip -c "$BACKUP_PATH/database.sql.gz" | docker exec -i "$DB_CONTAINER" mysql \
      -u root -p"${MYSQL_ROOT_PASSWORD:?MYSQL_ROOT_PASSWORD manquant dans .env}" \
      "${MYSQL_DATABASE:?MYSQL_DATABASE manquant dans .env}"
  elif [ -n "${DB_NAME:-}" ]; then
    echo "[restore] Restauration de la base de données ($DB_NAME, via MariaDB locale)..."
    gunzip -c "$BACKUP_PATH/database.sql.gz" | mysql \
      -h "${DB_HOST:-localhost}" \
      -u "${DB_USER:?DB_USER manquant dans webserver/api/.env}" \
      -p"${DB_PASS:?DB_PASS manquant dans webserver/api/.env}" \
      "$DB_NAME"
  else
    echo "[restore] ERREUR : ni le conteneur Docker $DB_CONTAINER ni une config MariaDB locale (webserver/api/.env) n'ont été trouvés." >&2
    exit 1
  fi
else
  echo "[restore] Pas de database.sql.gz trouvé, base de données ignorée."
fi

# 2. Restauration des fichiers
if [ -f "$BACKUP_PATH/files.tar.gz" ]; then
  echo "[restore] Restauration des fichiers..."
  tar -xzf "$BACKUP_PATH/files.tar.gz" -C "$PROJECT_DIR"
else
  echo "[restore] Pas de files.tar.gz trouvé, fichiers ignorés."
fi

echo "[restore] Restauration terminée."
