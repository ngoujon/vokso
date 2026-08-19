<?php

namespace App\Services;

use Exception;

/**
 * Construit les fournisseurs d'IA à partir des variables d'environnement.
 *
 * Le texte et l'image passent toujours par OpenAI. La synthèse vocale et la
 * transcription peuvent rester locales (TTS + Whisper), Whisper servant à
 * transcrire la voix de l'utilisateur pour remplacer la saisie au clavier.
 *
 *   AI_SPEECH_PROVIDER=openai|local
 *   AI_TRANSCRIPTION_PROVIDER=openai|whisper
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
        return $this->instances['text'] ??= new OpenAiProvider($this->openAiConfig());
    }

    public function imageGenerator(): ImageGeneratorInterface
    {
        return $this->instances['image'] ??= new OpenAiProvider($this->openAiConfig());
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
            'openai' => new OpenAiProvider($this->openAiConfig()),
            default => throw new Exception('AI_SPEECH_PROVIDER invalide (attendu : openai ou local).'),
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
            'openai' => new OpenAiProvider($this->openAiConfig()),
            default => throw new Exception('AI_TRANSCRIPTION_PROVIDER invalide (attendu : openai ou whisper).'),
        };
    }

    private function openAiConfig(): array
    {
        return array_filter([
            'api_key' => $this->get('OPENAI_API_KEY'),
            'base_url' => $this->get('OPENAI_BASE_URL'),
            'text_model' => $this->get('OPENAI_TEXT_MODEL'),
            'image_model' => $this->get('OPENAI_IMAGE_MODEL'),
            'speech_model' => $this->get('OPENAI_SPEECH_MODEL'),
            'transcription_model' => $this->get('OPENAI_TRANSCRIPTION_MODEL'),
        ], fn ($value) => $value !== null);
    }

    /** Valeur d'environnement normalisée en minuscules, "openai" par défaut. */
    private function choice(string $key): string
    {
        return strtolower(trim((string) ($this->env[$key] ?? 'openai')));
    }

    private function get(string $key): ?string
    {
        $value = $this->env[$key] ?? null;
        return ($value === null || $value === '') ? null : (string) $value;
    }
}
