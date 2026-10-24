<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Garde-fou de dépense IA, appliqué à chaque appel payant envoyé à l'API
 * (voir ApiClient), quel que soit l'appelant : visiteur, n8n, commande
 * artisan, script lancé à la main. Une boucle qui s'emballe (relances,
 * régénération d'images en série...) est coupée net au plafond, au lieu de
 * vider le crédit Mistral.
 *
 * Plafonds en nombre d'appels sur 1 h et 24 h glissantes, par type d'appel
 * (config vokso.ai_budget). Les appels sont comptés dans la table rate_limit,
 * sous la pseudo-adresse « ai ». Aucune exemption, administrateurs compris :
 * pour un besoin ponctuel, relever le plafond dans .env.
 */
final class AiBudget
{
    public const KINDS = ['text', 'image', 'speech', 'transcription', 'moderation'];

    private const KEY = 'ai';

    /** Compte un appel de ce type, ou lève AiBudgetExceeded si un plafond est atteint. */
    public static function consume(string $kind): void
    {
        $kind = in_array($kind, self::KINDS, true) ? $kind : 'text';

        foreach (['hourly' => 3600, 'daily' => 86400] as $period => $seconds) {
            $max = (int) config("vokso.ai_budget.$kind.$period", 0);
            if ($max <= 0) {
                continue;
            }
            $limiter = new RateLimiter($max, $seconds);
            if ($limiter->isLimited(self::KEY, self::route($kind, $period))) {
                throw new AiBudgetExceeded(sprintf(
                    'Garde-fou IA : plafond de %d appels « %s » sur %s atteint, appel bloqué.',
                    $max,
                    $kind,
                    $period === 'hourly' ? '1 h' : '24 h'
                ));
            }
        }

        foreach (['hourly' => 3600, 'daily' => 86400] as $period => $seconds) {
            if ((int) config("vokso.ai_budget.$kind.$period", 0) > 0) {
                (new RateLimiter(1, $seconds))->hit(self::KEY, self::route($kind, $period));
            }
        }
    }

    /**
     * Compte après coup des appels déjà facturés (ex. images enchaînées par
     * un agent dans une seule conversation) : rien à bloquer, mais les
     * appels suivants verront le plafond atteint.
     */
    public static function record(string $kind, int $count): void
    {
        $kind = in_array($kind, self::KINDS, true) ? $kind : 'text';
        for ($i = 0; $i < $count; $i++) {
            foreach (['hourly' => 3600, 'daily' => 86400] as $period => $seconds) {
                if ((int) config("vokso.ai_budget.$kind.$period", 0) > 0) {
                    (new RateLimiter(1, $seconds))->hit(self::KEY, self::route($kind, $period));
                }
            }
        }
    }

    /** Appels comptés et plafonds, pour l'administration. */
    public static function usage(): array
    {
        $usage = [];
        foreach (self::KINDS as $kind) {
            foreach (['hourly' => 3600, 'daily' => 86400] as $period => $seconds) {
                $usage[$kind][$period] = [
                    'used' => DB::table('rate_limit')
                        ->where('ip_address', self::KEY)
                        ->where('route', self::route($kind, $period))
                        ->where('requested_at', '>=', now()->subSeconds($seconds)->format('Y-m-d H:i:s'))
                        ->count(),
                    'max' => (int) config("vokso.ai_budget.$kind.$period", 0),
                ];
            }
        }

        return $usage;
    }

    private static function route(string $kind, string $period): string
    {
        return 'ai-'.$kind.'-'.$period;
    }
}
