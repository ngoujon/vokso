# Audit complet — QwaiPod (18/08/2026)

Périmètre : API PHP (`webserver/api`), front React (`webserver/src`), schéma SQL, déploiement Docker.

## 1. Synthèse

Le projet est fonctionnel et volontairement simple : un contrôleur orchestre trois appels IA
(texte, image, voix) puis stocke le résultat sur disque et en base. Les faiblesses principales
n'étaient pas fonctionnelles mais structurelles : routage trop permissif, fuites d'informations
techniques dans les réponses HTTP, absence de tout garde-fou sur l'endpoint le plus coûteux, et
couplage direct au SDK OpenAI empêchant toute alternative locale.

## 2. Corrigé dans cette revue

| Sujet | Constat | Correction |
|---|---|---|
| Routage | `index.php` instanciait `ucfirst($segment).'Controller'` et appelait une méthode dont le nom venait aussi de l'URL : toute classe/méthode publique du projet était atteignable | Table de routage explicite en liste blanche |
| Fuite d'informations | `display_errors=1` en dur, messages `PDOException` et erreurs OpenAI renvoyés au client (hôte, utilisateur, clé partielle) | Détail envoyé dans les logs ; réponse générique sauf `APP_DEBUG=true` |
| Injection de prompt | L'entrée était nettoyée pour la génération de texte mais l'entrée brute servait à la catégorisation et à l'enregistrement | Une seule valeur nettoyée pour tous les usages, longueur du sujet plafonnée |
| Écriture des fichiers | `file_put_contents` sans création ni contrôle du dossier `public/output/*` : échec silencieux, base incohérente | Création du dossier et échec explicite |
| CORS | `PingController` renvoyait `Access-Control-Allow-Origin: *`, contournant la liste blanche | En-têtes laissés au seul `CorsHandler` |
| Téléchargement image | `file_get_contents($url)` sans timeout, dépendant d'`allow_url_fopen` | Client HTTP Guzzle avec timeout |
| Lecteur audio | `formatTime(undefined)` affichait `NaN:NaN` avant chargement des métadonnées | Valeur de repli `0:00` |
| Base de données | Connexion sans `charset` : risque de mojibake sur les accents | `charset=utf8mb4` |
| Absence de protection sur `POST /generation` | Endpoint anonyme déclenchant trois appels IA payants et plusieurs minutes de calcul, sans quota ni limite de débit — risque le plus élevé du projet (facture et déni de service) | Quota par IP et par route (`rate_limit` en base, fenêtre glissante, 5 requêtes/heure par défaut, réglable via `RATE_LIMIT_MAX_REQUESTS`/`RATE_LIMIT_WINDOW_SECONDS`), appliqué à `generation` et `generation-audio`, couvert par des tests PHPUnit |

## 3. Restant à traiter (voir tâches créées)

1. **Identifiants en clair dans le dépôt** — `SQL/20250329120000.sql` versionne un
   `CREATE USER … IDENTIFIED BY` avec mot de passe, doublé d'un `GRANT ALL PRIVILEGES ON *.*
   WITH GRANT OPTION`. Mot de passe à révoquer et privilèges à restreindre à la seule base.
2. **`docker-compose.yml`** — mots de passe MySQL en dur, phpMyAdmin exposé sur `:8081` sans
   restriction, installation des extensions PHP à chaque démarrage (préférer un `Dockerfile`).
3. **Couverture de tests inégale** — 24 tests PHPUnit couvrent le système de rate-limiting et les contrôleurs critiques (API PHP), mais la couverture front (React) reste absente ; toute régression côté interface passe inaperçue.
4. **Génération synchrone** — la requête HTTP reste ouverte pendant toute la chaîne
   (plusieurs minutes, davantage en local). Une file d'attente avec suivi d'état est nécessaire.
5. **Incohérences de schéma** — la migration `20250111` écrit dans `prompts.description` alors
   que le code lit `prompt.content` ; les migrations ne sont pas idempotentes et aucune n'est
   rejouable automatiquement.
6. **Front** — recherche déclenchée par le même champ que la génération (comportement ambigu),
   debounce de 2 s, `lastFetchRef` qui peut annuler une requête légitime, manipulation du DOM par
   `document.getElementById` au lieu de refs React, `key={index}` sur la liste.
7. **`webserver/index.php`** affiche l'adresse IP du visiteur : reliquat de test à supprimer.
8. **Pas de limitation de débit sur l'authentification et le formulaire de contact** — `RateLimiter`
   (base de données, fenêtre glissante) n'est appliqué qu'à `POST /generation` et
   `/generation-audio`. `AuthController::register()` et `AuthController::login()` (bruteforce de
   mots de passe, création de comptes en masse) ainsi que `NewsletterController::subscribe()`
   (inscriptions en masse) restent sans quota, sans captcha et sans honeypot. Étendre le même
   `RateLimiter` à ces trois routes est le correctif le plus simple.

## 4. Faisabilité « tout Ollama »

**Réponse courte : non, pas avec Ollama seul — mais oui pour du 100 % local.**

Ollama n'expose que des modèles de langage (`/api/chat`, `/api/generate`, `/api/embed` et un
sous-ensemble compatible OpenAI). Il ne propose ni génération d'image, ni synthèse vocale, ni
transcription. Certains modèles multimodaux (llava, gemma3, qwen-vl) *lisent* une image, ils
n'en produisent pas.

| Besoin | Ollama | Solution locale retenue |
|---|---|---|
| Texte du podcast | ✅ `llama3.1:8b`, `mistral`, `qwen2.5` | Ollama |
| Catégorisation | ✅ | Ollama |
| Image | ❌ | Stable Diffusion / AUTOMATIC1111 (`/sdapi/v1/txt2img`) |
| Voix | ❌ | Serveur compatible OpenAI : Kokoro-FastAPI, openedai-speech (Piper), Coqui |
| Transcription | ❌ | faster-whisper-server ou whisper.cpp (`/v1/audio/transcriptions`) |

> Ces capacités sont celles connues à la date de rédaction ; l'accès réseau n'étant pas
> disponible pendant l'audit, il est recommandé de reconfirmer sur la documentation Ollama
> avant tout engagement.

**Points de vigilance :** un modèle 8B en français produit un texte sensiblement moins riche que
GPT‑4 (prévoir 12–14B et des prompts retravaillés) ; la qualité et la cohérence de style des
images locales demandent un choix de modèle et un LoRA ; les temps de génération sur CPU sont
prohibitifs, une carte graphique (≥ 12 Go VRAM) est en pratique nécessaire.

## 5. Architecture mise en place

Le contrôleur ne connaît plus aucun fournisseur : il demande une capacité à
`AiProviderFactory`, qui lit l'environnement et instancie l'implémentation voulue.

```
GenerationController
  └── AiProviderFactory
        ├── texte          → OpenAiProvider | OllamaProvider
        ├── image          → OpenAiProvider | StableDiffusionProvider
        ├── voix           → OpenAiProvider | OpenAiCompatibleSpeechProvider
        └── transcription  → OpenAiProvider | WhisperTranscriptionProvider
```

Chaque capacité se règle indépendamment (`AI_TEXT_PROVIDER`, `AI_IMAGE_PROVIDER`,
`AI_SPEECH_PROVIDER`, `AI_TRANSCRIPTION_PROVIDER`), ce qui permet une migration progressive.
Le modèle de configuration complet est dans `webserver/api/.env.example`, et un service
`ollama` est disponible dans `docker-compose.yml` sous le profil `local-ai` :

```bash
docker compose --profile local-ai up -d
docker compose exec ollama ollama pull llama3.1:8b
# puis dans webserver/api/.env : AI_TEXT_PROVIDER=ollama
```

Le comportement par défaut reste OpenAI : aucune installation existante n'est impactée.

## 6. Site de présentation

`site/index.html` : page statique autonome (aucune dépendance, aucun build) présentant le
concept, le fonctionnement, la comparaison cloud/local et la pile technique. Rendu vérifié au
navigateur en 1280×900 et 390×844, sans débordement horizontal.
