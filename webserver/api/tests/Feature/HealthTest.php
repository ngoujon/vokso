<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_returns_minimal_ok_body(): void
    {
        $response = $this->get('/health');

        $response->assertOk();
        $this->assertSame('{"status":"ok"}', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('noindex', $response->headers->get('X-Robots-Tag'));
    }

    public function test_health_only_accepts_get_and_head(): void
    {
        $this->call('HEAD', '/health')->assertOk();
        $this->post('/health')->assertStatus(405);
        $this->put('/health')->assertStatus(405);
        $this->get('/health/extra')->assertNotFound();
    }
}
