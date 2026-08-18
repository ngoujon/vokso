<?php

namespace Tests\Controllers;

use App\Controllers\PingController;
use PHPUnit\Framework\TestCase;

/**
 * Simule php://input pour permettre aux tests d'injecter un corps de requête
 * JSON sans dépendre d'une vraie requête HTTP.
 */
class MockPhpInputStream
{
    public static string $data = '';

    /** @var resource|null déclarée explicitement : PHP l'assigne lors de stream_wrapper_register(). */
    public $context;

    private int $position = 0;

    public function stream_open(): bool
    {
        $this->position = 0;
        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr(self::$data, $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen(self::$data);
    }

    public function stream_stat(): array
    {
        return [];
    }
}

class PingControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
    }

    private function withRequestBody(string $json, callable $callback): mixed
    {
        MockPhpInputStream::$data = $json;
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', MockPhpInputStream::class);
        try {
            return $callback();
        } finally {
            stream_wrapper_restore('php');
        }
    }

    public function testRejectsNonPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $controller = new PingController();

        ob_start();
        $controller->index();
        $output = ob_get_clean();

        $this->assertSame(405, http_response_code());
        $this->assertSame(['error' => 'Méthode non autorisée'], json_decode($output, true));
    }

    public function testRespondsPongForPingValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $controller = new PingController();

        $output = $this->withRequestBody(json_encode(['value' => 'ping']), function () use ($controller) {
            ob_start();
            $controller->index();
            return ob_get_clean();
        });

        $this->assertSame(['message' => 'pong'], json_decode($output, true));
    }

    public function testRejectsUnexpectedValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $controller = new PingController();

        $output = $this->withRequestBody(json_encode(['value' => 'autre chose']), function () use ($controller) {
            ob_start();
            $controller->index();
            return ob_get_clean();
        });

        $this->assertSame(400, http_response_code());
        $this->assertSame(['error' => 'Mauvais choix'], json_decode($output, true));
    }

    public function testRejectsMissingValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $controller = new PingController();

        $output = $this->withRequestBody(json_encode([]), function () use ($controller) {
            ob_start();
            $controller->index();
            return ob_get_clean();
        });

        $this->assertSame(400, http_response_code());
        $this->assertSame(['error' => 'Valeur manquante'], json_decode($output, true));
    }
}
