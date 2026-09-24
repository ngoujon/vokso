# Sauvegardes et restauration

## Ce qui est sauvegardé

- **Base de données** (toutes les tables : générations, utilisateurs, prompts, catégories, etc.). `scripts/backup.sh` détecte automatiquement l'environnement :
  - en dev local, dump via le conteneur Docker `mysql_db` (docker-compose) ;
  - en production, dump en local avec `mysqldump` contre la MariaDB installée en paquet système (`scripts/setup-server.sh`), avec les identifiants de `webserver/api/.env` (`DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS`).
- **Fichiers utilisateurs** : les fichiers audio uploadés (`webserver/api/storage/uploads`) et les médias générés (`webserver/public/output`).

## Sauvegarde manuelle

```bash
./scripts/backup.sh
```

Crée un dossier horodaté dans `backups/` (ex : `backups/20260831_120000/`) contenant :
- `database.sql.gz` : dump compressé de la base de données
- `files.tar.gz` : archive des fichiers utilisateurs

Le dossier `backups/` est ignoré par Git (fichier `.gitignore`) : les sauvegardes ne sont jamais commitées.

Options (variables d'environnement) :
- `BACKUP_DIR` : dossier de destination (défaut : `backups/`)
- `RETENTION_DAYS` : durée de rétention en jours, les sauvegardes plus anciennes sont supprimées automatiquement (défaut : 14 jours)

## Sauvegarde automatique (planification quotidienne)

En production, `scripts/setup-server.sh` installe automatiquement, dans la crontab de l'utilisateur `deploy`, l'exécution quotidienne de la sauvegarde :

```
0 3 * * * cd /var/www/html && ./scripts/backup.sh >> logs/backup.log 2>&1
```

(`/var/www/html` est le chemin de déploiement par défaut, `DEPLOY_PATH` dans `scripts/setup-server.sh`.) Sur un serveur déjà provisionné avant l'ajout de cette étape, relancer `scripts/setup-server.sh` ou ajouter la ligne à la main avec `sudo -u deploy crontab -e`.

Cela exécute la sauvegarde chaque nuit à 3h. Adapter la fréquence selon le volume d'activité (une sauvegarde quotidienne suffit pour ce projet au vu du faible volume de données).

## Restauration

```bash
./scripts/restore.sh backups/20260831_120000
```

⚠️ Cette opération écrase la base de données et les fichiers actuels avec le contenu de la sauvegarde choisie.

Le script :
1. Restaure la base de données à partir de `database.sql.gz` (conteneur `mysql_db` en dev local, MariaDB locale en production — même détection automatique que `backup.sh`).
2. Réextrait les fichiers utilisateurs à partir de `files.tar.gz`.

## Test de restauration effectué

Le mécanisme a été validé le 2026-08-31 : sauvegarde de la base réelle, restauration dans une base de test (`restore_test`) séparée pour vérifier que toutes les tables (`generations`, `users`, `prompts`, `categorie`, etc.) sont bien recréées, puis suppression de la base de test. L'archive de fichiers a été vérifiée (contenu attendu présent). Aucune donnée réelle n'a été modifiée pendant ce test.

Il est recommandé de refaire ce test de restauration périodiquement (par exemple tous les 3 mois) pour s'assurer que les sauvegardes restent exploitables.

## Prérequis

- **Dev local** : le conteneur Docker `mysql_db` doit être démarré (`docker compose up -d`), et le fichier `.env` à la racine du projet doit contenir `MYSQL_ROOT_PASSWORD` et `MYSQL_DATABASE`.
- **Production** : le paquet client `mariadb-client` (fournissant `mysqldump`/`mysql`, installé avec `mariadb-server` par `scripts/setup-server.sh`) doit être présent, et `webserver/api/.env` doit contenir `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
