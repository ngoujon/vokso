<?php

namespace App\Services\Ai;

use Exception;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Génération de texte via la route /chat/completions, commune à Mistral et
 * à Ollama Cloud.
 */
class ChatCompletionsTextProvider implements TextGeneratorInterface
{
    private ApiClient $api;
    private string $model;

    public function __construct(array $config, string $apiKeyEnvName = 'MISTRAL_API_KEY', string $defaultBaseUrl = 'https://api.mistral.ai/v1')
    {
        $this->api = new ApiClient($config, $apiKeyEnvName, $defaultBaseUrl);
        $this->model = (string) (($config['text_model'] ?? '') ?: 'mistral-small-latest');
    }

    public function generateText(string $systemPrompt, string $userPrompt): string
    {
        return $this->extract($this->api->postJson('/chat/completions', $this->payload($systemPrompt, $userPrompt)));
    }

    public function generateTextAsync(string $systemPrompt, string $userPrompt): PromiseInterface
    {
        return $this->api->postJsonAsync('/chat/completions', $this->payload($systemPrompt, $userPrompt))
            ->then(fn (array $data) => $this->extract($data));
    }

    private function payload(string $systemPrompt, string $userPrompt): array
    {
        return [
            'model' => $this->model,
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];
    }

    private function extract(array $data): string
    {
        if (! isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Réponse de l\'API inattendue pour la génération de texte.');
        }

        return (string) $data['choices'][0]['message']['content'];
    }
}
