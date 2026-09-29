<?php

namespace Tests\Utils;

use App\Utils\GenerationQuota;
use PDO;
use PHPUnit\Framework\TestCase;

class GenerationQuotaTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec(
            'CREATE TABLE generation_jobs (
                job_id VARCHAR(64) PRIMARY KEY,
                user_id INTEGER,
                status VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL
            )'
        );
    }

    private function addJob(int $userId, string $status, string $createdAt): void
    {
        $this->db->prepare('INSERT INTO generation_jobs (job_id, user_id, status, created_at) VALUES (:id, :user_id, :status, :created_at)')
            ->execute([':id' => bin2hex(random_bytes(8)), ':user_id' => $userId, ':status' => $status, ':created_at' => $createdAt]);
    }

    public function testCountsOnlyNonFailedJobsOfTheCurrentMonth(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->addJob(1, 'done', $now);
        $this->addJob(1, 'pending', $now);
        $this->addJob(1, 'error', $now);
        $this->addJob(1, 'done', date('Y-m-d H:i:s', strtotime(date('Y-m-01') . ' -1 day')));
        $this->addJob(2, 'done', $now);

        $this->assertSame(2, (new GenerationQuota($this->db, 5))->usedThisMonth(1));
    }

    public function testIsReachedAtTheLimit(): void
    {
        $quota = new GenerationQuota($this->db, 2);
        $user = ['id' => 1, 'role' => 'user'];

        $this->addJob(1, 'done', date('Y-m-d H:i:s'));
        $this->assertFalse($quota->isReached($user));

        $this->addJob(1, 'processing', date('Y-m-d H:i:s'));
        $this->assertTrue($quota->isReached($user));
    }

    public function testAdminsAreNotCapped(): void
    {
        $quota = new GenerationQuota($this->db, 0);

        $this->assertFalse($quota->isReached(['id' => 1, 'role' => 'admin']));
        $this->assertTrue($quota->isReached(['id' => 2, 'role' => 'user']));
    }
}
