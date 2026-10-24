<?php

namespace App\Services\Ai;

use App\Support\AiBudget;
use Exception;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Throwable;

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

    /**
     * Fichier pas encore disponible (404 juste après la conversation) :
     * téléchargement réessayé après un délai croissant. C'est gratuit, alors
     * que l'image, elle, est déjà payée.
     *
     * Un 429 sur la conversation n'est jamais réessayé : « image_generation
     * rate limit reached » signifie que l'agent a enchaîné les appels de
     * l'outil (une dizaine, tous facturés) jusqu'à la limite de Mistral.
     * Relancer, c'est repayer la même série.
     */
    private const DOWNLOAD_ATTEMPTS = 3;

    /** Appels facturés estimés derrière un « image_generation rate limit reached » (relevé du 24/10/2026). */
    private const RATE_LIMITED_CALLS = 10;

    /**
     * Rappel envoyé avec chaque prompt : l'agent déjà créé chez Mistral
     * (MISTRAL_IMAGE_AGENT_ID) garde ses anciennes instructions.
     */
    private const SINGLE_CALL = 'Appelle l\'outil image_generation une seule fois, puis arrête-toi : '
        .'ne génère aucune variante, ne réessaie pas et ne retouche pas l\'image.';

    private MistralAgent $agent;
    private int $retryDelayMs;
    private bool $metered;

    public function __construct(array $config)
    {
        $this->retryDelayMs = (int) ($config['image_retry_delay_ms'] ?? 5000);
        // Comme ApiClient : un handler injecté (tests) ne coûte rien.
        $this->metered = ! isset($config['handler']);
        // Chaque conversation (y compris la seconde tentative) compte comme une image (AiBudget).
        $this->agent = new MistralAgent(['budget_kind' => 'image'] + $config,(string) ($config['image_model'] ?? 'mistral-medium-latest'), [
            'name' => 'vokso-image-generator',
            'description' => 'Génère la vignette d\'un podcast à partir d\'un extrait de son texte.',
            'instructions' => 'Tu es l\'illustrateur d\'un podcast. À chaque message, tu génères exactement une image '
                .'avec l\'outil image_generation, sans poser de question ni répondre seulement par du texte. '.self::SINGLE_CALL,
            'tools' => [['type' => 'image_generation']],
        ], $config['image_agent_id'] ?? null);
    }

    public function generateImage(string $prompt): string
    {
        return $this->generateImageAsync($prompt)->wait();
    }

    public function generateImageAsync(string $prompt): PromiseInterface
    {
        return $this->requestFileIdAsync($prompt)->then(fn (string $fileId) => $this->downloadAsync($fileId));
    }

    private function downloadAsync(string $fileId, int $attempt = 1): PromiseInterface
    {
        $delayMs = $attempt > 1 ? $this->retryDelayMs * ($attempt - 1) : 0;

        return $this->agent->fileContentAsync($fileId, $delayMs)->otherwise(function ($reason) use ($fileId, $attempt) {
            $message = $reason instanceof Throwable ? $reason->getMessage() : (string) $reason;
            if ($attempt < self::DOWNLOAD_ATTEMPTS && str_contains($message, '`404 ')) {
                return $this->downloadAsync($fileId, $attempt + 1);
            }

            return Create::rejectionFor($reason);
        });
    }

    private function requestFileIdAsync(string $prompt, int $attempt = 1): PromiseInterface
    {
        // Un seul appel d'outil à la fois (argument du chat completion,
        // accepté par les conversations) en plus de la consigne écrite.
        $converse = $this->agent->converseAsync($prompt."\n\n".self::SINGLE_CALL, ['parallel_tool_calls' => false])
            ->otherwise(function ($reason) {
                $message = $reason instanceof Throwable ? $reason->getMessage() : (string) $reason;
                if ($this->metered && str_contains($message, 'image_generation rate limit')) {
                    // Déjà comptée comme une image : le reste de la série est ajouté au garde-fou.
                    AiBudget::record('image', self::RATE_LIMITED_CALLS - 1);
                }

                return Create::rejectionFor($reason);
            });

        return $converse->then(function (array $data) use ($prompt, $attempt) {
            $outputs = $data['outputs'] ?? [];

            // Chaque appel de l'outil est une image facturée, même si l'agent
            // en enchaîne plusieurs dans la même conversation (constaté : une
            // vingtaine pour un seul épisode). Le garde-fou compte la
            // conversation comme une image : les appels en plus y sont ajoutés.
            $executions = count(MistralAgent::chunks($outputs, 'tool.execution'));
            if ($this->metered && $executions > 1) {
                AiBudget::record('image', $executions - 1);
            }

            $fileId = self::fileId($outputs);
            if ($fileId !== null) {
                return $fileId;
            }
            // Nouvelle conversation seulement si l'agent n'a pas appelé
            // l'outil (réponse texte, rien de facturé) : sinon on paierait
            // encore une série d'images pour rien.
            if ($attempt < self::CONVERSATION_ATTEMPTS && $executions === 0) {
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
                .' Sorties : ['.implode(', ', $types).'], outil appelé '.$executions.' fois.'
                .($reply !== '' ? ' Réponse de l\'agent : « '.mb_substr($reply, 0, 300).' »' : ''));
        });
    }

    /**
     * Id du fichier produit : chunk « tool_file » attendu, sinon tout chunk
     * portant un file_id (la forme de cette API bêta varie : une image
     * générée et payée ne doit pas être perdue pour un nom de type).
     */
    private static function fileId(array $outputs): ?string
    {
        $file = MistralAgent::chunks($outputs, 'tool_file')[0] ?? self::firstWithFileId($outputs);

        return isset($file['file_id']) && is_string($file['file_id']) && $file['file_id'] !== '' ? $file['file_id'] : null;
    }

    private static function firstWithFileId(mixed $node): ?array
    {
        if (! is_array($node)) {
            return null;
        }
        if (isset($node['file_id']) && is_string($node['file_id'])) {
            return $node;
        }
        foreach ($node as $child) {
            if (($found = self::firstWithFileId($child)) !== null) {
                return $found;
            }
        }

        return null;
    }
}
