# Pile IA locale (profil `local-ai`) et Ollama Cloud

La génération de texte peut passer par OpenAI ou par Ollama Cloud (`AI_TEXT_PROVIDER=ollama`),
via son API compatible OpenAI (`https://ollama.com/v1`, clé API du compte Ollama).

L'image reste toujours sur OpenAI : Ollama (local ou cloud) ne propose aucun modèle de
génération d'image, seulement des modèles de texte (et des modèles de vision qui
*analysent* une image, pas qui en génèrent).

La transcription (Whisper) suit la même limite : Ollama Cloud n'expose pas de modèle de
transcription audio. Elle peut donc tourner soit via OpenAI, soit en local via
`docker-compose.yml` (conteneur `whisper` ci-dessous), utilisé pour la saisie vocale :
l'utilisateur enregistre sa voix au lieu de taper son sujet, le texte transcrit est
ensuite utilisé comme n'importe quelle saisie manuelle.

Le conteneur Stable Diffusion a été retiré : il ajoutait de la complexité (GPU requis)
sans bénéfice suffisant par rapport à OpenAI pour ce service, et Ollama ne peut pas le
remplacer pour cet usage.

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

## Texte via Ollama Cloud

Dans `webserver/api/.env` :

```env
AI_TEXT_PROVIDER=ollama
OLLAMA_API_KEY=<clé du compte Ollama, section API keys sur ollama.com>
OLLAMA_BASE_URL=https://ollama.com/v1
OLLAMA_TEXT_MODEL=gpt-oss:120b-cloud
```

`OLLAMA_TEXT_MODEL` accepte n'importe quel modèle "cloud" du catalogue Ollama
(`gpt-oss:20b-cloud` pour un modèle plus léger et plus rapide, `qwen3-coder:480b-cloud`,
`deepseek-v3.1:671b-cloud`, etc.).

## Points de vigilance

- Le port hôte `8000` (whisper) peut entrer en conflit avec un autre service déjà lancé
  sur la machine ; adapter le mapping `ports:` si besoin, cela n'affecte pas la
  communication interne entre conteneurs (résolution par nom de service).
- Sans le profil `local-ai` actif, `AI_SPEECH_PROVIDER` et `AI_TRANSCRIPTION_PROVIDER`
  doivent rester sur `openai`.
