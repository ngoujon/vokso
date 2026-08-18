<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;

/**
 * Fournisseur Ollama (serveur local, aucun appel externe, aucune clé API).
 *
 * Ollama n'expose que des modèles de langage : il couvre la génération de
 * texte et la catégorisation, mais PAS la génération d'image, la synthèse
 * vocale ni la transcription. Ces trois briques doivent être servies par
 * d'autres conteneurs locaux (voir StableDiffusionProvider,
 * OpenAiCompatibleSpeechProvider et WhisperTranscriptionProvider).
 */
class OllamaProvider implements TextGeneratorInterface
{
    private Client $client;
    private string $baseUrl;
    private string $textModel;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'http://ollama:11434'), '/');
        $this->textModel = (string) ($config['text_model'] ?? 'llama3.1:8b');
        $this->client = new Client(['timeout' => (float) ($config['timeout'] ?? 600)]);
    }

    public function generateText(string $systemPrompt, string $userPrompt): string
    {
        try {
            $response = $this->client->post($this->baseUrl . '/api/chat', [
                'json' => [
                    'model' => $this->textModel,
                    'stream' => false,
                    'options' => ['temperature' => 0.2],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ],
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : '';
            throw new Exception('Appel Ollama en échec : ' . $e->getMessage() . ' ' . $body);
        }

        $data = json_decode((string) $response->getBody(), true);
        if (!isset($data['message']['content'])) {
            throw new Exception('Réponse Ollama inattendue pour la génération de texte.');
        }

        return (string) $data['message']['content'];
    }

    /** Vérifie que le serveur Ollama répond et que le modèle est installé. */
    public function isReady(): bool
    {
        try {
            $data = json_decode((string) $this->client->get($this->baseUrl . '/api/tags')->getBody(), true);
        } catch (Exception $e) {
            return false;
        }

        foreach ($data['models'] ?? [] as $model) {
            if (($model['name'] ?? '') === $this->textModel) {
                return true;
            }
        }

        return false;
    }
}
