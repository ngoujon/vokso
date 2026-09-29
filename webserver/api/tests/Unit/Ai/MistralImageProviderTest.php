<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\MistralImageProvider;
use Exception;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
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

    public function testGenerateImageAsyncRejectsWhenNoToolFileInResponse(): void
    {
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], json_encode(['outputs' => []])),
        ], ['image_agent_id' => 'agent-preexistant']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('aucune image');
        $provider->generateImageAsync('un prompt')->wait();
    }
}
