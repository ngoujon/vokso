<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;

/**
 * Génération d'image 100 % locale via l'API txt2img d'AUTOMATIC1111
 * (également exposée par SD.Next et compatible ComfyUI derrière un proxy).
 * Remplace DALL-E dans une installation sans dépendance externe.
 */
class StableDiffusionProvider implements ImageGeneratorInterface
{
    private Client $client;
    private string $baseUrl;
    private int $width;
    private int $height;
    private int $steps;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'http://stable-diffusion:7860'), '/');
        $this->width = (int) ($config['width'] ?? 1024);
        $this->height = (int) ($config['height'] ?? 1024);
        $this->steps = (int) ($config['steps'] ?? 30);
        $this->client = new Client(['timeout' => (float) ($config['timeout'] ?? 600)]);
    }

    public function generateImage(string $prompt): string
    {
        try {
            $response = $this->client->post($this->baseUrl . '/sdapi/v1/txt2img', [
                'json' => [
                    'prompt' => $prompt,
                    'width' => $this->width,
                    'height' => $this->height,
                    'steps' => $this->steps,
                    'batch_size' => 1,
                ],
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            throw new Exception('Appel Stable Diffusion en échec : ' . $e->getMessage());
        }

        $data = json_decode((string) $response->getBody(), true);
        if (!isset($data['images'][0])) {
            throw new Exception('Réponse Stable Diffusion inattendue.');
        }

        $binary = base64_decode($data['images'][0], true);
        if ($binary === false) {
            throw new Exception('Image Stable Diffusion illisible (base64 invalide).');
        }

        return $binary;
    }
}
