<?php

namespace Tests\Services;

use App\Services\OllamaCloudProvider;
use Exception;
use PHPUnit\Framework\TestCase;

class OllamaCloudProviderTest extends TestCase
{
    public function testConstructorRejectsMissingApiKey(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('OLLAMACLOUD_API_KEY est absent');
        new OllamaCloudProvider([]);
    }

    public function testConstructorAcceptsApiKey(): void
    {
        $provider = new OllamaCloudProvider(['api_key' => 'sk-cloud-test']);
        $this->assertInstanceOf(OllamaCloudProvider::class, $provider);
    }
}
