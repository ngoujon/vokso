<?php

namespace App\Support;

use App\Models\GenerationJob;
use App\Models\User;

/**
 * Quota mensuel de générations par compte : le service est gratuit, ce
 * plafond est le garde-fou contre l'abus des appels IA payants côté serveur
 * (en plus du RateLimiter par IP).
 *
 * On compte les jobs et non les générations terminées : sinon un utilisateur
 * pourrait lancer N jobs en parallèle avant que le premier ne soit
 * comptabilisé. Les jobs en erreur ne sont pas décomptés.
 */
class GenerationQuota
{
    public function __construct(private int $monthlyLimit)
    {
    }

    public static function fromConfig(): self
    {
        return new self((int) config('vokso.generation_monthly_quota'));
    }

    public function limit(): int
    {
        return $this->monthlyLimit;
    }

    public function usedThisMonth(int $userId): int
    {
        return GenerationJob::query()
            ->where('user_id', $userId)
            ->where('status', '<>', 'error')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    /** Les administrateurs ne sont pas plafonnés (tests, démonstrations). */
    public function isReached(User $user): bool
    {
        if ($user->isAdmin()) {
            return false;
        }

        return $this->usedThisMonth($user->id) >= $this->monthlyLimit;
    }
}
