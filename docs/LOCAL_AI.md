# Pile IA locale (profil `local-ai`)

Ce document complète `docs/AUDIT.md` (section 4-5) : il décrit les services ajoutés au
`docker-compose.yml`, la façon de les démarrer, et les modèles recommandés pour chaque
capacité. La chaîne texte → image → voix → transcription a été testée en local (voir
« Résultat des tests » ci-dessous) ; seule la génération d'image n'a pas pu être vérifiée
faute de GPU disponible dans l'environnement de test.

## Démarrage

```bash
docker compose --profile local-ai up -d
docker compose exec ollama ollama pull llama3.1:8b
```

Puis dans `webserver/api/.env` :

```env
AI_TEXT_PROVIDER=ollama
AI_IMAGE_PROVIDER=stablediffusion
AI_SPEECH_PROVIDER=local
AI_TRANSCRIPTION_PROVIDER=whisper
```

## Services et modèles recommandés

| Service | Image | Port interne | Modèle recommandé | Remarque |
|---|---|---|---|---|
| `ollama` | `ollama/ollama:latest` | 11434 | `llama3.1:8b` (`qwen2.5:14b` si VRAM suffisante, meilleur en français) | À tirer manuellement après le démarrage |
| `stable-diffusion` | `universonic/stable-diffusion-webui:latest` | 7860 (`/sdapi/v1/txt2img`) | SDXL Base 1.0 ou un checkpoint SD 1.5 orienté illustration | Image `amd64`/CUDA : nécessite une carte NVIDIA + `nvidia-container-toolkit` sur l'hôte. Déposer le fichier `.safetensors` dans le volume `sd_data` (monté sur `models/Stable-diffusion`) avant le premier appel |
| `tts` | `ghcr.io/remsky/kokoro-fastapi-cpu:latest` | 8880 (`/v1/audio/speech`) | Kokoro, voix `ff_siwis` (français) | Variante CPU testée avec succès ; une variante `-gpu` existe pour accélérer |
| `whisper` | `fedirz/faster-whisper-server:latest-cpu` | 8000 (`/v1/audio/transcriptions`) | `Systran/faster-whisper-medium` (`-small` pour un poste modeste) | Le modèle est téléchargé automatiquement au premier appel, mis en cache dans le volume `whisper_data` |

Les valeurs par défaut ci-dessus correspondent à `webserver/api/.env.example`.

## Résultat des tests (2026-08-18)

Testé directement avec Docker, sans passer par l'API PHP :

- `tts` (Kokoro-FastAPI, CPU) : démarre, charge le modèle, répond `200` sur
  `POST /v1/audio/speech` avec un MP3 valide (~32 Ko pour une phrase courte).
- `whisper` (faster-whisper-server, CPU) : démarre, répond `200` sur `GET /v1/models`,
  et transcrit correctement le MP3 généré par `tts` ci-dessus (« Bonjour, ceci est un
  test. » → texte identique). La boucle texte → voix → transcription est donc validée
  de bout en bout.
- `stable-diffusion` : l'image se télécharge et est valide (confirmé via `docker pull
  --platform linux/amd64`), mais elle est construite sur CUDA 12.1 et ne peut pas être
  démarrée sans GPU NVIDIA. Non testée fonctionnellement dans cet environnement (Mac
  Apple Silicon, sans GPU dédié) ; à revalider sur la machine cible avant mise en
  production.
- `ollama` : service inchangé par rapport à l'existant, non re-testé ici.

## Points de vigilance

- Le port hôte `8000` (whisper) peut entrer en conflit avec un autre service déjà lancé
  sur la machine ; adapter le mapping `ports:` si besoin, cela n'affecte pas la
  communication interne entre conteneurs (résolution par nom de service).
- `stable-diffusion` est le seul service de cette liste qui exige un GPU en pratique
  (cf. `docs/AUDIT.md` §4) ; sur une machine sans GPU NVIDIA, garder
  `AI_IMAGE_PROVIDER=openai`.
