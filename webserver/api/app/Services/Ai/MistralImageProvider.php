<?php

namespace App\Services\Ai;

use Exception;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Génération d'image via un agent Mistral doté de l'outil « image_generation »
 * (voir MistralAgent) : la conversation renvoie l'id du fichier produit, puis
 * l'image est téléchargée via l'API Files.
 * https://docs.mistral.ai/agents/connectors/image_generation/
 */
class MistralImageProvider implements ImageGeneratorInterface
{
    /** L'agent répond parfois par du texte sans appeler l'outil : une seconde conversation est alors tentée. */
    private const CONVERSATION_ATTEMPTS = 2;

    private MistralAgent $agent;

    public function __construct(array $config)
    {
        // Chaque conversation (y compris la seconde tentative) compte comme une image (AiBudget).
        $this->agent = new MistralAgent(['budget_kind' => 'image'] + $config,(string) ($config['image_model'] ?? 'mistral-medium-latest'), [
            'name' => 'vokso-image-generator',
            'description' => 'Génère la vignette d\'un podcast à partir d\'un extrait de son texte.',
            'instructions' => 'Tu es l\'illustrateur d\'un podcast. À chaque message, tu génères exactement une image '
                .'avec l\'outil image_generation, sans poser de question ni répondre seulement par du texte.',
            'tools' => [['type' => 'image_generation']],
        ], $config['image_agent_id'] ?? null);
    }

    public function generateImage(string $prompt): string
    {
        return $this->generateImageAsync($prompt)->wait();
    }

    public function generateImageAsync(string $prompt): PromiseInterface
    {
        return $this->requestFileIdAsync($prompt)->then(fn (string $fileId) => $this->agent->fileContentAsync($fileId));
    }

    private function requestFileIdAsync(string $prompt, int $attempt = 1): PromiseInterface
    {
        return $this->agent->converseAsync($prompt)->then(function (array $data) use ($prompt, $attempt) {
            $outputs = $data['outputs'] ?? [];
            $file = MistralAgent::chunks($outputs, 'tool_file')[0] ?? null;
            if (isset($file['file_id'])) {
                return (string) $file['file_id'];
            }
            if ($attempt < self::CONVERSATION_ATTEMPTS) {
                return $this->requestFileIdAsync($prompt, $attempt + 1);
            }

            // Types de sorties et texte de l'agent : de quoi comprendre, depuis
            // le log, pourquoi l'outil n'a rien produit.
            $types = array_map(fn ($output) => is_array($output) ? (string) ($output['type'] ?? '?') : '?', (array) $outputs);
            $reply = trim(implode(' ', array_merge(
                array_map(fn (array $chunk) => (string) ($chunk['text'] ?? ''), MistralAgent::chunks($outputs, 'text')),
                array_filter(array_map(fn ($output) => is_string($output['content'] ?? null) ? $output['content'] : '', (array) $outputs))
            )));
            throw new Exception('Réponse Mistral inattendue : aucune image (tool_file) trouvée dans la conversation.'
                .' Sorties : ['.implode(', ', $types).'].'
                .($reply !== '' ? ' Réponse de l\'agent : « '.mb_substr($reply, 0, 300).' »' : ''));
        });
    }
}
