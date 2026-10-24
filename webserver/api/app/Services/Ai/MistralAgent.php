<?php

namespace App\Services\Ai;

use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Agent Mistral (API bêta Agents + Conversations), partagé par la narration
 * avec recherche web et la génération d'image : Mistral n'expose ces outils
 * que via un agent, pas via une route classique.
 *
 * L'agent est créé au premier appel puis son id est mémoïsé pour le process ;
 * renseigner son id en configuration (MISTRAL_*_AGENT_ID) évite cette
 * création. L'API Conversations n'a qu'un champ « inputs » : prompt système
 * et prompt utilisateur y sont concaténés par l'appelant.
 * https://docs.mistral.ai/agents/agents_introduction/
 */
final class MistralAgent
{
    private ApiClient $api;
    private ?string $id;

    /**
     * @param  array  $definition  corps de POST /agents sans le modèle (name, description, instructions, tools)
     */
    public function __construct(array $config, private string $model, private array $definition, ?string $agentId)
    {
        $this->api = ApiClient::mistral($config);
        $this->id = ($agentId ?? '') !== '' ? $agentId : null;
    }

    /** Démarre une conversation ; la promesse se résout avec la réponse décodée (clé « outputs »). */
    public function converseAsync(string $inputs): PromiseInterface
    {
        // id() reste synchrone : un aller-retour de plus, au tout premier appel seulement.
        return $this->api->postJsonAsync('/conversations', ['agent_id' => $this->id(), 'inputs' => $inputs]);
    }

    /** Démarre une conversation en flux (server-sent events), lu au fil de l'eau par l'appelant. */
    public function converseStream(string $inputs): StreamInterface
    {
        return $this->api->postStream('/conversations', ['agent_id' => $this->id(), 'inputs' => $inputs, 'stream' => true]);
    }

    /** Entrées d'une conversation (message, appels d'outils et leurs résultats). */
    public function history(string $conversationId): array
    {
        $data = json_decode($this->api->getRawAsync('/conversations/'.rawurlencode($conversationId).'/history')->wait(), true);

        return is_array($data) ? ($data['entries'] ?? $data) : [];
    }

    /** Fichier hébergé hors de l'API (URL signée renvoyée par un outil). */
    public function download(string $url): string
    {
        return $this->api->download($url);
    }

    /** Contenu brut d'un fichier produit par un outil de l'agent (API Files), après $delayMs éventuel. */
    public function fileContentAsync(string $fileId, int $delayMs = 0): PromiseInterface
    {
        return $this->api->getRawAsync('/files/'.$fileId.'/content', $delayMs);
    }

    private function id(): string
    {
        if ($this->id !== null) {
            return $this->id;
        }

        $data = $this->api->postJson('/agents', ['model' => $this->model] + $this->definition);
        if (! isset($data['id'])) {
            throw new Exception('Réponse Mistral inattendue pour la création de l\'agent '.$this->definition['name'].' (id absent).');
        }

        return $this->id = (string) $data['id'];
    }

    /**
     * Chunks d'un type donné, cherchés en profondeur dans la réponse : les
     * sorties imbriquent leur contenu (chaîne ou liste de chunks) et cette API
     * bêta en fait varier la forme.
     *
     * @return list<array>
     */
    public static function chunks(mixed $node, string $type): array
    {
        if (! is_array($node)) {
            return [];
        }
        if (($node['type'] ?? null) === $type) {
            return [$node];
        }

        return array_merge(...array_map(fn ($child) => self::chunks($child, $type), array_values($node)) ?: [[]]);
    }
}
