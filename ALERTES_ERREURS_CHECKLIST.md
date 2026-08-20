# Checklist : Alertes en cas d'erreur ou d'indisponibilité

## Objectif
Vérifier la présence d'un système de suivi d'erreurs applicatives (Sentry ou équivalent) ou, a minima, de logs d'erreurs exploitables en cas de panne.

## Constat

### Suivi d'erreurs applicatif (Sentry ou équivalent)
- **Absent.** Aucune dépendance Sentry, Bugsnag, Rollbar ou Datadog n'est présente (`webserver/package.json`, `webserver/api/composer.json`).
- Aucune variable d'environnement liée à un outil de suivi d'erreurs (`.env*`).
- Aucune alerte automatique (email, Slack, etc.) n'est déclenchée en cas d'erreur ou d'indisponibilité.

### Logs d'erreurs
- Des logs serveur existent (`webserver/logs/`) : `front-error_log`, `api-error_log`, `static-error_log` (Apache/PHP).
- `front-error_log` contient des erreurs exploitables : erreurs SQL (colonnes manquantes, conflits de collation), erreurs de configuration serveur (répertoire sans index).
- `api-error_log` et `static-error_log` sont vides (pas d'erreur backend API capturée à ce jour, ou logs non alimentés).
- La dépendance **Monolog** est présente dans `webserver/api/vendor/` (via composer) mais n'est **pas utilisée** dans le code applicatif (`webserver/api/src`) : aucun logger n'est instancié ou appelé dans les contrôleurs/services.
- Les logs sont uniquement locaux (fichiers texte sur le serveur), sans centralisation, rotation visible, ni alerting.

## Conclusion
- Pas d'outil de suivi d'erreurs applicatif (Sentry ou équivalent) : **non conforme**.
- Logs d'erreurs serveur présents et partiellement exploitables (Apache/PHP), mais :
  - non utilisés de façon structurée côté code applicatif (Monolog installé mais inactif),
  - pas de mécanisme d'alerte (aucune notification en cas d'erreur ou d'indisponibilité),
  - pas de centralisation/rétention garantie.

## Recommandations
1. Intégrer un outil de suivi d'erreurs (Sentry, ou équivalent) côté API PHP et côté frontend pour capter les exceptions en temps réel avec alerting.
2. Activer et utiliser Monolog (déjà présent en dépendance) dans les contrôleurs/services pour logger les erreurs applicatives de façon structurée.
3. Mettre en place une alerte (email/Slack/webhook) en cas d'indisponibilité ou de pic d'erreurs.
