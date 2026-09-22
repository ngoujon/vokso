<?php

namespace Tests\Services;

use App\Services\OpenAiProvider;
use Exception;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class OpenAiProviderTest extends TestCase
{
    public function testConstructorRejectsMissingApiKey(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('OPENAI_API_KEY est absent');
        new OpenAiProvider([]);
    }

    public function testAudioExtensionIsMp3(): void
    {
        $provider = new OpenAiProvider(['api_key' => 'sk-test']);
        $this->assertSame('mp3', $provider->audioExtension());
    }

    public function testTranscribeRejectsUnreadableFile(): void
    {
        $provider = new OpenAiProvider(['api_key' => 'sk-test']);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Fichier audio introuvable');
        $provider->transcribe('/chemin/inexistant.wav');
    }

    private function providerWithMockedResponses(array $responses): OpenAiProvider
    {
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        return new OpenAiProvider(['api_key' => 'sk-test', 'handler' => $handlerStack]);
    }

    public function testGenerateTextAsyncResolvesWithMessageContent(): void
    {
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'Bonjour le monde']]]])),
        ]);

        $result = $provider->generateTextAsync('system', 'user')->wait();

        $this->assertSame('Bonjour le monde', $result);
    }

    public function testGenerateImageAsyncDecodesBase64Payload(): void
    {
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], json_encode(['data' => [['b64_json' => base64_encode('binary-image')]]])),
        ]);

        $result = $provider->generateImageAsync('un prompt')->wait();

        $this->assertSame('binary-image', $result);
    }

    public function testSynthesizeAsyncResolvesWithRawBody(): void
    {
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], 'binary-audio'),
        ]);

        $result = $provider->synthesizeAsync('un texte')->wait();

        $this->assertSame('binary-audio', $result);
    }

    public function testTextAndCategoryPromisesRunConcurrentlyAndBothSettle(): void
    {
        $provider = $this->providerWithMockedResponses([
            new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'texte principal']]]])),
            new Response(500, [], json_encode(['error' => ['message' => 'quota dépassé']])),
        ]);

        $results = PromiseUtils::settle([
            'text' => $provider->generateTextAsync('system', 'sujet'),
            'category' => $provider->generateTextAsync('system-cat', 'sujet'),
        ])->wait();

        $this->assertSame('fulfilled', $results['text']['state']);
        $this->assertSame('texte principal', $results['text']['value']);
        $this->assertSame('rejected', $results['category']['state']);
        $this->assertStringContainsString('Appel OpenAI en échec', $results['category']['reason']->getMessage());
    }
}
