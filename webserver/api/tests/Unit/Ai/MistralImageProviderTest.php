<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\MistralImageProvider;
use Exception;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class MistralImageProviderTest extends TestCase
{
    private array $history = [];

    public function testConstructorRejectsMissingApiKey(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('MISTRAL_API_KEY est absent');
        new MistralImageProvider([]);
    }

    private function providerWithMockedResponses(array $responses, array $extraConfig = []): MistralImageProvider
    {
        $this->history = [];
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($this->history));

        return new MistralImageProvider(array_merge(
            ['api_key' => 'mistral-test', 'handler' => $handlerStack, 'image_agent_id' => 'agent-preexistant', 'image_retry_delay_ms' => 0],
            $extraConfig
        ));
    }

    /** Corps server-sent events d'une conversation en flux. */
    private static function sse(array ...$events): Response
    {
        $body = '';
        foreach ($events as $event) {
            $body .= 'event: '.($event['type'] ?? 'message')."\ndata: ".json_encode($event)."\n\n";
        }

        return new Response(200, ['Content-Type' => 'text/event-stream'], $body);
    }

    private static function started(): array
    {
        return ['type' => 'conversation.response.started', 'conversation_id' => 'conv_1'];
    }

    /** Résultat de l'outil tel que renvoyé en octobre 2026 : URL signée, « & » échappés. */
    private static function urlDone(string $url = 'https://blob.example/image.jpg?se=1&amp;sig=a%2Bb'): array
    {
        return ['type' => 'tool.execution.done', 'name' => 'image_generation', 'info' => ['result' => json_encode(['url' => $url])]];
    }

    public function testDownloadsImageFromToolResultUrlAndStopsAtFirstCall(): void
    {
        $provider = $this->providerWithMockedResponses([
            self::sse(self::started(), ['type' => 'tool.execution.started', 'arguments' => '{"prompt": "abeille"}'], self::urlDone(), self::urlDone()),
            new Response(200, [], 'binary-image'),
        ]);

        $this->assertSame('binary-image', $provider->generateImageAsync('un prompt')->wait());
        $this->assertCount(2, $this->history);
        $conversation = $this->history[0]['request'];
        $body = json_decode((string) $conversation->getBody(), true);
        $this->assertTrue($body['stream']);
        $this->assertArrayNotHasKey('completion_args', $body);
        $this->assertStringContainsString('une seule fois', $body['inputs']);
        // URL signée décodée, et jamais la clé API envoyée hors de Mistral.
        $download = $this->history[1]['request'];
        $this->assertSame('https://blob.example/image.jpg?se=1&sig=a%2Bb', (string) $download->getUri());
        $this->assertFalse($download->hasHeader('Authorization'));
    }

    public function testReadsHistoryWhenToolResultIsNotInTheEvent(): void
    {
        $provider = $this->providerWithMockedResponses([
            self::sse(self::started(), ['type' => 'tool.execution.done', 'name' => 'image_generation']),
            new Response(200, [], json_encode(['entries' => [
                ['type' => 'message.input', 'content' => 'un prompt'],
                ['type' => 'tool.execution', 'arguments' => '{"prompt": "x"}', 'info' => ['result' => json_encode(['url' => 'https://blob.example/h.jpg'])]],
            ]])),
            new Response(200, [], 'binary-history'),
        ]);

        $this->assertSame('binary-history', $provider->generateImageAsync('un prompt')->wait());
        $this->assertStringEndsWith('/conversations/conv_1/history', $this->history[1]['request']->getUri()->getPath());
    }

    public function testStillAcceptsLegacyToolFileAndRetriesNotReadyFile(): void
    {
        $provider = $this->providerWithMockedResponses([
            self::sse(self::started(), ['type' => 'message.output.delta', 'content' => [['type' => 'tool_file', 'file_id' => 'file-1']]]),
            new Response(404, [], json_encode(['detail' => 'Not found'])),
            new Response(200, [], 'binary-file'),
        ]);

        $this->assertSame('binary-file', $provider->generateImageAsync('un prompt')->wait());
    }

    public function testCreatesAgentWhenNoIdIsConfigured(): void
    {
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], json_encode(['id' => 'agent-123'])),
            self::sse(self::started(), self::urlDone('https://blob.example/a.jpg')),
            new Response(200, [], 'binary-image-2'),
        ], ['image_agent_id' => null]);

        $this->assertSame('binary-image-2', $provider->generateImageAsync('un prompt')->wait());
    }

    public function testRetriesOnceWhenAgentAnswersWithTextOnly(): void
    {
        $provider = $this->providerWithMockedResponses([
            self::sse(self::started(), ['type' => 'message.output.delta', 'content' => 'Je ne peux pas.']),
            self::sse(self::started(), self::urlDone('https://blob.example/b.jpg')),
            new Response(200, [], 'binary-image-3'),
        ]);

        $this->assertSame('binary-image-3', $provider->generateImageAsync('un prompt')->wait());
    }

    public function testRejectsWithAgentReplyWhenNoImageTwice(): void
    {
        $textOnly = fn () => self::sse(self::started(), ['type' => 'message.output.delta', 'content' => 'Je ne peux pas.']);
        $provider = $this->providerWithMockedResponses([$textOnly(), $textOnly()]);

        try {
            $provider->generateImageAsync('un prompt')->wait();
            $this->fail('Une exception était attendue.');
        } catch (Exception $e) {
            $this->assertStringContainsString('aucune image', $e->getMessage());
            $this->assertStringContainsString('Je ne peux pas.', $e->getMessage());
        }
    }

    public function testStopsAfterSecondPaidCallWithoutConversationId(): void
    {
        $done = ['type' => 'tool.execution.done', 'name' => 'image_generation'];
        // Une seule réponse en file : ni seconde conversation ni lecture d'historique.
        $provider = $this->providerWithMockedResponses([self::sse($done, $done, $done, $done)]);

        try {
            $provider->generateImageAsync('un prompt')->wait();
            $this->fail('Une exception était attendue.');
        } catch (Exception $e) {
            $this->assertStringContainsString('outil appelé 2 fois', $e->getMessage());
        }
    }

    public function testNeverRetriesImageRateLimit(): void
    {
        $provider = $this->providerWithMockedResponses([
            new Response(429, [], json_encode(['detail' => 'image_generation rate limit reached.'])),
            self::sse(self::started(), self::urlDone()),
        ]);

        try {
            $provider->generateImageAsync('un prompt')->wait();
            $this->fail('Une exception était attendue.');
        } catch (Exception $e) {
            $this->assertStringContainsString('rate limit', $e->getMessage());
        }
        $this->assertCount(1, $this->history);
    }
}
