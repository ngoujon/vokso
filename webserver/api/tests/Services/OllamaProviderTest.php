<?php

namespace Tests\Services;

use App\Services\OllamaProvider;
use PHPUnit\Framework\TestCase;

class OllamaProviderTest extends TestCase
{
    public function testIsReadyReturnsFalseWhenServerUnreachable(): void
    {
        $provider = new OllamaProvider([
            'base_url' => 'http://127.0.0.1:1',
            'timeout' => 0.2,
        ]);

        $this->assertFalse($provider->isReady());
    }
}
