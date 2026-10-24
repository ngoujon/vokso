<?php

namespace App\Services\Ai;

use App\Support\AiBudget;
use Exception;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Génération d'image via un agent Mistral doté de l'outil « image_generation »
 * (voir MistralAgent). https://docs.mistral.ai/agents/connectors/image_generation/
 *
 * Constaté en octobre 2026 : l'outil ne renvoie plus de chunk « tool_file »
 * mais l'URL signée de l'image dans le résultat de l'appel (tool.execution,
 * info.result). L'agent, ne voyant pas d'image dans sa réponse, rappelait
 * l'outil une vingtaine de fois (toutes facturées) avant d'abandonner.
 * D'où la conversation en flux : elle est coupée dès la première image
 * produite, et l'image est téléchargée depuis son URL.
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
     * l'outil jusqu'à la limite de Mistral. Relancer, c'est repayer la série.
     */
    private const DOWNLOAD_ATTEMPTS = 3;

    /** Appels facturés estimés derrière un « image_generation rate limit reached » (relevé du 24/10/2026). */
    private const RATE_LIMITED_CALLS = 10;

    /**
     * Rappel envoyé avec chaque prompt (une conversation avec agent refuse
     * completion_args, et un agent déjà créé garde ses instructions).
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
        $this->agent = new MistralAgent(['budget_kind' => 'image'] + $config, (string) ($config['image_model'] ?? 'mistral-medium-latest'), [
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

    /**
     * La conversation est lue en flux, donc de façon synchrone (quelques
     * secondes, avant le lancement des morceaux de voix) ; seul le
     * téléchargement d'un fichier de l'API Files reste asynchrone.
     */
    public function generateImageAsync(string $prompt): PromiseInterface
    {
        try {
            $source = $this->requestImage($prompt);
        } catch (Throwable $e) {
            return Create::rejectionFor($e);
        }
        if (isset($source['url'])) {
            try {
                return Create::promiseFor($this->agent->download($source['url']));
            } catch (Throwable $e) {
                return Create::rejectionFor($e);
            }
        }

        return $this->downloadAsync($source['file_id']);
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

    /** @return array{url: string}|array{file_id: string} */
    private function requestImage(string $prompt, int $attempt = 1): array
    {
        try {
            $stream = $this->agent->converseStream($prompt."\n\n".self::SINGLE_CALL);
        } catch (Exception $e) {
            if ($this->metered && str_contains($e->getMessage(), 'image_generation rate limit')) {
                // Déjà comptée comme une image : le reste de la série est ajouté au garde-fou.
                AiBudget::record('image', self::RATE_LIMITED_CALLS - 1);
            }
            throw $e;
        }

        [$source, $executions, $types, $reply] = self::readUntilImage($stream);
        if (isset($source['conversation_id'])) {
            // Appel de l'outil terminé sans l'image dans l'événement : elle
            // est relue dans l'historique de la conversation (lecture gratuite).
            $source = self::imageSource($this->agent->history($source['conversation_id']));
        }

        // Chaque appel de l'outil est une image facturée ; la conversation
        // compte déjà pour une dans le garde-fou.
        if ($this->metered && $executions > 1) {
            AiBudget::record('image', $executions - 1);
        }
        if ($source !== null) {
            return $source;
        }
        // Nouvelle conversation seulement si l'agent n'a pas appelé l'outil
        // (réponse texte, rien de facturé).
        if ($attempt < self::CONVERSATION_ATTEMPTS && $executions === 0) {
            return $this->requestImage($prompt, $attempt + 1);
        }

        throw new Exception('Réponse Mistral inattendue : aucune image trouvée dans la conversation.'
            .' Événements : ['.implode(', ', array_slice(array_unique($types), 0, 12)).'], outil appelé '.$executions.' fois.'
            .($reply !== '' ? ' Réponse de l\'agent : « '.mb_substr($reply, 0, 300).' »' : ''));
    }

    /**
     * Lit les événements du flux jusqu'à la première image (puis coupe la
     * connexion, ce qui arrête l'agent) ou jusqu'à la fin.
     *
     * @return array{0: ?array, 1: int, 2: list<string>, 3: string} [image ou ['conversation_id' => ...], appels de l'outil, types d'événements, texte de l'agent]
     */
    private static function readUntilImage(StreamInterface $stream): array
    {
        $buffer = '';
        $conversationId = null;
        $executions = 0;
        $types = [];
        $reply = '';
        try {
            while (! $stream->eof()) {
                $buffer .= $stream->read(8192);
                while (($end = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $end));
                    $buffer = substr($buffer, $end + 1);
                    if (! str_starts_with($line, 'data:')) {
                        continue;
                    }
                    $event = json_decode(trim(substr($line, 5)), true);
                    if (! is_array($event)) {
                        continue;
                    }
                    $type = (string) ($event['type'] ?? '?');
                    $types[] = $type;
                    if (str_starts_with($type, 'conversation.response.error')) {
                        throw new Exception('Erreur Mistral en cours de conversation : '.mb_substr((string) json_encode($event, JSON_UNESCAPED_UNICODE), 0, 300));
                    }
                    // Fin d'un appel de l'outil (« tool.execution.done » en flux, « tool.execution » sinon).
                    $toolDone = preg_match('/^tool\.execution(\.(done|completed|finished))?$/', $type) === 1;
                    if ($toolDone) {
                        $executions++;
                    }
                    if (is_string($event['content'] ?? null)) {
                        $reply .= $event['content'];
                    }
                    $conversationId ??= is_string($event['conversation_id'] ?? null) ? $event['conversation_id'] : null;
                    $source = self::imageSource($event);
                    if ($source !== null) {
                        return [$source, max(1, $executions), $types, $reply];
                    }
                    // Premier appel de l'outil terminé : on s'arrête là dans
                    // tous les cas, l'image sera cherchée dans l'historique.
                    if ($toolDone && $conversationId !== null) {
                        return [['conversation_id' => $conversationId], $executions, $types, $reply];
                    }
                    // Sans id de conversation : pas de deuxième image payée pour rien.
                    if ($toolDone && $executions >= 2) {
                        return [null, $executions, $types, trim($reply)];
                    }
                }
            }
        } finally {
            $stream->close();
        }

        return [null, $executions, $types, trim($reply)];
    }

    /**
     * Image dans un événement : URL signée (résultat de l'outil, chaîne JSON
     * {"url": ...} dans info.result) ou, ancienne forme, id de fichier
     * (chunk « tool_file »). Cherchée en profondeur, la forme de cette API
     * bêta variant.
     *
     * @return array{url: string}|array{file_id: string}|null
     */
    private static function imageSource(mixed $node): ?array
    {
        if (is_string($node)) {
            $node = trim($node);
            if (! str_starts_with($node, '{')) {
                return null;
            }
            $node = json_decode($node, true);
        }
        if (! is_array($node)) {
            return null;
        }
        if (isset($node['file_id']) && is_string($node['file_id']) && $node['file_id'] !== '') {
            return ['file_id' => $node['file_id']];
        }
        if (isset($node['url']) && is_string($node['url']) && preg_match('#^https://#', $node['url']) === 1) {
            // Le résultat de l'outil échappe les « & » de l'URL signée en « &amp; ».
            return ['url' => html_entity_decode($node['url'], ENT_QUOTES | ENT_HTML5)];
        }
        foreach ($node as $key => $child) {
            // Les arguments de l'appel (prompt) ne contiennent jamais l'image.
            if ($key === 'arguments') {
                continue;
            }
            if (($found = self::imageSource($child)) !== null) {
                return $found;
            }
        }

        return null;
    }
}
