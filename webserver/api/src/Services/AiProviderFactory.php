<?php

namespace App\Services;

use Exception;

/**
 * Construit les fournisseurs d'IA à partir des variables d'environnement.
 *
 * OpenAI n'est plus utilisé (décision produit) : le texte passe par Mistral
 * ou Ollama Cloud (API compatible OpenAI), la transcription par Mistral
 * (Voxtral) ou reste locale (Whisper self-hosted), la synthèse vocale reste
 * locale (TTS) ou passe par Mistral, et l'image passe par Mistral (agent
 * doté de l'outil "image_generation" + API Conversations, pas de route REST
 * unique comme chez OpenAI, voir MistralImageProvider). "openai" n'est donc
 * plus une valeur valide pour ces réglages : un réglage manquant tombe sur
 * Mistral par défaut (jamais sur un fournisseur nécessitant une clé absente).
 *
 *   AI_TEXT_PROVIDER=mistral|ollama
 *   AI_IMAGE_PROVIDER=mistral
 *   AI_SPEECH_PROVIDER=mistral|local
 *   AI_TRANSCRIPTION_PROVIDER=mistral|whisper
 */
class AiProviderFactory
{
    private array $env;
    private array $instances = [];

    public function __construct(array $env)
    {
        $this->env = $env;
    }

    public function textGenerator(): TextGeneratorInterface
    {
        return $this->instances['text'] ??= match ($this->choice('AI_TEXT_PROVIDER')) {
            'mistral' => new OpenAiProvider($this->mistralConfig()),
            'ollama' => new OpenAiProvider($this->ollamaConfig()),
            default => throw new Exception('AI_TEXT_PROVIDER invalide (attendu : mistral ou ollama).'),
        };
    }

    /**
     * Générateur de texte utilisé pour la narration principale du podcast
     * uniquement (pas le titre/la catégorie, qui n'ont pas besoin de
     * recherche web) : équipé de l'outil "web_search" côté Mistral, pour que
     * le modèle puisse vérifier des faits récents quand il le juge utile.
     * Ollama Cloud n'expose pas ce connecteur : on retombe alors sur le
     * générateur de texte standard, sans web search.
     */
    public function researchTextGenerator(): TextGeneratorInterface
    {
        return $this->instances['research_text'] ??= match ($this->choice('AI_TEXT_PROVIDER')) {
            'mistral' => new MistralAgentTextProvider($this->mistralTextAgentConfig()),
            'ollama' => $this->textGenerator(),
            default => throw new Exception('AI_TEXT_PROVIDER invalide (attendu : mistral ou ollama).'),
        };
    }

    public function imageGenerator(): ImageGeneratorInterface
    {
        return $this->instances['image'] ??= match ($this->choice('AI_IMAGE_PROVIDER')) {
            'mistral' => new MistralImageProvider($this->mistralImageConfig()),
            default => throw new Exception('AI_IMAGE_PROVIDER invalide (attendu : mistral).'),
        };
    }

    public function speechSynthesizer(): SpeechSynthesizerInterface
    {
        return $this->instances['speech'] ??= match ($this->choice('AI_SPEECH_PROVIDER')) {
            'local' => new OpenAiCompatibleSpeechProvider([
                'base_url' => $this->get('TTS_BASE_URL'),
                'model' => $this->get('TTS_MODEL'),
                'voice' => $this->get('TTS_VOICE'),
                'format' => $this->get('TTS_FORMAT'),
            ]),
            'mistral' => new OpenAiProvider($this->mistralConfig()),
            default => throw new Exception('AI_SPEECH_PROVIDER invalide (attendu : mistral ou local).'),
        };
    }

    public function transcriber(): TranscriberInterface
    {
        return $this->instances['transcription'] ??= match ($this->choice('AI_TRANSCRIPTION_PROVIDER')) {
            'whisper' => new WhisperTranscriptionProvider([
                'base_url' => $this->get('WHISPER_BASE_URL'),
                'model' => $this->get('WHISPER_MODEL'),
                'language' => $this->get('WHISPER_LANGUAGE'),
            ]),
            'mistral' => new OpenAiProvider($this->mistralConfig()),
            default => throw new Exception('AI_TRANSCRIPTION_PROVIDER invalide (attendu : mistral ou whisper).'),
        };
    }

    /**
     * L'API Mistral (chat completions + transcription Voxtral) est
     * compatible avec le format OpenAI : même client, seule la config change.
     */
    private function mistralConfig(): array
    {
        return array_filter([
            'api_key' => $this->get('MISTRAL_API_KEY'),
            'base_url' => $this->get('MISTRAL_BASE_URL'),
            'text_model' => $this->get('MISTRAL_TEXT_MODEL'),
            'transcription_model' => $this->get('MISTRAL_TRANSCRIPTION_MODEL'),
            'speech_model' => $this->get('MISTRAL_SPEECH_MODEL'),
            'speech_voice' => $this->get('MISTRAL_SPEECH_VOICE'),
            // Contrairement à /v1/audio/speech d'OpenAI (audio brut en corps
            // de réponse), celui de Mistral renvoie du JSON avec l'audio en
            // base64 dans "audio_data" (vérifié par un appel réel le
            // 22/09/2026, voir mémoire ai-provider-mistral-migration).
            'speech_response_format' => 'json_base64',
            'api_key_env' => 'MISTRAL_API_KEY',
        ], fn ($value) => $value !== null);
    }

    /**
     * L'image Mistral passe par un agent doté de l'outil "image_generation" +
     * l'API Conversations, pas par un endpoint REST direct (voir
     * MistralImageProvider) : sa config n'a donc rien à voir avec celle du
     * texte/de la transcription (image_model, pas de transcription_model...).
     */
    private function mistralImageConfig(): array
    {
        return array_filter([
            'api_key' => $this->get('MISTRAL_API_KEY'),
            'base_url' => $this->get('MISTRAL_BASE_URL'),
            'image_model' => $this->get('MISTRAL_IMAGE_MODEL'),
            'image_agent_id' => $this->get('MISTRAL_IMAGE_AGENT_ID'),
        ], fn ($value) => $value !== null);
    }

    /**
     * Config de l'agent texte "web_search" (voir MistralAgentTextProvider) :
     * réutilise la même clé API et le même modèle que le texte classique,
     * avec en plus un id d'agent pré-créé optionnel (évite une recréation à
     * chaque process, comme MISTRAL_IMAGE_AGENT_ID pour l'image).
     */
    private function mistralTextAgentConfig(): array
    {
        return array_merge($this->mistralConfig(), array_filter([
            'text_agent_id' => $this->get('MISTRAL_TEXT_AGENT_ID'),
        ], fn ($value) => $value !== null));
    }

    /** Ollama Cloud expose une API de chat compatible OpenAI (texte uniquement). */
    private function ollamaConfig(): array
    {
        return array_filter([
            'api_key' => $this->get('OLLAMA_API_KEY'),
            'base_url' => $this->get('OLLAMA_BASE_URL'),
            'text_model' => $this->get('OLLAMA_TEXT_MODEL'),
            'api_key_env' => 'OLLAMA_API_KEY',
        ], fn ($value) => $value !== null);
    }

    /** Valeur d'environnement normalisée en minuscules. */
    private function choice(string $key, string $default = 'mistral'): string
    {
        return strtolower(trim((string) ($this->env[$key] ?? $default)));
    }

    private function get(string $key): ?string
    {
        $value = $this->env[$key] ?? null;
        return ($value === null || $value === '') ? null : (string) $value;
    }
}
