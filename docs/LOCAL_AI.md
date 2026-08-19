# Pile IA locale (profil `local-ai`)

Le texte et l'image passent toujours par OpenAI. Seules la synthèse vocale (TTS) et la
transcription (Whisper) peuvent tourner en local via `docker-compose.yml`. Whisper sert
à la saisie vocale : l'utilisateur enregistre sa voix au lieu de taper son sujet, le
texte transcrit est ensuite utilisé comme n'importe quelle saisie manuelle.

Les conteneurs Ollama et Stable Diffusion ont été retirés : ils ajoutaient de la
complexité (GPU requis pour Stable Diffusion, modèles à tirer manuellement pour Ollama)
sans bénéfice suffisant par rapport à OpenAI pour ce service.

## Démarrage

```bash
docker compose --profile local-ai up -d
```

Puis dans `webserver/api/.env` :

```env
AI_SPEECH_PROVIDER=local
AI_TRANSCRIPTION_PROVIDER=whisper
```

## Services et modèles recommandés

| Service | Image | Port interne | Modèle recommandé | Remarque |
|---|---|---|---|---|
| `tts` | `ghcr.io/remsky/kokoro-fastapi-cpu:latest` | 8880 (`/v1/audio/speech`) | Kokoro, voix `ff_siwis` (français) | Variante CPU testée avec succès ; une variante `-gpu` existe pour accélérer |
| `whisper` | `fedirz/faster-whisper-server:latest-cpu` | 8000 (`/v1/audio/transcriptions`) | `Systran/faster-whisper-medium` (`-small` pour un poste modeste) | Le modèle est téléchargé automatiquement au premier appel, mis en cache dans le volume `whisper_data`. Utilisé pour la saisie vocale côté front |

Les valeurs par défaut ci-dessus correspondent à `webserver/api/.env.example`.

## Points de vigilance

- Le port hôte `8000` (whisper) peut entrer en conflit avec un autre service déjà lancé
  sur la machine ; adapter le mapping `ports:` si besoin, cela n'affecte pas la
  communication interne entre conteneurs (résolution par nom de service).
- Sans le profil `local-ai` actif, `AI_SPEECH_PROVIDER` et `AI_TRANSCRIPTION_PROVIDER`
  doivent rester sur `openai`.
