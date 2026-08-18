<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;

/**
 * Transcription locale via un serveur exposant la route OpenAI
 * `/v1/audio/transcriptions` (faster-whisper-server, whisper.cpp server…).
 * Sert de source d'entrée alternative : un fichier audio déposé par
 * l'utilisateur devient le texte d'origine du podcast.
 */
class WhisperTranscriptionProvider implements TranscriberInterface
{
    private Client $client;
    private string $baseUrl;
    private string $model;
    private string $language;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'http://whisper:8000/v1'), '/');
        $this->model = (string) ($config['model'] ?? 'Systran/faster-whisper-medium');
        $this->language = (string) ($config['language'] ?? 'fr');
        $this->client = new Client(['timeout' => (float) ($config['timeout'] ?? 600)]);
    }

    public function transcribe(string $audioFilePath): string
    {
        if (!is_readable($audioFilePath)) {
            throw new Exception('Fichier audio introuvable : ' . $audioFilePath);
        }

        try {
            $response = $this->client->post($this->baseUrl . '/audio/transcriptions', [
                'multipart' => [
                    ['name' => 'model', 'contents' => $this->model],
                    ['name' => 'language', 'contents' => $this->language],
                    ['name' => 'file', 'contents' => fopen($audioFilePath, 'r'), 'filename' => basename($audioFilePath)],
                ],
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            throw new Exception('Appel Whisper local en échec : ' . $e->getMessage());
        }

        $data = json_decode((string) $response->getBody(), true);
        if (!isset($data['text'])) {
            throw new Exception('Réponse Whisper inattendue.');
        }

        return (string) $data['text'];
    }
}
