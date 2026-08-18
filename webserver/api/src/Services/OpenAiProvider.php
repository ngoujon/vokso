<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;

/**
 * Fournisseur OpenAI : texte (chat completions), image (DALL-E),
 * synthèse vocale (TTS) et transcription (Whisper).
 */
class OpenAiProvider implements TextGeneratorInterface, ImageGeneratorInterface, SpeechSynthesizerInterface, TranscriberInterface
{
    private Client $client;
    private string $apiKey;
    private string $baseUrl;
    private string $textModel;
    private string $imageModel;
    private string $imageSize;
    private string $speechModel;
    private string $transcriptionModel;

    public function __construct(array $config)
    {
        $this->apiKey = (string) ($config['api_key'] ?? '');
        if ($this->apiKey === '') {
            throw new Exception('OPENAI_API_KEY est absent des variables d\'environnement.');
        }

        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.openai.com/v1'), '/');
        $this->textModel = (string) ($config['text_model'] ?? 'gpt-4o-mini');
        $this->imageModel = (string) ($config['image_model'] ?? 'dall-e-3');
        $this->imageSize = (string) ($config['image_size'] ?? '1024x1024');
        $this->speechModel = (string) ($config['speech_model'] ?? 'tts-1-hd');
        $this->transcriptionModel = (string) ($config['transcription_model'] ?? 'whisper-1');

        $this->client = new Client([
            'timeout' => (float) ($config['timeout'] ?? 180),
            'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
        ]);
    }

    public function generateText(string $systemPrompt, string $userPrompt): string
    {
        $data = $this->requestJson('POST', '/chat/completions', [
            'model' => $this->textModel,
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Réponse OpenAI inattendue pour la génération de texte.');
        }

        return (string) $data['choices'][0]['message']['content'];
    }

    public function generateImage(string $prompt): string
    {
        $data = $this->requestJson('POST', '/images/generations', [
            'model' => $this->imageModel,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $this->imageSize,
        ]);

        // Selon le modèle, l'image est renvoyée en base64 ou via une URL temporaire.
        if (isset($data['data'][0]['b64_json'])) {
            $binary = base64_decode($data['data'][0]['b64_json'], true);
            if ($binary === false) {
                throw new Exception('Image OpenAI illisible (base64 invalide).');
            }
            return $binary;
        }

        if (isset($data['data'][0]['url'])) {
            return (string) $this->client->get($data['data'][0]['url'])->getBody();
        }

        throw new Exception('Réponse OpenAI inattendue pour la génération d\'image.');
    }

    public function synthesize(string $text): string
    {
        $voice = rand(0, 1) ? 'nova' : 'onyx';

        try {
            $response = $this->client->post($this->baseUrl . '/audio/speech', [
                'json' => [
                    'model' => $this->speechModel,
                    'voice' => $voice,
                    'input' => $text,
                    'speed' => 1,
                ],
            ]);
        } catch (Exception $e) {
            throw new Exception('Échec de la synthèse vocale OpenAI : ' . $e->getMessage());
        }

        return (string) $response->getBody();
    }

    public function audioExtension(): string
    {
        return 'mp3';
    }

    public function transcribe(string $audioFilePath): string
    {
        if (!is_readable($audioFilePath)) {
            throw new Exception('Fichier audio introuvable : ' . $audioFilePath);
        }

        try {
            $response = $this->client->post($this->baseUrl . '/audio/transcriptions', [
                'multipart' => [
                    ['name' => 'model', 'contents' => $this->transcriptionModel],
                    ['name' => 'file', 'contents' => fopen($audioFilePath, 'r'), 'filename' => basename($audioFilePath)],
                ],
            ]);
        } catch (Exception $e) {
            throw new Exception('Échec de la transcription OpenAI : ' . $e->getMessage());
        }

        $data = json_decode((string) $response->getBody(), true);
        if (!isset($data['text'])) {
            throw new Exception('Réponse OpenAI inattendue pour la transcription.');
        }

        return (string) $data['text'];
    }

    private function requestJson(string $method, string $path, array $payload): array
    {
        try {
            $response = $this->client->request($method, $this->baseUrl . $path, ['json' => $payload]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : '';
            throw new Exception('Appel OpenAI en échec (' . $path . ') : ' . $e->getMessage() . ' ' . $body);
        }

        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data)) {
            throw new Exception('Réponse OpenAI illisible (' . $path . ').');
        }
        if (isset($data['error'])) {
            throw new Exception('Erreur OpenAI : ' . ($data['error']['message'] ?? 'inconnue'));
        }

        return $data;
    }
}
