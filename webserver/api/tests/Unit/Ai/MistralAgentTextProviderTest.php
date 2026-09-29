<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\MistralAgentTextProvider;
use Exception;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class MistralAgentTextProviderTest extends TestCase
{
    public function testConstructorRejectsMissingApiKey(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('MISTRAL_API_KEY est absent');
        new MistralAgentTextProvider([]);
    }

    private function providerWithMockedResponses(array $responses, array $extraConfig = []): MistralAgentTextProvider
    {
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        return new MistralAgentTextProvider(array_merge(
            ['api_key' => 'mistral-test', 'handler' => $handlerStack],
            $extraConfig
        ));
    }

    public function testGenerateTextAsyncCreatesAgentThenExtractsText(): void
    {
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], json_encode(['id' => 'agent-123'])),
            new Response(200, [], json_encode([
                'outputs' => [
                    ['type' => 'tool.execution', 'name' => 'web_search'],
                    [
                        'type' => 'message.output',
                        'content' => [
                            ['type' => 'text', 'text' => 'Bonjour, '],
                            ['type' => 'text', 'text' => 'voici le texte du podcast.'],
                        ],
                    ],
                ],
            ])),
        ]);

        $result = $provider->generateTextAsync('system', 'user')->wait();

        $this->assertSame('Bonjour, voici le texte du podcast.', $result);
    }

    public function testGenerateTextAsyncReusesConfiguredAgentId(): void
    {
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], json_encode([
                'outputs' => [
                    ['type' => 'message.output', 'content' => 'Texte direct en chaîne.'],
                ],
            ])),
        ], ['text_agent_id' => 'agent-preexistant']);

        $result = $provider->generateTextAsync('system', 'user')->wait();

        $this->assertSame('Texte direct en chaîne.', $result);
    }

    public function testGenerateTextAsyncKeepsLastMessageOutputWhenSeveral(): void
    {
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], json_encode([
                'outputs' => [
                    ['type' => 'message.output', 'content' => 'Brouillon intermédiaire.'],
                    ['type' => 'message.output', 'content' => 'Texte final.'],
                ],
            ])),
        ], ['text_agent_id' => 'agent-preexistant']);

        $result = $provider->generateTextAsync('system', 'user')->wait();

        $this->assertSame('Texte final.', $result);
    }

    public function testGenerateTextAsyncRejectsWhenNoTextInResponse(): void
    {
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], json_encode(['outputs' => []])),
        ], ['text_agent_id' => 'agent-preexistant']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('aucun texte');
        $provider->generateTextAsync('system', 'user')->wait();
    }
}
