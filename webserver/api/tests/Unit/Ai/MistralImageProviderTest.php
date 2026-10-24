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
    public function testConstructorRejectsMissingApiKey(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('MISTRAL_API_KEY est absent');
        new MistralImageProvider([]);
    }

    private function providerWithMockedResponses(array $responses, array $extraConfig = []): MistralImageProvider
    {
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        return new MistralImageProvider(array_merge(
            ['api_key' => 'mistral-test', 'handler' => $handlerStack],
            $extraConfig
        ));
    }

    public function testGenerateImageAsyncCreatesAgentThenDownloadsFile(): void
    {
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], json_encode(['id' => 'agent-123'])),
            new Response(200, [], json_encode([
                'outputs' => [
                    [
                        'type' => 'message.output',
                        'content' => [
                            ['type' => 'text', 'text' => 'Voici votre image.'],
                            ['type' => 'tool_file', 'file_id' => 'file-456', 'file_type' => 'png'],
                        ],
                    ],
                ],
            ])),
            new Response(200, [], 'binary-image'),
        ]);

        $result = $provider->generateImageAsync('un prompt')->wait();

        $this->assertSame('binary-image', $result);
    }

    public function testGenerateImageAsyncReusesConfiguredAgentId(): void
    {
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], json_encode([
                'outputs' => [
                    ['type' => 'tool_file', 'file_id' => 'file-789'],
                ],
            ])),
            new Response(200, [], 'binary-image-2'),
        ], ['image_agent_id' => 'agent-preexistant']);

        $result = $provider->generateImageAsync('un prompt')->wait();

        $this->assertSame('binary-image-2', $result);
    }

    public function testGenerateImageAsyncRetriesWhenAgentAnswersWithTextOnly(): void
    {
        $textOnly = json_encode(['outputs' => [
            ['type' => 'message.output', 'content' => [['type' => 'text', 'text' => 'Je ne peux pas.']]],
        ]]);
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], $textOnly),
            new Response(200, [], json_encode(['outputs' => [['type' => 'tool_file', 'file_id' => 'file-1']]])),
            new Response(200, [], 'binary-image-3'),
        ], ['image_agent_id' => 'agent-preexistant']);

        $this->assertSame('binary-image-3', $provider->generateImageAsync('un prompt')->wait());
    }

    public function testGenerateImageAsyncRetriesDownloadWhenFileIsNotReady(): void
    {
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], json_encode(['outputs' => [['type' => 'tool_file', 'file_id' => 'file-1']]])),
            new Response(404, [], json_encode(['detail' => 'Not found'])),
            new Response(200, [], 'binary-image-4'),
        ], ['image_agent_id' => 'agent-preexistant', 'image_retry_delay_ms' => 0]);

        $this->assertSame('binary-image-4', $provider->generateImageAsync('un prompt')->wait());
    }

    public function testGenerateImageAsyncNeverRetriesImageRateLimit(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(429, [], json_encode(['detail' => 'image_generation rate limit reached.'])),
            new Response(200, [], json_encode(['outputs' => [['type' => 'tool_file', 'file_id' => 'file-1']]])),
        ]));
        $stack->push(Middleware::history($history));
        $provider = new MistralImageProvider(['api_key' => 'mistral-test', 'handler' => $stack, 'image_agent_id' => 'agent-preexistant']);

        try {
            $provider->generateImageAsync('un prompt')->wait();
            $this->fail('Une exception était attendue.');
        } catch (Exception $e) {
            $this->assertStringContainsString('rate limit', $e->getMessage());
        }
        $this->assertCount(1, $history);
        // Un seul appel d'outil demandé, par l'argument et par la consigne.
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertFalse($body['completion_args']['parallel_tool_calls']);
        $this->assertStringContainsString('une seule fois', $body['inputs']);
    }

    public function testGenerateImageAsyncDoesNotStartNewConversationAfterPaidToolCalls(): void
    {
        $execution = ['type' => 'tool.execution', 'name' => 'image_generation'];
        // Une seule réponse en file : une seconde conversation ferait échouer le MockHandler autrement.
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], json_encode(['outputs' => [$execution, $execution, $execution]])),
        ], ['image_agent_id' => 'agent-preexistant']);

        try {
            $provider->generateImageAsync('un prompt')->wait();
            $this->fail('Une exception était attendue.');
        } catch (Exception $e) {
            $this->assertStringContainsString('outil appelé 3 fois', $e->getMessage());
        }
    }

    public function testGenerateImageAsyncFindsFileIdOutsideToolFileChunk(): void
    {
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], json_encode(['outputs' => [
                ['type' => 'message.output', 'content' => [['type' => 'image_file', 'file_id' => 'file-9']]],
            ]])),
            new Response(200, [], 'binary-image-5'),
        ], ['image_agent_id' => 'agent-preexistant']);

        $this->assertSame('binary-image-5', $provider->generateImageAsync('un prompt')->wait());
    }

    public function testGenerateImageAsyncRejectsWithAgentReplyWhenNoToolFileTwice(): void
    {
        $textOnly = json_encode(['outputs' => [
            ['type' => 'message.output', 'content' => [['type' => 'text', 'text' => 'Je ne peux pas.']]],
        ]]);
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], $textOnly),
            new Response(200, [], $textOnly),
        ], ['image_agent_id' => 'agent-preexistant']);

        try {
            $provider->generateImageAsync('un prompt')->wait();
            $this->fail('Une exception était attendue.');
        } catch (Exception $e) {
            $this->assertStringContainsString('aucune image', $e->getMessage());
            $this->assertStringContainsString('Je ne peux pas.', $e->getMessage());
        }
    }
}
