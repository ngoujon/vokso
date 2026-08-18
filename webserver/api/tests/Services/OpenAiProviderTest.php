<?php

namespace Tests\Services;

use App\Services\OpenAiProvider;
use Exception;
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
}
