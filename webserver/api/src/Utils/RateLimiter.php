<?php

namespace App\Utils;

use PDO;

/**
 * Limiteur de débit par IP et par route, adossé à la base de données (pas de
 * dépendance supplémentaire comme Redis) : chaque appel compte les requêtes
 * récentes de l'IP sur la fenêtre glissante, puis journalise l'appel courant.
 */
class RateLimiter
{
    public function __construct(
        private PDO $db,
        private int $maxRequests,
        private int $windowSeconds
    ) {
    }

    public function tooManyRequests(string $ip, string $route): bool
    {
        $since = date('Y-m-d H:i:s', time() - $this->windowSeconds);

        // Purge des entrées expirées pour cette route : évite de faire grossir
        // la table indéfiniment sans tâche cron dédiée.
        $this->db
            ->prepare('DELETE FROM rate_limit WHERE route = :route AND requested_at < :since')
            ->execute([':route' => $route, ':since' => $since]);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM rate_limit WHERE ip_address = :ip AND route = :route AND requested_at >= :since'
        );
        $stmt->execute([':ip' => $ip, ':route' => $route, ':since' => $since]);

        if ((int) $stmt->fetchColumn() >= $this->maxRequests) {
            return true;
        }

        $this->db
            ->prepare('INSERT INTO rate_limit (ip_address, route, requested_at) VALUES (:ip, :route, NOW())')
            ->execute([':ip' => $ip, ':route' => $route]);

        return false;
    }
}
