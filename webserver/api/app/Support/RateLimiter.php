<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Limiteur de débit par IP et par route, adossé à la table rate_limit (pas
 * de cache ni de Redis à provisionner sur le serveur) : chaque appel compte
 * les requêtes récentes de l'IP sur la fenêtre glissante, puis journalise
 * l'appel courant.
 */
class RateLimiter
{
    public function __construct(private int $maxRequests, private int $windowSeconds)
    {
    }

    public static function fromConfig(): self
    {
        return new self(
            (int) config('vokso.rate_limit.max_requests'),
            (int) config('vokso.rate_limit.window_seconds')
        );
    }

    public function tooManyRequests(string $ip, string $route): bool
    {
        $since = now()->subSeconds($this->windowSeconds)->format('Y-m-d H:i:s');

        // Purge des entrées expirées pour cette route : évite de faire grossir
        // la table indéfiniment sans tâche planifiée dédiée.
        DB::table('rate_limit')->where('route', $route)->where('requested_at', '<', $since)->delete();

        $count = DB::table('rate_limit')
            ->where('ip_address', $ip)
            ->where('route', $route)
            ->where('requested_at', '>=', $since)
            ->count();

        if ($count >= $this->maxRequests) {
            return true;
        }

        DB::table('rate_limit')->insert([
            'ip_address' => $ip,
            'route' => $route,
            'requested_at' => now()->format('Y-m-d H:i:s'),
        ]);

        return false;
    }
}
