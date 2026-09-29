<?php

namespace App\Services\Ai;

use Exception;

/** Transcription d'un fichier audio déposé par l'utilisateur (Mistral Voxtral). */
class MistralTranscriptionProvider implements TranscriberInterface
{
    private ApiClient $api;
    private string $model;

    public function __construct(array $config)
    {
        $this->api = new ApiClient($config + ['timeout' => 600], 'MISTRAL_API_KEY', 'https://api.mistral.ai/v1');
        $this->model = (string) (($config['transcription_model'] ?? '') ?: 'voxtral-mini-latest');
    }

    public function transcribe(string $audioFilePath): string
    {
        if (! is_readable($audioFilePath)) {
            throw new Exception('Fichier audio introuvable : '.$audioFilePath);
        }

        $data = $this->api->postMultipart('/audio/transcriptions', [
            ['name' => 'model', 'contents' => $this->model],
            ['name' => 'file', 'contents' => fopen($audioFilePath, 'r'), 'filename' => basename($audioFilePath)],
        ]);

        if (! isset($data['text'])) {
            throw new Exception('Réponse de l\'API inattendue pour la transcription.');
        }

        return (string) $data['text'];
    }
}
