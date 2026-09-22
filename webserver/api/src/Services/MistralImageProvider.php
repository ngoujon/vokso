<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Génération d'image via l'API Mistral (bêta). Contrairement à OpenAI, il
 * n'existe pas de route unique "images/generations" : il faut créer un agent
 * doté de l'outil "image_generation", démarrer une conversation avec le
 * prompt, puis télécharger le fichier produit via l'API Files.
 * https://docs.mistral.ai/agents/connectors/image_generation/
 */
class MistralImageProvider implements ImageGeneratorInterface
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
        $this->model = (string) ($config['image_model'] ?? 'mistral-medium-latest');
        // Permet de réutiliser un agent déjà créé (évite une recréation à
        // chaque appel) ; sinon un nouvel agent est créé au premier appel.
        $this->agentId = ($config['image_agent_id'] ?? '') !== '' ? (string) $config['image_agent_id'] : null;

        $clientConfig = [
            'timeout' => (float) ($config['timeout'] ?? 180),
            'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
        ];
        if (isset($config['handler'])) {
            $clientConfig['handler'] = $config['handler'];
        }
        $this->client = new Client($clientConfig);
    }

    public function generateImage(string $prompt): string
    {
        $agentId = $this->ensureAgent();
        $fileId = $this->requestImageFileId($agentId, $prompt);

        return (string) $this->client->get($this->baseUrl . '/files/' . $fileId . '/content')->getBody();
    }

    public function generateImageAsync(string $prompt): PromiseInterface
    {
        // ensureAgent() reste synchrone : c'est un aller-retour de plus avant
        // que la promesse ne parte (perceptible seulement au tout premier
        // appel du process, puisque l'id est ensuite mémoïsé). Renseigner
        // MISTRAL_IMAGE_AGENT_ID l'évite complètement.
        $agentId = $this->ensureAgent();

        return $this->client->requestAsync('POST', $this->baseUrl . '/conversations', [
            'json' => ['agent_id' => $agentId, 'inputs' => $prompt],
        ])->then(function ($response) {
            $fileId = $this->extractFileId($this->decodeJson($response, '/conversations'));
            return $this->client->getAsync($this->baseUrl . '/files/' . $fileId . '/content');
        })->then(
            fn ($response) => (string) $response->getBody(),
            function ($reason) {
                $body = ($reason instanceof \GuzzleHttp\Exception\RequestException && $reason->getResponse())
                    ? (string) $reason->getResponse()->getBody()
                    : '';
                throw new Exception('Échec de la génération d\'image Mistral : ' . $reason->getMessage() . ' ' . $body);
            }
        );
    }

    /** Crée l'agent "image_generation" au premier appel et réutilise son id ensuite. */
    private function ensureAgent(): string
    {
        if ($this->agentId !== null) {
            return $this->agentId;
        }

        try {
            $response = $this->client->post($this->baseUrl . '/agents', [
                'json' => [
                    'model' => $this->model,
                    'name' => 'vokso-image-generator',
                    'description' => 'Génère la vignette d\'un podcast à partir d\'un extrait de son texte.',
                    'tools' => [['type' => 'image_generation']],
                ],
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : '';
            throw new Exception('Échec de la création de l\'agent image Mistral : ' . $e->getMessage() . ' ' . $body);
        }

        $data = $this->decodeJson($response, '/agents');
        if (!isset($data['id'])) {
            throw new Exception('Réponse Mistral inattendue pour la création de l\'agent image (id absent).');
        }

        return $this->agentId = (string) $data['id'];
    }

    private function requestImageFileId(string $agentId, string $prompt): string
    {
        try {
            $response = $this->client->post($this->baseUrl . '/conversations', [
                'json' => ['agent_id' => $agentId, 'inputs' => $prompt],
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : '';
            throw new Exception('Échec de la conversation image Mistral : ' . $e->getMessage() . ' ' . $body);
        }

        return $this->extractFileId($this->decodeJson($response, '/conversations'));
    }

    /**
     * Le fichier généré apparaît comme un chunk `{"type": "tool_file", "file_id": "..."}`,
     * imbriqué dans `outputs[].content` (elle-même une chaîne ou une liste de chunks).
     * On parcourt la réponse en profondeur pour rester robuste à cette API bêta.
     */
    private function extractFileId(array $data): string
    {
        $fileId = $this->findToolFileId($data['outputs'] ?? []);
        if ($fileId === null) {
            throw new Exception('Réponse Mistral inattendue : aucune image (tool_file) trouvée dans la conversation.');
        }

        return $fileId;
    }

    private function findToolFileId(mixed $node): ?string
    {
        if (is_array($node)) {
            if (($node['type'] ?? null) === 'tool_file' && isset($node['file_id'])) {
                return (string) $node['file_id'];
            }
            foreach ($node as $child) {
                $found = $this->findToolFileId($child);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
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
