# Checklist de Vérification : Déploiement reproductible (CI/CD ou script documenté)

Vérification de l'existence d'un pipeline CI/CD ou d'un script de déploiement
documenté (pas une procédure manuelle non écrite) permettant de redéployer le
site de façon fiable.

## Résultat

**❌ Critère NON respecté.**

- Aucun pipeline CI/CD n'est présent dans le dépôt : pas de dossier
  `.github/workflows`, pas de `.gitlab-ci.yml`, pas de `Jenkinsfile`.
- Aucun script de déploiement dédié (`deploy.sh`, `Makefile` de déploiement,
  etc.) n'existe à la racine ni dans `webserver/`.
- `webserver/package.json` ne contient que des scripts de développement front
  standards (`start`, `build`, `test`, `eject` fournis par `react-scripts`),
  aucun script de déploiement.
- `docker-compose.yml` / `docker-compose.override.yml` définissent bien
  l'infrastructure (Apache, MySQL, Mailhog) mais sont conçus pour un usage
  local de développement (mot de passe MySQL en variable d'environnement
  requise, service Mailhog explicitement commenté comme
  "à remplacer... une fois le nom de domaine choisi") : ce n'est pas une
  procédure de déploiement en production.
- `README.md` ne contient aucune section décrivant une procédure de mise en
  production ni de redéploiement.
- Aucun fichier `docs/` (`AUDIT.md`, `LOCAL_AI.md`) ne documente de procédure
  de déploiement.

## Recommandation

Pour satisfaire ce critère, il faudrait soit :
1. mettre en place un pipeline CI/CD (ex. GitHub Actions) qui build, teste et
   déploie automatiquement l'application, soit
2. à défaut, rédiger et versionner un script de déploiement documenté
   (ex. `deploy.sh`) décrivant les étapes exactes et reproductibles de mise
   en production, indépendant de toute manipulation manuelle non tracée.
