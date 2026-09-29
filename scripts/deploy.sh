#!/usr/bin/env bash
# Déploie la dernière version de la branche de production sur le serveur :
# récupère le code, installe les dépendances de l'API Laravel, build le
# frontend React, recharge Apache. Ce script s'exécute SUR le serveur cible (soit à la main en SSH,
# soit appelé automatiquement par .github/workflows/deploy.yml).
#
# Utilisation (depuis le répertoire du projet sur le serveur) :
#   ./scripts/deploy.sh
#
# Variables d'environnement optionnelles :
#   DEPLOY_BRANCH   Branche à déployer (défaut : production)
#   APACHE_RELOAD   Commande de rechargement d'Apache
#                   (défaut : "sudo systemctl reload apache2")
#
# Ne gère pas les migrations SQL : les fichiers sous SQL/ ne sont pas tous
# rejouables (ALTER/INSERT non idempotents, voir SQL/*.sql), elles restent
# appliquées manuellement et consciemment (voir docs/DEPLOIEMENT.md).
#
# Rollback : ce script affiche le commit précédemment déployé avant de
# mettre à jour. En cas de problème, revenir en arrière avec :
#   git checkout <commit-affiché> && ./scripts/deploy.sh

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

DEPLOY_BRANCH="${DEPLOY_BRANCH:-production}"
APACHE_RELOAD="${APACHE_RELOAD:-sudo systemctl reload apache2}"

echo "[deploy] $(date -Iseconds) Démarrage du déploiement (branche : $DEPLOY_BRANCH)"

PREVIOUS_COMMIT="$(git rev-parse HEAD)"
echo "[deploy] Commit actuellement déployé (pour rollback éventuel) : $PREVIOUS_COMMIT"

echo "[deploy] Récupération du code..."
git fetch origin "$DEPLOY_BRANCH"
git checkout "$DEPLOY_BRANCH"
git reset --hard "origin/$DEPLOY_BRANCH"

NEW_COMMIT="$(git rev-parse HEAD)"
echo "[deploy] Nouveau commit : $NEW_COMMIT"

API_DIR="$PROJECT_DIR/webserver/api"

echo "[deploy] Installation des dépendances backend (composer)..."
composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction --working-dir="$API_DIR"

# Clé d'application Laravel : générée une seule fois si absente du .env.
# Non bloquant : l'API n'en dépend pas pour fonctionner (pas de session ni
# de cookie chiffré). Le .env appartient généralement à Apache et n'est pas
# lisible par l'utilisateur de déploiement : on ne tente alors rien.
ENV_FILE="$API_DIR/.env"
if [ -r "$ENV_FILE" ] && [ -w "$ENV_FILE" ]; then
    if ! grep -q '^APP_KEY=base64:' "$ENV_FILE"; then
        echo "[deploy] Génération de la clé d'application Laravel (APP_KEY)..."
        grep -q '^APP_KEY=' "$ENV_FILE" || echo 'APP_KEY=' >> "$ENV_FILE"
        php "$API_DIR/artisan" key:generate --force --no-interaction || echo "[deploy] AVERTISSEMENT : APP_KEY non générée."
    fi
else
    echo "[deploy] .env de l'API non modifiable par $(whoami) : génération éventuelle de APP_KEY ignorée."
fi

# Dossiers écrits par Apache (www-data) au runtime. Non bloquant si sudo
# n'est pas autorisé pour ces commandes.
mkdir -p "$API_DIR/storage/uploads" "$PROJECT_DIR/webserver/public/output" "$PROJECT_DIR/webserver/logs" 2>/dev/null || true
sudo -n chgrp -R www-data "$API_DIR/storage" "$PROJECT_DIR/webserver/logs" 2>/dev/null \
    && sudo -n chmod -R g+rwX "$API_DIR/storage" "$PROJECT_DIR/webserver/logs" 2>/dev/null \
    || echo "[deploy] AVERTISSEMENT : droits d'écriture de www-data non ajustés (sudo indisponible)."

# Pas de config:cache : l'utilisateur de déploiement ne peut pas lire le .env,
# le cache produit contiendrait des valeurs par défaut.
rm -f "$API_DIR/bootstrap/cache/config.php" "$API_DIR/bootstrap/cache/routes-v7.php"

echo "[deploy] Installation des dépendances frontend et build (npm)..."
npm ci --prefix webserver
npm run build --prefix webserver

# apache-config/vokso.conf n'était jusqu'ici copié vers /etc/apache2/sites-available
# qu'une fois, à l'installation initiale (scripts/setup-server.sh) : tout
# changement versionné du vhost (réécritures d'URL, en-têtes...) restait sans
# effet en prod tant que personne ne relançait cette copie à la main. On la
# resynchronise donc à chaque déploiement, avec un test de syntaxe et une
# restauration automatique en cas de config invalide pour ne jamais recharger
# Apache sur un vhost cassé.
echo "[deploy] Synchronisation de la configuration Apache (apache-config/vokso.conf)..."
APACHE_VHOST_DEST="/etc/apache2/sites-available/vokso.conf"
APACHE_VHOST_BACKUP="$(mktemp)"
if [ -f "$APACHE_VHOST_DEST" ]; then
    sudo cp "$APACHE_VHOST_DEST" "$APACHE_VHOST_BACKUP"
fi
sudo cp "$PROJECT_DIR/apache-config/vokso.conf" "$APACHE_VHOST_DEST"
if ! sudo apache2ctl configtest; then
    echo "[deploy] ERREUR : configuration Apache invalide après synchronisation, restauration de la version précédente."
    if [ -s "$APACHE_VHOST_BACKUP" ]; then
        sudo cp "$APACHE_VHOST_BACKUP" "$APACHE_VHOST_DEST"
    fi
    rm -f "$APACHE_VHOST_BACKUP"
    exit 1
fi
rm -f "$APACHE_VHOST_BACKUP"

echo "[deploy] Rechargement d'Apache..."
eval "$APACHE_RELOAD"

echo "[deploy] $(date -Iseconds) Déploiement terminé : $PREVIOUS_COMMIT -> $NEW_COMMIT"
