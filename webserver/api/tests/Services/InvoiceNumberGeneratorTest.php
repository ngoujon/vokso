<?php

namespace Tests\Services;

use App\Services\InvoiceNumberGenerator;
use PDO;
use PHPUnit\Framework\TestCase;

class InvoiceNumberGeneratorTest extends TestCase
{
    private function makeDb(): PDO
    {
        $sqliteClass = class_exists('Pdo\\Sqlite') ? 'Pdo\\Sqlite' : PDO::class;
        $db = new $sqliteClass('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE invoice_counters (year INTEGER PRIMARY KEY, last_number INTEGER NOT NULL DEFAULT 0)');

        return $db;
    }

    public function testFirstNumberOfTheYearStartsAtOne(): void
    {
        $generator = new InvoiceNumberGenerator($this->makeDb());

        $this->assertSame('VOKSO-2026-000001', $generator->next(2026));
    }

    public function testNumbersIncrementSequentiallyWithoutGaps(): void
    {
        $generator = new InvoiceNumberGenerator($this->makeDb());

        $this->assertSame('VOKSO-2026-000001', $generator->next(2026));
        $this->assertSame('VOKSO-2026-000002', $generator->next(2026));
        $this->assertSame('VOKSO-2026-000003', $generator->next(2026));
    }

    public function testEachYearHasItsOwnIndependentSequence(): void
    {
        $generator = new InvoiceNumberGenerator($this->makeDb());

        $this->assertSame('VOKSO-2026-000001', $generator->next(2026));
        $this->assertSame('VOKSO-2027-000001', $generator->next(2027));
        $this->assertSame('VOKSO-2026-000002', $generator->next(2026));
    }
}
