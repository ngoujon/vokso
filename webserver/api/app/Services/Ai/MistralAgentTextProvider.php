<?php

namespace App\Services\Ai;

use Exception;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Narration rédigée par un agent Mistral équipé de l'outil « web_search »
 * (voir MistralAgent). Le modèle décide seul s'il doit chercher sur le web ;
 * l'appel de l'outil n'est jamais forcé.
 * https://docs.mistral.ai/agents/connectors/websearch/
 */
class MistralAgentTextProvider implements TextGeneratorInterface
{
    private MistralAgent $agent;

    public function __construct(array $config)
    {
        $this->agent = new MistralAgent($config, (string) ($config['text_model'] ?? 'mistral-small-latest'), [
            'name' => 'vokso-text-writer',
            'description' => 'Rédige le texte de narration d\'un podcast, avec accès web search si besoin d\'informations complémentaires.',
            'tools' => [['type' => 'web_search']],
        ], $config['text_agent_id'] ?? null);
    }

    public function generateText(string $systemPrompt, string $userPrompt): string
    {
        return $this->generateTextAsync($systemPrompt, $userPrompt)->wait();
    }

    public function generateTextAsync(string $systemPrompt, string $userPrompt): PromiseInterface
    {
        return $this->agent->converseAsync(trim($systemPrompt)."\n\n".trim($userPrompt))
            ->then(fn (array $data) => $this->extractText($data));
    }

    /**
     * Les sorties mêlent appels d'outil et messages : le texte final est celui
     * du dernier « message.output », chaîne ou liste de chunks « text ».
     */
    private function extractText(array $data): string
    {
        $text = '';
        foreach (MistralAgent::chunks($data['outputs'] ?? [], 'message.output') as $output) {
            $content = $output['content'] ?? '';
            $part = is_string($content)
                ? $content
                : implode('', array_map(fn (array $chunk) => (string) ($chunk['text'] ?? ''), MistralAgent::chunks($content, 'text')));
            if (trim($part) !== '') {
                $text = $part;
            }
        }

        if ($text === '') {
            throw new Exception('Réponse Mistral inattendue : aucun texte trouvé dans la conversation.');
        }

        return $text;
    }
}
