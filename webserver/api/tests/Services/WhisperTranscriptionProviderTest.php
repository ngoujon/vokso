<?php

namespace Tests\Services;

use App\Services\WhisperTranscriptionProvider;
use Exception;
use PHPUnit\Framework\TestCase;

class WhisperTranscriptionProviderTest extends TestCase
{
    public function testTranscribeRejectsUnreadableFile(): void
    {
        $provider = new WhisperTranscriptionProvider(['base_url' => 'http://127.0.0.1:1']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Fichier audio introuvable');
        $provider->transcribe('/tmp/does-not-exist-' . uniqid() . '.mp3');
    }

    public function testTranscribeWrapsConnectionFailure(): void
    {
        $audioFilePath = tempnam(sys_get_temp_dir(), 'whisper-test-');
        file_put_contents($audioFilePath, 'audio-content');

        try {
            $provider = new WhisperTranscriptionProvider([
                'base_url' => 'http://127.0.0.1:1',
                'timeout' => 0.2,
            ]);

            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Appel Whisper local en échec');
            $provider->transcribe($audioFilePath);
        } finally {
            unlink($audioFilePath);
        }
    }
}
