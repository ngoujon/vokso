<?php

namespace App\Utils;

use PDO;

/**
 * Quota mensuel de générations par compte : le service est gratuit, ce
 * plafond est le seul garde-fou contre l'abus des appels IA payants côté
 * serveur (en plus du RateLimiter par IP).
 *
 * On compte les jobs (generation_jobs) et non les générations terminées :
 * sinon un utilisateur pourrait lancer N jobs en parallèle avant que le
 * premier ne soit comptabilisé. Les jobs en erreur ne sont pas décomptés.
 */
class GenerationQuota
{
    public function __construct(private PDO $db, private int $monthlyLimit)
    {
    }

    public function limit(): int
    {
        return $this->monthlyLimit;
    }

    public function usedThisMonth(int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM generation_jobs
             WHERE user_id = :user_id AND status <> 'error' AND created_at >= :month_start"
        );
        $stmt->execute([':user_id' => $userId, ':month_start' => date('Y-m-01 00:00:00')]);

        return (int) $stmt->fetchColumn();
    }

    /** Les administrateurs ne sont pas plafonnés (tests, démonstrations). */
    public function isReached(array $user): bool
    {
        if (($user['role'] ?? null) === 'admin') {
            return false;
        }

        return $this->usedThisMonth((int) $user['id']) >= $this->monthlyLimit;
    }

    public static function fromEnv(PDO $db): self
    {
        return new self($db, max(0, (int) ($_ENV['GENERATION_MONTHLY_QUOTA'] ?? 5)));
    }
}
