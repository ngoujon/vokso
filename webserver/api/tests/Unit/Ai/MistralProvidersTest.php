<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\ChatCompletionsTextProvider;
use App\Services\Ai\MistralAgentTextProvider;
use App\Services\Ai\MistralImageProvider;
use App\Services\Ai\MistralSpeechProvider;
use App\Services\Ai\MistralTranscriptionProvider;
use Exception;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class MistralProvidersTest extends TestCase
{
    private array $history = [];

    private function handler(array $responses): HandlerStack
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return $stack;
    }

    public function test_providers_require_an_api_key(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('MISTRAL_API_KEY est absent');
        new ChatCompletionsTextProvider([]);
    }

    public function test_ollama_reports_its_own_missing_key(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('OLLAMA_API_KEY est absent');
        (new AiProviderFactory(['text_provider' => 'ollama', 'ollama' => []]))->textGenerator();
    }

    public function test_text_generation_reads_the_first_choice(): void
    {
        $provider = new ChatCompletionsTextProvider([
            'api_key' => 'test-key',
            'handler' => $this->handler([
                new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'Bonjour le monde']]]])),
            ]),
        ]);

        $this->assertSame('Bonjour le monde', $provider->generateTextAsync('system', 'user')->wait());

        $request = $this->history[0]['request'];
        $this->assertSame('https://api.mistral.ai/v1/chat/completions', (string) $request->getUri());
        $this->assertSame('Bearer test-key', $request->getHeaderLine('Authorization'));
        $this->assertSame('mistral-small-latest', json_decode((string) $request->getBody(), true)['model']);
    }

    public function test_concurrent_text_calls_settle_independently(): void
    {
        $provider = new ChatCompletionsTextProvider([
            'api_key' => 'test-key',
            'handler' => $this->handler([
                new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'texte principal']]]])),
                new Response(500, [], json_encode(['error' => ['message' => 'quota dépassé']])),
            ]),
        ]);

        $results = PromiseUtils::settle([
            'text' => $provider->generateTextAsync('system', 'sujet'),
            'category' => $provider->generateTextAsync('system-cat', 'sujet'),
        ])->wait();

        $this->assertSame('texte principal', $results['text']['value']);
        $this->assertSame('rejected', $results['category']['state']);
        $this->assertStringContainsString('Appel API en échec', $results['category']['reason']->getMessage());
    }

    public function test_speech_decodes_base64_audio_and_never_sends_speed(): void
    {
        $provider = new MistralSpeechProvider([
            'api_key' => 'test-key',
            'handler' => $this->handler([
                new Response(200, [], json_encode(['audio_data' => base64_encode('binary-audio')])),
            ]),
        ]);

        $this->assertSame('binary-audio', $provider->synthesizeAsync('un texte')->wait());
        $this->assertSame('mp3', $provider->audioExtension());

        $payload = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertSame('fr_marie_neutral', $payload['voice']);
        $this->assertArrayNotHasKey('speed', $payload);
    }

    public function test_speech_rejects_a_payload_without_audio(): void
    {
        $provider = new MistralSpeechProvider([
            'api_key' => 'test-key',
            'handler' => $this->handler([new Response(200, [], json_encode(['object' => 'error']))]),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('audio_data');
        $provider->synthesizeAsync('un texte')->wait();
    }

    public function test_transcription_rejects_an_unreadable_file(): void
    {
        $provider = new MistralTranscriptionProvider(['api_key' => 'test-key']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Fichier audio introuvable');
        $provider->transcribe('/chemin/inexistant.wav');
    }

    public function test_transcription_returns_the_text(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'vokso');
        file_put_contents($file, 'audio');

        $provider = new MistralTranscriptionProvider([
            'api_key' => 'test-key',
            'handler' => $this->handler([new Response(200, [], json_encode(['text' => 'Bonjour']))]),
        ]);

        $this->assertSame('Bonjour', $provider->transcribe($file));
        unlink($file);
    }

    public function test_factory_uses_mistral_everywhere_by_default(): void
    {
        $factory = new AiProviderFactory(['mistral' => ['api_key' => 'test-key']]);

        $this->assertSame('mistral', $factory->textProvider());
        $this->assertInstanceOf(ChatCompletionsTextProvider::class, $factory->textGenerator());
        $this->assertInstanceOf(MistralAgentTextProvider::class, $factory->researchTextGenerator());
        $this->assertInstanceOf(MistralImageProvider::class, $factory->imageGenerator());
        $this->assertInstanceOf(MistralSpeechProvider::class, $factory->speechSynthesizer());
        $this->assertInstanceOf(MistralTranscriptionProvider::class, $factory->transcriber());
    }

    public function test_factory_falls_back_to_mistral_for_unknown_text_provider(): void
    {
        $factory = new AiProviderFactory(['text_provider' => 'bogus', 'mistral' => ['api_key' => 'test-key']]);

        $this->assertSame('mistral', $factory->textProvider());
    }

    public function test_ollama_research_generator_is_the_plain_text_generator(): void
    {
        $factory = new AiProviderFactory([
            'text_provider' => 'ollama',
            'ollama' => ['api_key' => 'test-key', 'text_model' => 'llama3'],
        ]);

        $this->assertSame($factory->textGenerator(), $factory->researchTextGenerator());
        $this->assertSame('llama3', $factory->textModel());
    }
}
