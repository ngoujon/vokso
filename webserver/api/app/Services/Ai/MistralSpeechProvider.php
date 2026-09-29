<?php

namespace App\Services\Ai;

use Exception;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Synthèse vocale Mistral (Voxtral TTS, route /audio/speech). La réponse est
 * du JSON avec l'audio encodé en base64 dans "audio_data" ; le paramètre
 * "speed" est refusé (422), il n'est donc jamais envoyé.
 */
class MistralSpeechProvider implements SpeechSynthesizerInterface
{
    private ApiClient $api;
    private string $model;
    private string $voice;

    public function __construct(array $config)
    {
        $this->api = new ApiClient($config + ['timeout' => 600], 'MISTRAL_API_KEY', 'https://api.mistral.ai/v1');
        $this->model = (string) (($config['speech_model'] ?? '') ?: 'voxtral-mini-tts-latest');
        $this->voice = (string) (($config['speech_voice'] ?? '') ?: 'fr_marie_neutral');
    }

    public function synthesize(string $text): string
    {
        return $this->synthesizeAsync($text)->wait();
    }

    public function synthesizeAsync(string $text): PromiseInterface
    {
        return $this->api->postJsonAsync('/audio/speech', [
            'model' => $this->model,
            'voice' => $this->voice,
            'input' => $text,
        ])->then(fn (array $data) => $this->decodeAudio($data));
    }

    public function audioExtension(): string
    {
        return 'mp3';
    }

    private function decodeAudio(array $data): string
    {
        if (! isset($data['audio_data']) || ! is_string($data['audio_data'])) {
            throw new Exception('Réponse de synthèse vocale inattendue (champ "audio_data" absent).');
        }

        $binary = base64_decode($data['audio_data'], true);
        if ($binary === false) {
            throw new Exception('Audio de synthèse vocale illisible (base64 invalide).');
        }

        return $binary;
    }
}
