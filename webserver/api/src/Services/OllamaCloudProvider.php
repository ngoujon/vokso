<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;

/**
 * Fournisseur Ollama Cloud (service hébergé par Ollama, nécessite une clé API).
 *
 * Même API que l'Ollama local (endpoint /api/chat) mais servie depuis
 * https://ollama.com avec authentification par jeton porteur.
 */
class OllamaCloudProvider implements TextGeneratorInterface
{
    private Client $client;
    private string $baseUrl;
    private string $textModel;

    public function __construct(array $config)
    {
        $apiKey = (string) ($config['api_key'] ?? '');
        if ($apiKey === '') {
            throw new Exception('OLLAMACLOUD_API_KEY est absent des variables d\'environnement.');
        }

        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://ollama.com'), '/');
        $this->textModel = (string) ($config['text_model'] ?? 'gpt-oss:120b');

        $this->client = new Client([
            'timeout' => (float) ($config['timeout'] ?? 180),
            'headers' => ['Authorization' => 'Bearer ' . $apiKey],
        ]);
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
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : '';
            throw new Exception('Appel Ollama Cloud en échec : ' . $e->getMessage() . ' ' . $body);
        }

        $data = json_decode((string) $response->getBody(), true);
        if (!isset($data['message']['content'])) {
            throw new Exception('Réponse Ollama Cloud inattendue pour la génération de texte.');
        }

        return (string) $data['message']['content'];
    }
}
