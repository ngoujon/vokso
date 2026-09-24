# Checklist : Alertes en cas d'erreur ou d'indisponibilité

## Objectif
Vérifier la présence d'un système de suivi d'erreurs applicatives (Sentry ou équivalent) ou, a minima, de logs d'erreurs exploitables en cas de panne.

## Constat (mise à jour 2026-09-24)

### Suivi d'erreurs applicatif (Sentry)
- **Présent**, intégré côté API PHP et côté frontend React (commit `5cbeef7`, 2026-08-31), postérieur au constat initial ci-dessous qui est donc obsolète.
  - API : `sentry/sentry` (composer), initialisé dans `webserver/api/src/Utils/ErrorTracking.php` via `SENTRY_DSN_API` (`webserver/api/.env`). Sans DSN, le SDK reste no-op (comportement voulu en local).
  - Frontend : `@sentry/react`, initialisé dans `webserver/src/index.js` via `REACT_APP_SENTRY_DSN` (`webserver/.env`), avec un `Sentry.ErrorBoundary` autour de l'app.
- `\Sentry\captureException()` est appelé dans le catch-all global de `webserver/api/index.php` (toute exception non rattrapée dans un contrôleur y remonte) ainsi que dans `worker.php` et explicitement dans `BillingController` (Stripe).
- **Point d'attention non vérifiable depuis le dépôt local** : `SENTRY_DSN_API` et `REACT_APP_SENTRY_DSN` sont dans des fichiers `.env` non versionnés (gitignorés) et doivent être renseignés manuellement sur le serveur de prod. `scripts/setup-server.sh` ne documente que `SENTRY_DSN_API` (ligne 30) — **rien n'y mentionne `REACT_APP_SENTRY_DSN`**, ni de `.env.example` côté `webserver/` pour ce dernier. À confirmer sur le serveur que les deux DSN sont bien renseignés, sinon le suivi d'erreurs est silencieusement inactif malgré le code en place.
- **Couverture partielle côté API** : plusieurs contrôleurs/services (`CategoryController`, `ListingController` dans leurs constructeurs en cas d'échec de connexion PDO, `ContactController`, `AuthController`, `NewsletterController`, `SearchController`, `MailerService`, `PodcastGenerator`) attrapent leurs exceptions localement et appellent uniquement `Logger::get()->error(...)`, sans `\Sentry\captureException()`. Certaines (`CategoryController::__construct`, `ListingController::__construct`) font même un `die()` immédiat après le log, court-circuitant le catch-all global d'`index.php` — ces erreurs ne remontent donc jamais à Sentry, seulement au log local.

### Logs d'erreurs
- Logs applicatifs structurés (JSON) via Monolog, actif : `webserver/api/src/Utils/Logger.php`, écrit dans `webserver/logs/api.log`. Utilisé de façon cohérente dans la plupart des contrôleurs/services (voir ci-dessus).
- Logs serveur Apache/PHP toujours présents en complément (`webserver/logs/front-error_log`, `api-error_log`, `static-error_log`).
- Toujours aucun mécanisme d'alerte actif/confirmé (email, Slack, etc.) en cas de pic d'erreurs ou d'indisponibilité : Sentry peut le faire (alerting intégré configurable dans son dashboard) mais rien dans le dépôt n'indique qu'une règle d'alerte a été configurée côté projet Sentry — hors dépôt, donc non vérifiable ici.

## Conclusion
- Outil de suivi d'erreurs applicatif (Sentry) : **conforme**, sous réserve de confirmer que les DSN de prod sont bien renseignés (point non vérifiable depuis le dépôt).
- Logs d'erreurs structurés (Monolog) : **conforme et actif**, utilisés dans la quasi-totalité du code applicatif.
- Couverture Sentry incomplète sur certains chemins d'erreur (voir ci-dessus) et absence de documentation pour le DSN frontend dans `setup-server.sh`.
- Alerting actif (notification en cas de panne) : **non vérifiable depuis le dépôt** (dépend de la config du projet Sentry côté SaaS).

## Recommandations
1. Confirmer sur le serveur de prod que `SENTRY_DSN_API` (`webserver/api/.env`) et `REACT_APP_SENTRY_DSN` (`webserver/.env`) sont bien renseignés — sinon Sentry reste inactif malgré le code.
2. Ajouter `REACT_APP_SENTRY_DSN` à la documentation de `scripts/setup-server.sh` (au même titre que `SENTRY_DSN_API`) pour éviter l'oubli lors d'un futur provisioning.
3. Ajouter `\Sentry\captureException()` dans les catch locaux qui ne remontent pas au catch-all global (constructeurs de `CategoryController`/`ListingController` avec `die()`, `ContactController`, `AuthController`, `NewsletterController`, `SearchController`, `MailerService`, `PodcastGenerator`), ou les laisser remonter l'exception plutôt que de l'avaler.
4. Vérifier/configurer une règle d'alerte (email/Slack) dans le dashboard Sentry pour être notifié en cas de pic d'erreurs ou d'indisponibilité.
