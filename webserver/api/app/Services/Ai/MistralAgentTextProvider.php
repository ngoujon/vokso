<?php

namespace App\Services\Ai;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Génération de texte via un agent Mistral équipé de l'outil "web_search" :
 * même mécanique que MistralImageProvider (agent + API Conversations, pas de
 * route "chat/completions" classique), mais on récupère le texte de la
 * réponse plutôt qu'un fichier. Le modèle décide seul s'il a besoin de
 * chercher sur le web (voir le prompt "texte" en base, qui ne l'impose que
 * "si besoin") ; on ne force jamais l'appel de l'outil.
 * https://docs.mistral.ai/agents/connectors/websearch/
 */
class MistralAgentTextProvider implements TextGeneratorInterface
{
    private Client $client;
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private ?string $agentId;

    public function __construct(array $config)
    {
        $this->apiKey = (string) ($config['api_key'] ?? '');
        if ($this->apiKey === '') {
            throw new Exception('MISTRAL_API_KEY est absent des variables d\'environnement.');
        }

        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.mistral.ai/v1'), '/');
        $this->model = (string) ($config['text_model'] ?? 'mistral-small-latest');
        $this->agentId = ($config['text_agent_id'] ?? '') !== '' ? (string) $config['text_agent_id'] : null;

        $clientConfig = [
            'timeout' => (float) ($config['timeout'] ?? 180),
            'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
        ];
        if (isset($config['handler'])) {
            $clientConfig['handler'] = $config['handler'];
        }
        $this->client = new Client($clientConfig);
    }

    public function generateText(string $systemPrompt, string $userPrompt): string
    {
        $agentId = $this->ensureAgent();

        try {
            $response = $this->client->post($this->baseUrl . '/conversations', [
                'json' => ['agent_id' => $agentId, 'inputs' => $this->combinePrompts($systemPrompt, $userPrompt)],
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : '';
            throw new Exception('Échec de la conversation texte Mistral : ' . $e->getMessage() . ' ' . $body);
        }

        return $this->extractText($this->decodeJson($response, '/conversations'));
    }

    public function generateTextAsync(string $systemPrompt, string $userPrompt): PromiseInterface
    {
        // ensureAgent() reste synchrone, comme dans MistralImageProvider : un
        // seul aller-retour de plus, uniquement au tout premier appel du
        // process (l'id est ensuite mémoïsé). Renseigner MISTRAL_TEXT_AGENT_ID
        // l'évite complètement.
        $agentId = $this->ensureAgent();

        return $this->client->requestAsync('POST', $this->baseUrl . '/conversations', [
            'json' => ['agent_id' => $agentId, 'inputs' => $this->combinePrompts($systemPrompt, $userPrompt)],
        ])->then(
            fn ($response) => $this->extractText($this->decodeJson($response, '/conversations')),
            function ($reason) {
                $body = ($reason instanceof \GuzzleHttp\Exception\RequestException && $reason->getResponse())
                    ? (string) $reason->getResponse()->getBody()
                    : '';
                throw new Exception('Échec de la génération de texte Mistral (web search) : ' . $reason->getMessage() . ' ' . $body);
            }
        );
    }

    /**
     * L'API Conversations ne distingue pas prompt système / prompt
     * utilisateur (un seul champ "inputs", comme pour MistralImageProvider) :
     * on les concatène en gardant la même séparation sémantique qu'un appel
     * chat/completions classique.
     */
    private function combinePrompts(string $systemPrompt, string $userPrompt): string
    {
        return trim($systemPrompt) . "\n\n" . trim($userPrompt);
    }

    /** Crée l'agent "web_search" au premier appel et réutilise son id ensuite. */
    private function ensureAgent(): string
    {
        if ($this->agentId !== null) {
            return $this->agentId;
        }

        try {
            $response = $this->client->post($this->baseUrl . '/agents', [
                'json' => [
                    'model' => $this->model,
                    'name' => 'vokso-text-writer',
                    'description' => 'Rédige le texte de narration d\'un podcast, avec accès web search si besoin d\'informations complémentaires.',
                    'tools' => [['type' => 'web_search']],
                ],
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : '';
            throw new Exception('Échec de la création de l\'agent texte Mistral : ' . $e->getMessage() . ' ' . $body);
        }

        $data = $this->decodeJson($response, '/agents');
        if (!isset($data['id'])) {
            throw new Exception('Réponse Mistral inattendue pour la création de l\'agent texte (id absent).');
        }

        return $this->agentId = (string) $data['id'];
    }

    /**
     * La réponse peut contenir plusieurs entrées dans "outputs" (appels
     * d'outil web_search inclus) : le texte final est celui du dernier
     * "message.output", potentiellement scindé en plusieurs chunks
     * `{"type": "text", "text": "..."}` à concaténer.
     */
    private function extractText(array $data): string
    {
        $outputs = $data['outputs'] ?? [];
        $text = null;

        foreach ($outputs as $output) {
            if (!is_array($output) || ($output['type'] ?? null) !== 'message.output') {
                continue;
            }

            $content = $output['content'] ?? '';
            if (is_string($content)) {
                $text = $content;
                continue;
            }

            if (is_array($content)) {
                $chunks = array_filter(
                    array_map(
                        fn ($chunk) => (is_array($chunk) && ($chunk['type'] ?? null) === 'text') ? (string) ($chunk['text'] ?? '') : null,
                        $content
                    )
                );
                if ($chunks !== []) {
                    $text = implode('', $chunks);
                }
            }
        }

        if ($text === null || trim($text) === '') {
            throw new Exception('Réponse Mistral inattendue : aucun texte trouvé dans la conversation.');
        }

        return $text;
    }

    private function decodeJson($response, string $path): array
    {
        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data)) {
            throw new Exception('Réponse Mistral illisible (' . $path . ').');
        }
        if (isset($data['error'])) {
            throw new Exception('Erreur Mistral : ' . (is_array($data['error']) ? ($data['error']['message'] ?? 'inconnue') : $data['error']));
        }

        return $data;
    }
}
