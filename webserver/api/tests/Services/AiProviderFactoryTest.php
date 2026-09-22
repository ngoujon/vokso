<?php

namespace Tests\Services;

use App\Services\AiProviderFactory;
use App\Services\MistralImageProvider;
use App\Services\OpenAiProvider;
use App\Services\OpenAiCompatibleSpeechProvider;
use App\Services\WhisperTranscriptionProvider;
use Exception;
use PHPUnit\Framework\TestCase;

class AiProviderFactoryTest extends TestCase
{
    private function factory(array $env): AiProviderFactory
    {
        return new AiProviderFactory(array_merge(
            ['OPENAI_API_KEY' => 'sk-test', 'MISTRAL_API_KEY' => 'mistral-test'],
            $env
        ));
    }

    public function testTextGeneratorDefaultsToOpenAi(): void
    {
        $provider = $this->factory([])->textGenerator();
        $this->assertInstanceOf(OpenAiProvider::class, $provider);
    }

    public function testImageGeneratorDefaultsToMistral(): void
    {
        $provider = $this->factory([])->imageGenerator();
        $this->assertInstanceOf(MistralImageProvider::class, $provider);
    }

    public function testImageGeneratorRoutesToOpenAi(): void
    {
        $provider = $this->factory(['AI_IMAGE_PROVIDER' => 'openai'])->imageGenerator();
        $this->assertInstanceOf(OpenAiProvider::class, $provider);
    }

    public function testImageGeneratorRejectsInvalidProvider(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('AI_IMAGE_PROVIDER invalide');
        $this->factory(['AI_IMAGE_PROVIDER' => 'bogus'])->imageGenerator();
    }

    public function testTextGeneratorRoutesToOllama(): void
    {
        $provider = $this->factory(['AI_TEXT_PROVIDER' => 'ollama', 'OLLAMA_API_KEY' => 'ollama-test'])->textGenerator();
        $this->assertInstanceOf(OpenAiProvider::class, $provider);
    }

    public function testTextGeneratorRejectsInvalidProvider(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('AI_TEXT_PROVIDER invalide');
        $this->factory(['AI_TEXT_PROVIDER' => 'bogus'])->textGenerator();
    }

    public function testSpeechSynthesizerRoutesToLocal(): void
    {
        $provider = $this->factory(['AI_SPEECH_PROVIDER' => 'local'])->speechSynthesizer();
        $this->assertInstanceOf(OpenAiCompatibleSpeechProvider::class, $provider);
    }

    public function testSpeechSynthesizerRejectsInvalidProvider(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('AI_SPEECH_PROVIDER invalide');
        $this->factory(['AI_SPEECH_PROVIDER' => 'bogus'])->speechSynthesizer();
    }

    public function testTranscriberRoutesToWhisper(): void
    {
        $provider = $this->factory(['AI_TRANSCRIPTION_PROVIDER' => 'whisper'])->transcriber();
        $this->assertInstanceOf(WhisperTranscriptionProvider::class, $provider);
    }

    public function testTranscriberRejectsInvalidProvider(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('AI_TRANSCRIPTION_PROVIDER invalide');
        $this->factory(['AI_TRANSCRIPTION_PROVIDER' => 'bogus'])->transcriber();
    }

    public function testInstancesAreMemoized(): void
    {
        $factory = $this->factory([]);
        $this->assertSame($factory->textGenerator(), $factory->textGenerator());
    }
}
