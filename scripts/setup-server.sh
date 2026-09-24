#!/usr/bin/env bash
# Provisionne un serveur Ubuntu FRAIS pour héberger Vokso (vitrine + app React
# + API PHP) et le prépare à recevoir les déploiements automatiques de
# scripts/deploy.sh via .github/workflows/deploy.yml.
#
# Usage : à exécuter UNE SEULE FOIS, en root, sur le serveur cible fraîchement
# installé (ex. via `ssh root@serveur 'bash -s' < scripts/setup-server.sh`,
# ou copié puis lancé sur place avec `sudo ./setup-server.sh`).
#
# Ce script :
#   1. Installe la pile (Apache, PHP, MariaDB, Node, Composer, git, certbot).
#   2. Crée un utilisateur dédié "deploy" (jamais la clé perso d'un dev, voir
#      docs/DEPLOIEMENT.md) avec un accès SSH par clé uniquement.
#   3. Autorise ce seul utilisateur à recharger Apache sans mot de passe
#      (sudoers NOPASSWD limité à cette commande précise).
#   4. Clone le dépôt sur la branche production dans /var/www/html (chemin
#      attendu par apache-config/vokso.conf : DocumentRoot .../site,
#      Alias /app et /api vers .../webapp -> symlink vers webserver/).
#   5. Installe les dépendances et build une première fois (ensuite,
#      scripts/deploy.sh s'en charge à chaque déploiement).
#   6. Crée la base de données et rejoue SQL/*.sql dans l'ordre (base neuve
#      uniquement : ne pas relancer sur un serveur qui a déjà des données).
#   7. Installe le vhost Apache versionné (apache-config/vokso.conf).
#   8. Configure le pare-feu (UFW : SSH + HTTP/HTTPS uniquement).
#   9. Installe une crontab quotidienne (3h) exécutant scripts/backup.sh
#      (voir docs/BACKUP.md), journalisée dans logs/backup.log.
#
# Hors périmètre, à faire à la main après ce script :
#   - Pointer le DNS de vokso.fr / www.vokso.fr vers l'IP du serveur, puis
#     lancer `certbot --apache -d vokso.fr -d www.vokso.fr` (HTTPS).
#   - Remplir les secrets applicatifs dans /var/www/html/webserver/api/.env
#     (MISTRAL_API_KEY, STRIPE_*, SMTP_*, SENTRY_DSN_API) : ce script laisse
#     ces valeurs vides, ce ne sont pas des informations d'infrastructure.
#   - Une fois la clé de déploiement confirmée fonctionnelle, désactiver
#     l'authentification par mot de passe SSH (PasswordAuthentication no)
#     et changer/désactiver le mot de passe du compte utilisé pour ce script.
#
# Variables d'environnement optionnelles :
#   REPO_URL        Défaut : https://github.com/ngoujon/vokso.git
#   DEPLOY_BRANCH   Défaut : production
#   DEPLOY_USER     Défaut : deploy
#   DEPLOY_PATH     Défaut : /var/www/html
#   DOMAIN          Défaut : vokso.fr
#   DB_NAME/DB_USER/DB_PASS  Défaut générés/"generation_db"/"vokso"/aléatoire

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Ce script doit être exécuté en root (sudo)." >&2
  exit 1
fi

REPO_URL="${REPO_URL:-https://github.com/ngoujon/vokso.git}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-production}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
DEPLOY_PATH="${DEPLOY_PATH:-/var/www/html}"
DOMAIN="${DOMAIN:-vokso.fr}"
DB_NAME="${DB_NAME:-generation_db}"
DB_USER="${DB_USER:-vokso}"
DB_PASS="${DB_PASS:-$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 32)}"

echo "[setup] $(date -Iseconds) Démarrage du provisioning ($DOMAIN, branche $DEPLOY_BRANCH)"

echo "[setup] Mise à jour du système..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y

echo "[setup] Installation de la pile applicative..."
apt-get install -y \
  apache2 \
  php php-cli libapache2-mod-php php-mysql php-mbstring php-xml php-curl php-zip php-gd php-intl \
  mariadb-server \
  composer \
  git curl unzip openssl \
  certbot python3-certbot-apache \
  ufw cron

if ! command -v node >/dev/null 2>&1 || [ "$(node -v | grep -oE '^v[0-9]+' | tr -d v)" -lt 20 ]; then
  echo "[setup] Installation de Node.js 20 (NodeSource)..."
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
fi

echo "[setup] Activation des modules Apache requis par apache-config/vokso.conf..."
a2enmod deflate expires headers rewrite ssl >/dev/null

echo "[setup] Création de l'utilisateur de déploiement '$DEPLOY_USER'..."
if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
  adduser --disabled-password --gecos "" "$DEPLOY_USER"
fi
usermod -aG www-data "$DEPLOY_USER"
install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh"
touch "/home/$DEPLOY_USER/.ssh/authorized_keys"
chown "$DEPLOY_USER:$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh/authorized_keys"
chmod 600 "/home/$DEPLOY_USER/.ssh/authorized_keys"

echo "[setup] Autorisation NOPASSWD limitée au rechargement d'Apache pour '$DEPLOY_USER'..."
cat > "/etc/sudoers.d/$DEPLOY_USER-apache-reload" <<EOF
$DEPLOY_USER ALL=(root) NOPASSWD: /bin/systemctl reload apache2
EOF
chmod 440 "/etc/sudoers.d/$DEPLOY_USER-apache-reload"
visudo -cf "/etc/sudoers.d/$DEPLOY_USER-apache-reload"

echo "[setup] Clonage du dépôt dans $DEPLOY_PATH (branche $DEPLOY_BRANCH)..."
if [ -d "$DEPLOY_PATH/.git" ]; then
  echo "[setup] $DEPLOY_PATH est déjà un dépôt git, on saute le clonage."
else
  mkdir -p "$DEPLOY_PATH"
  find "$DEPLOY_PATH" -mindepth 1 -delete
  sudo -u "$DEPLOY_USER" git clone --branch "$DEPLOY_BRANCH" "$REPO_URL" "$DEPLOY_PATH"
fi

echo "[setup] Lien symbolique webapp -> webserver (attendu par apache-config/vokso.conf)..."
[ -L "$DEPLOY_PATH/webapp" ] || ln -s "$DEPLOY_PATH/webserver" "$DEPLOY_PATH/webapp"

echo "[setup] Installation des dépendances et premier build..."
sudo -u "$DEPLOY_USER" composer install --no-dev --optimize-autoloader --prefer-dist --working-dir="$DEPLOY_PATH/webserver/api"
sudo -u "$DEPLOY_USER" npm ci --prefix "$DEPLOY_PATH/webserver"
sudo -u "$DEPLOY_USER" npm run build --prefix "$DEPLOY_PATH/webserver"

echo "[setup] Préparation des répertoires écrits par l'API (uploads, podcasts générés)..."
mkdir -p "$DEPLOY_PATH/webserver/api/storage/uploads" "$DEPLOY_PATH/webserver/public/output" "$DEPLOY_PATH/webserver/logs"
chown -R "$DEPLOY_USER:www-data" "$DEPLOY_PATH"
chmod -R g+rwX "$DEPLOY_PATH"

echo "[setup] Base de données ($DB_NAME)..."
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "[setup] Rejeu des migrations SQL/*.sql dans l'ordre (base neuve uniquement)..."
for f in "$DEPLOY_PATH"/SQL/*.sql; do
  echo "[setup]   -> $(basename "$f")"
  mysql "$DB_NAME" < "$f"
done

echo "[setup] Fichier webserver/api/.env (secrets applicatifs à compléter à la main)..."
if [ ! -f "$DEPLOY_PATH/webserver/api/.env" ]; then
  sudo -u "$DEPLOY_USER" cp "$DEPLOY_PATH/webserver/api/.env.example" "$DEPLOY_PATH/webserver/api/.env"
  sed -i \
    -e "s/^DB_HOST=.*/DB_HOST=localhost/" \
    -e "s/^DB_NAME=.*/DB_NAME=$DB_NAME/" \
    -e "s/^DB_USER=.*/DB_USER=$DB_USER/" \
    -e "s/^DB_PASS=.*/DB_PASS=$DB_PASS/" \
    -e "s#^ALLOWED_ORIGINS=.*#ALLOWED_ORIGINS=https://$DOMAIN#" \
    -e "s#^FRONTEND_URL=.*#FRONTEND_URL=https://$DOMAIN#" \
    -e "s/^APP_DEBUG=.*/APP_DEBUG=false/" \
    "$DEPLOY_PATH/webserver/api/.env"
fi

echo "[setup] Installation du vhost Apache versionné..."
cp "$DEPLOY_PATH/apache-config/vokso.conf" /etc/apache2/sites-available/vokso.conf
a2dissite 000-default >/dev/null 2>&1 || true
a2ensite vokso >/dev/null
systemctl reload apache2

echo "[setup] Pare-feu (UFW)..."
ufw allow OpenSSH >/dev/null
ufw allow 'Apache Full' >/dev/null
ufw --force enable >/dev/null

echo "[setup] Sauvegarde quotidienne (scripts/backup.sh) via crontab de '$DEPLOY_USER'..."
mkdir -p "$DEPLOY_PATH/logs"
chown "$DEPLOY_USER:www-data" "$DEPLOY_PATH/logs"
CRON_LINE="0 3 * * * cd $DEPLOY_PATH && ./scripts/backup.sh >> logs/backup.log 2>&1"
( sudo -u "$DEPLOY_USER" crontab -l 2>/dev/null | grep -vF "scripts/backup.sh"; echo "$CRON_LINE" ) | sudo -u "$DEPLOY_USER" crontab -

echo "[setup] $(date -Iseconds) Provisioning terminé."
echo "[setup] Mot de passe généré pour l'utilisateur MySQL '$DB_USER' : $DB_PASS"
echo "[setup] -> déjà reporté dans $DEPLOY_PATH/webserver/api/.env (DB_PASS)."
echo "[setup] Reste à faire à la main :"
echo "[setup]   - Compléter MISTRAL_API_KEY / STRIPE_* / SMTP_* / SENTRY_DSN_API dans webserver/api/.env"
echo "[setup]   - Pointer le DNS de $DOMAIN vers ce serveur puis lancer :"
echo "[setup]       certbot --apache -d $DOMAIN -d www.$DOMAIN"
echo "[setup]   - Une fois la clé de déploiement validée, désactiver l'auth par mot de passe SSH"
echo "[setup]     et changer le mot de passe initial du compte utilisé pour ce provisioning."
