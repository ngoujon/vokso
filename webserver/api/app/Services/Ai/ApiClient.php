<?php

namespace App\Services\Ai;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Client HTTP JSON authentifié par clé API (Bearer), partagé par les
 * fournisseurs Mistral / Ollama Cloud.
 */
class ApiClient
{
    private Client $client;
    private string $baseUrl;

    public function __construct(array $config, string $apiKeyEnvName, string $defaultBaseUrl)
    {
        $apiKey = (string) ($config['api_key'] ?? '');
        if ($apiKey === '') {
            throw new Exception($apiKeyEnvName.' est absent des variables d\'environnement.');
        }

        $this->baseUrl = rtrim((string) (($config['base_url'] ?? '') ?: $defaultBaseUrl), '/');

        $clientConfig = [
            'timeout' => (float) ($config['timeout'] ?? 180),
            'headers' => ['Authorization' => 'Bearer '.$apiKey],
        ];
        // Permet d'injecter un handler Guzzle (MockHandler) dans les tests.
        if (isset($config['handler'])) {
            $clientConfig['handler'] = $config['handler'];
        }
        $this->client = new Client($clientConfig);
    }

    public function postJson(string $path, array $payload): array
    {
        try {
            $response = $this->client->post($this->baseUrl.$path, ['json' => $payload]);
        } catch (GuzzleException $e) {
            throw new Exception('Appel API en échec ('.$path.') : '.$e->getMessage().' '.$this->errorBody($e));
        }

        return $this->decode($response, $path);
    }

    /** La promesse se résout avec le tableau décodé. */
    public function postJsonAsync(string $path, array $payload): PromiseInterface
    {
        return $this->client->postAsync($this->baseUrl.$path, ['json' => $payload])->then(
            fn (ResponseInterface $response) => $this->decode($response, $path),
            function ($reason) use ($path) {
                throw new Exception('Appel API en échec ('.$path.') : '.$reason->getMessage().' '.$this->errorBody($reason));
            }
        );
    }

    /** La promesse se résout avec le corps brut de la réponse. */
    public function postRawAsync(string $path, array $options): PromiseInterface
    {
        return $this->client->postAsync($this->baseUrl.$path, $options)->then(
            fn (ResponseInterface $response) => (string) $response->getBody(),
            function ($reason) use ($path) {
                throw new Exception('Appel API en échec ('.$path.') : '.$reason->getMessage().' '.$this->errorBody($reason));
            }
        );
    }

    public function postMultipart(string $path, array $multipart): array
    {
        try {
            $response = $this->client->post($this->baseUrl.$path, ['multipart' => $multipart]);
        } catch (GuzzleException $e) {
            throw new Exception('Appel API en échec ('.$path.') : '.$e->getMessage().' '.$this->errorBody($e));
        }

        return $this->decode($response, $path);
    }

    private function decode(ResponseInterface $response, string $path): array
    {
        $data = json_decode((string) $response->getBody(), true);
        if (! is_array($data)) {
            throw new Exception('Réponse illisible ('.$path.').');
        }
        if (isset($data['error'])) {
            $message = is_array($data['error']) ? ($data['error']['message'] ?? 'inconnue') : (string) $data['error'];
            throw new Exception('Erreur API : '.$message);
        }

        return $data;
    }

    private function errorBody(mixed $reason): string
    {
        return ($reason instanceof RequestException && $reason->getResponse())
            ? (string) $reason->getResponse()->getBody()
            : '';
    }
}
