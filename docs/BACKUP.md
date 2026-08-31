# Sauvegardes et restauration

## Ce qui est sauvegardé

- **Base de données** MySQL (toutes les tables : générations, utilisateurs, prompts, catégories, etc.), via un dump du conteneur Docker `mysql_db`.
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

Ajouter à la crontab (`crontab -e`) :

```
0 3 * * * cd /Volumes/data/workspace-dev/apps/qwai-pod && ./scripts/backup.sh >> logs/backup.log 2>&1
```

Cela exécute la sauvegarde chaque nuit à 3h. Adapter la fréquence selon le volume d'activité (une sauvegarde quotidienne suffit pour ce projet au vu du faible volume de données).

## Restauration

```bash
./scripts/restore.sh backups/20260831_120000
```

⚠️ Cette opération écrase la base de données et les fichiers actuels avec le contenu de la sauvegarde choisie.

Le script :
1. Restaure la base de données dans le conteneur `mysql_db` à partir de `database.sql.gz`.
2. Réextrait les fichiers utilisateurs à partir de `files.tar.gz`.

## Test de restauration effectué

Le mécanisme a été validé le 2026-08-31 : sauvegarde de la base réelle, restauration dans une base de test (`restore_test`) séparée pour vérifier que toutes les tables (`generations`, `users`, `prompts`, `categorie`, etc.) sont bien recréées, puis suppression de la base de test. L'archive de fichiers a été vérifiée (contenu attendu présent). Aucune donnée réelle n'a été modifiée pendant ce test.

Il est recommandé de refaire ce test de restauration périodiquement (par exemple tous les 3 mois) pour s'assurer que les sauvegardes restent exploitables.

## Prérequis

- Le conteneur Docker `mysql_db` doit être démarré (`docker compose up -d`).
- Le fichier `.env` à la racine du projet doit contenir `MYSQL_ROOT_PASSWORD` et `MYSQL_DATABASE`.
