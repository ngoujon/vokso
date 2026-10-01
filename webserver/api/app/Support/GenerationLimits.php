<?php

namespace App\Support;

use App\Models\GenerationJob;
use App\Models\User;

/**
 * Garde-fous de la génération, ouverte à tous sans compte : chaque podcast
 * déclenche plusieurs appels IA payants.
 *
 * - par IP, sur une heure et sur 24 h glissantes : largement assez pour un
 *   usage normal, bloque un script qui enchaîne les demandes ;
 * - global sur 24 h, toutes IP confondues : plafonne la facture même face à
 *   un abus réparti sur de nombreuses adresses. On compte les jobs (pas les
 *   générations terminées) hors erreurs, pour que des demandes lancées en
 *   rafale soient comptées tout de suite.
 *
 * Les administrateurs connectés ne sont pas plafonnés (tests, démonstrations).
 */
class GenerationLimits
{
    private const HOURLY_ROUTE = 'generation';
    private const DAILY_ROUTE = 'generation-day';

    public function __construct(
        private int $perIpHourly,
        private int $perIpDaily,
        private int $globalDaily
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            (int) config('vokso.generation_limits.per_ip_hourly'),
            (int) config('vokso.generation_limits.per_ip_daily'),
            (int) config('vokso.generation_limits.global_daily')
        );
    }

    /**
     * Message de refus, ou null si la génération est autorisée : elle est
     * alors comptée dans les limites par IP. Une demande refusée n'est pas
     * comptée, pour ne pas prolonger le blocage d'un utilisateur qui réessaie.
     */
    public function refusal(?User $user, string $ip): ?string
    {
        if ($user?->isAdmin()) {
            return null;
        }

        if ($this->globalUsage() >= $this->globalDaily) {
            return 'Vokso a atteint son nombre maximum de podcasts pour aujourd\'hui. Merci de réessayer demain.';
        }

        $daily = new RateLimiter($this->perIpDaily, 86400);
        if ($daily->isLimited($ip, self::DAILY_ROUTE)) {
            return sprintf('Limite de %d podcasts par jour atteinte. Merci de réessayer demain.', $this->perIpDaily);
        }

        $hourly = new RateLimiter($this->perIpHourly, 3600);
        if ($hourly->isLimited($ip, self::HOURLY_ROUTE)) {
            return sprintf('Limite de %d podcasts par heure atteinte. Merci de réessayer un peu plus tard.', $this->perIpHourly);
        }

        $daily->hit($ip, self::DAILY_ROUTE);
        $hourly->hit($ip, self::HOURLY_ROUTE);

        return null;
    }

    public function globalUsage(): int
    {
        return GenerationJob::query()
            ->where('status', '<>', 'error')
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }
}
