<?php

namespace App\Services\Ai;

/**
 * Construit les fournisseurs d'IA à partir de config('vokso.ai').
 *
 * Tout passe par Mistral : texte (avec un agent "web_search" pour la
 * narration), image (agent "image_generation"), voix (Voxtral TTS) et
 * transcription (Voxtral). Le texte peut aussi passer par Ollama Cloud
 * (AI_TEXT_PROVIDER=ollama), sans recherche web.
 */
class AiProviderFactory
{
    private array $instances = [];

    public function __construct(private array $config)
    {
    }

    public function textProvider(): string
    {
        return ($this->config['text_provider'] ?? 'mistral') === 'ollama' ? 'ollama' : 'mistral';
    }

    public function textModel(): string
    {
        return $this->textProvider() === 'ollama'
            ? (string) ($this->config['ollama']['text_model'] ?? '')
            : (string) ($this->config['mistral']['text_model'] ?? 'mistral-small-latest');
    }

    public function textGenerator(): TextGeneratorInterface
    {
        return $this->instances['text'] ??= $this->textProvider() === 'ollama'
            ? new ChatCompletionsTextProvider($this->config['ollama'] ?? [], 'OLLAMA_API_KEY', 'https://ollama.com/v1')
            : new ChatCompletionsTextProvider($this->mistral());
    }

    /**
     * Générateur utilisé pour la narration principale uniquement : équipé de
     * l'outil "web_search" côté Mistral pour vérifier des faits récents.
     * Ollama Cloud n'a pas ce connecteur : on retombe sur le texte standard.
     */
    public function researchTextGenerator(): TextGeneratorInterface
    {
        return $this->instances['research_text'] ??= $this->textProvider() === 'ollama'
            ? $this->textGenerator()
            : new MistralAgentTextProvider($this->mistral());
    }

    public function imageGenerator(): ImageGeneratorInterface
    {
        return $this->instances['image'] ??= new MistralImageProvider($this->mistral());
    }

    public function speechSynthesizer(): SpeechSynthesizerInterface
    {
        return $this->instances['speech'] ??= new MistralSpeechProvider($this->mistral());
    }

    public function transcriber(): TranscriberInterface
    {
        return $this->instances['transcription'] ??= new MistralTranscriptionProvider($this->mistral());
    }

    private function mistral(): array
    {
        return array_filter($this->config['mistral'] ?? [], fn ($value) => $value !== null && $value !== '');
    }
}
