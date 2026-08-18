<?php

namespace Tests\Utils;

use App\Utils\RateLimiter;
use PDO;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    private function makeDb(): PDO
    {
        // PDO::sqliteCreateFunction() est dépréciée depuis PHP 8.5 au profit de
        // Pdo\Sqlite::createFunction(), qui n'existe pas avant PHP 8.4 et
        // nécessite d'instancier directement cette sous-classe.
        $sqliteClass = class_exists('Pdo\\Sqlite') ? 'Pdo\\Sqlite' : PDO::class;
        $db = new $sqliteClass('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // SQLite n'a pas de NOW() natif, contrairement à MySQL utilisé en prod.
        if ($db instanceof \PDO && method_exists($db, 'createFunction')) {
            $db->createFunction('NOW', fn () => date('Y-m-d H:i:s'));
        } else {
            $db->sqliteCreateFunction('NOW', fn () => date('Y-m-d H:i:s'));
        }
        $db->exec(
            'CREATE TABLE rate_limit (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                route VARCHAR(50) NOT NULL,
                requested_at DATETIME NOT NULL
            )'
        );

        return $db;
    }

    public function testAllowsRequestsUnderTheLimit(): void
    {
        $limiter = new RateLimiter($this->makeDb(), 3, 3600);

        $this->assertFalse($limiter->tooManyRequests('1.2.3.4', 'generation'));
        $this->assertFalse($limiter->tooManyRequests('1.2.3.4', 'generation'));
        $this->assertFalse($limiter->tooManyRequests('1.2.3.4', 'generation'));
    }

    public function testBlocksOnceLimitIsReached(): void
    {
        $limiter = new RateLimiter($this->makeDb(), 2, 3600);

        $this->assertFalse($limiter->tooManyRequests('1.2.3.4', 'generation'));
        $this->assertFalse($limiter->tooManyRequests('1.2.3.4', 'generation'));
        $this->assertTrue($limiter->tooManyRequests('1.2.3.4', 'generation'));
    }

    public function testTracksEachIpIndependently(): void
    {
        $limiter = new RateLimiter($this->makeDb(), 1, 3600);

        $this->assertFalse($limiter->tooManyRequests('1.2.3.4', 'generation'));
        $this->assertFalse($limiter->tooManyRequests('5.6.7.8', 'generation'));
        $this->assertTrue($limiter->tooManyRequests('1.2.3.4', 'generation'));
    }

    public function testTracksEachRouteIndependently(): void
    {
        $limiter = new RateLimiter($this->makeDb(), 1, 3600);

        $this->assertFalse($limiter->tooManyRequests('1.2.3.4', 'generation'));
        $this->assertFalse($limiter->tooManyRequests('1.2.3.4', 'search'));
    }

    public function testExpiredEntriesAreNotCounted(): void
    {
        $db = $this->makeDb();
        $db->prepare('INSERT INTO rate_limit (ip_address, route, requested_at) VALUES (:ip, :route, :time)')
            ->execute([
                ':ip' => '1.2.3.4',
                ':route' => 'generation',
                ':time' => date('Y-m-d H:i:s', time() - 7200),
            ]);

        $limiter = new RateLimiter($db, 1, 3600);

        $this->assertFalse($limiter->tooManyRequests('1.2.3.4', 'generation'));
    }
}
