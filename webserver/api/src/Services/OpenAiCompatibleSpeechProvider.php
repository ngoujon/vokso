<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;

/**
 * Synthèse vocale locale via un serveur exposant la route OpenAI
 * `/v1/audio/speech` (Kokoro-FastAPI, openedai-speech/Piper, Coqui…).
 * Permet de supprimer la dépendance à l'API TTS d'OpenAI.
 */
class OpenAiCompatibleSpeechProvider implements SpeechSynthesizerInterface
{
    private Client $client;
    private string $baseUrl;
    private string $model;
    private string $voice;
    private string $format;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'http://tts:8880/v1'), '/');
        $this->model = (string) ($config['model'] ?? 'tts-1');
        $this->voice = (string) ($config['voice'] ?? 'ff_siwis');
        $this->format = (string) ($config['format'] ?? 'mp3');
        $this->client = new Client(['timeout' => (float) ($config['timeout'] ?? 600)]);
    }

    public function synthesize(string $text): string
    {
        try {
            $response = $this->client->post($this->baseUrl . '/audio/speech', [
                'json' => [
                    'model' => $this->model,
                    'voice' => $this->voice,
                    'input' => $text,
                    'response_format' => $this->format,
                ],
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            throw new Exception('Appel TTS local en échec : ' . $e->getMessage());
        }

        return (string) $response->getBody();
    }

    public function audioExtension(): string
    {
        return $this->format;
    }
}
