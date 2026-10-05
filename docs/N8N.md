# API n8n : créer un épisode depuis un workflow

Réservée au n8n hébergé sur le même serveur que Vokso
(`https://vps-3962b7dc.vps.ovh.net/n8n/`, IP 51.254.211.144). Deux verrous,
tous deux obligatoires (`EnsureN8nCaller`) :

- l'adresse d'origine doit figurer dans `N8N_ALLOWED_IPS` (par défaut : IP
  publiques du serveur, boucle locale et réseaux Docker 172.16.0.0/12) ;
- l'en-tête `Authorization: Bearer <jeton>` doit porter le jeton secret, dont
  seule l'empreinte SHA-256 est versionnée (`config/vokso.php`). Pour changer
  de jeton : `openssl rand -hex 32`, puis définir `N8N_API_TOKEN_SHA256` (empreinte
  `printf %s "$JETON" | sha256sum`) dans le `.env` de l'API.

Toute requête refusée reçoit `403 {"error": "Accès refusé"}`.

## 1. Lancer la création

```
POST https://vokso.fr/api/n8n/podcasts
Authorization: Bearer <jeton>
Content-Type: application/json

{"sujet": "Les anneaux de Saturne", "duree": 10, "niveau": 4}
```

`duree` (minutes, 1 à 15, 5 par défaut) et `niveau` (1 Survol, 2 Découverte,
3 Approfondi, 4 Avancé, 5 Expert ; 3 par défaut) sont facultatifs.

Réponse `202` :

```json
{
  "job_id": "job_…",
  "status": "pending",
  "sujet": "Les anneaux de Saturne",
  "status_url": "https://vokso.fr/api/n8n/podcasts/job_…"
}
```

Erreurs : `422` sujet vide ou de plus de 300 caractères, durée ou niveau
hors bornes, `429` plafond
quotidien global atteint (`GENERATION_GLOBAL_DAILY_LIMIT`). Les plafonds par
IP de la création publique ne s'appliquent pas.

## 2. Suivre jusqu'à la publication

```
GET https://vokso.fr/api/n8n/podcasts/job_…
Authorization: Bearer <jeton>
```

`status` passe de `pending`/`processing` à `done` (ou `error`), en général en
une à deux minutes. Une fois `done: true`, `episode` contient le lien à
envoyer :

```json
{
  "status": "done",
  "done": true,
  "error": null,
  "episode": {
    "id": "gen_…",
    "title": "Saturne et ses anneaux",
    "category": "Astronomie",
    "url": "https://vokso.fr/podcast/gen_…-saturne-et-ses-anneaux",
    "image_url": "https://vokso.fr/static/images/…webp",
    "audio_url": "https://vokso.fr/static/audios/…mp3"
  }
}
```

## Workflow n8n suggéré

1. Déclencheur (manuel, planifié, message Telegram…).
2. **HTTP Request** POST `/api/n8n/podcasts` (authentification « Header Auth » :
   `Authorization` = `Bearer <jeton>`), corps `{"sujet": "…"}`.
3. **Wait** 30 s → **HTTP Request** GET sur `{{$json.status_url}}` →
   **IF** `done` est vrai, sinon **IF** `status` = `error`, sinon retour au Wait.
4. **Telegram** : « Épisode prêt : {{$json.episode.title}} — {{$json.episode.url}} ».

Si n8n tourne dans Docker et appelle `https://vokso.fr`, la requête ressort
par l'IP publique du serveur ou par le réseau Docker : les deux sont autorisés
par défaut.
