<?php

namespace Tests\Services;

use App\Services\StableDiffusionProvider;
use Exception;
use PHPUnit\Framework\TestCase;

class StableDiffusionProviderTest extends TestCase
{
    public function testGenerateImageWrapsConnectionFailure(): void
    {
        $provider = new StableDiffusionProvider([
            'base_url' => 'http://127.0.0.1:1',
            'timeout' => 0.2,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Appel Stable Diffusion en échec');
        $provider->generateImage('un chat astronaute');
    }
}
