# Déploiement

Remplace la procédure manuelle non tracée par un pipeline CI/CD (GitHub
Actions) et un script de déploiement versionné, utilisable seul en attendant
que le serveur de production soit choisi (voir
`HEBERGEMENT_DIMENSIONNEMENT_CHECKLIST.md` et `DNS_EMAIL_CHECKLIST.md` : le
nom de domaine et l'hébergeur de production ne sont pas encore fixés à ce
jour).

## 1. Intégration continue (CI)

`.github/workflows/ci.yml` — actif dès maintenant, sans configuration
supplémentaire. Sur chaque push et pull request :

- **Frontend** : installe les dépendances (`webserver/`), build
  (`react-scripts build`), lance les tests (`react-scripts test`).
- **Backend** : installe les dépendances Composer (`webserver/api/`), lance
  les tests PHPUnit.

## 2. Déploiement continu (CD)

`.github/workflows/deploy.yml` se déclenche automatiquement une fois que le
workflow CI a réussi sur la branche `production`. Il se connecte en SSH au
serveur et y exécute `scripts/deploy.sh`.

**Tant que les secrets ci-dessous ne sont pas configurés, ce workflow ne fait
rien** (il affiche une notice et s'arrête sans erreur) : il est prêt à être
activé dès que l'hébergement de production sera choisi, sans modification de
code.

### Activation, une fois le serveur de production choisi

1. Générer une paire de clés SSH dédiée au déploiement (pas la clé
   personnelle d'un développeur) et autoriser la clé publique sur le compte
   utilisé pour déployer sur le serveur (`~/.ssh/authorized_keys`).
2. Sur le serveur, cloner le dépôt une première fois dans le répertoire qui
   servira de racine de déploiement, sur la branche `production`.
3. Dans les paramètres GitHub du dépôt (*Settings → Secrets and variables →
   Actions*), ajouter :
   - `DEPLOY_SSH_HOST` : adresse du serveur.
   - `DEPLOY_SSH_USER` : utilisateur SSH de déploiement.
   - `DEPLOY_SSH_KEY` : clé privée générée à l'étape 1.
   - `DEPLOY_PATH` : chemin absolu du dépôt cloné sur le serveur (étape 2).
4. Vérifier que l'utilisateur de déploiement peut recharger Apache sans mot
   de passe (`sudo systemctl reload apache2` en `NOPASSWD` dans `sudoers`,
   ou adapter `APACHE_RELOAD` — voir ci-dessous) et exécuter `composer` /
   `npm` (dépendances installées sur le serveur).
5. Merger sur `production` : le déploiement se déclenche automatiquement
   après un CI vert.

## 3. Script de déploiement (`scripts/deploy.sh`)

Utilisable indépendamment du pipeline CI/CD, en SSH sur le serveur, depuis la
racine du dépôt cloné :

```bash
./scripts/deploy.sh
```

Étapes effectuées :
1. Affiche le commit actuellement déployé (pour rollback éventuel).
2. `git fetch` + `git reset --hard origin/production`.
3. `composer install --no-dev` (backend).
4. `npm ci && npm run build` (frontend).
5. Recharge Apache.

Variables d'environnement optionnelles :
- `DEPLOY_BRANCH` (défaut : `production`)
- `APACHE_RELOAD` (défaut : `sudo systemctl reload apache2`)

### Rollback

Le script affiche le commit précédent avant de mettre à jour. En cas de
problème après un déploiement :

```bash
git checkout <commit-affiché>
./scripts/deploy.sh
```

### Hors périmètre : migrations SQL

Ce script ne rejoue pas les fichiers `SQL/*.sql` automatiquement : plusieurs
contiennent des `ALTER TABLE`/`INSERT`/`UPDATE` non idempotents (rejouables
une seule fois sans casser la base). Elles restent appliquées manuellement et
consciemment par la personne qui déploie, comme c'est le cas aujourd'hui.
Automatiser ce point nécessiterait un outil de suivi de migrations dédié
(table de versions appliquées), hors périmètre de cette mise en place de
pipeline de déploiement.
