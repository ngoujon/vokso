<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Durée attendue d'une génération, affichée au visiteur pendant l'attente
 * (temps restant). Tirée des dernières générations réussies du même format,
 * pour suivre la vitesse réelle des modèles plutôt qu'une valeur figée.
 */
final class GenerationEstimate
{
    // Échantillon : assez pour lisser un appel lent, assez récent pour suivre les modèles.
    private const SAMPLE = 20;
    private const MIN_SAMPLES = 3;
    private const MIN_SECONDS = 20;
    private const MAX_SECONDS = 900;

    /** Repli sans historique, calé sur les mesures de production (≈ 125 s pour 5 min). */
    public static function fallback(int $minutes, string $sourceType): int
    {
        return 45 + 16 * $minutes + ($sourceType === 'audio' ? 15 : 0);
    }

    /** Durée totale attendue, en secondes. */
    public static function seconds(?int $minutes, string $sourceType): int
    {
        $minutes = $minutes ?? EpisodeFormat::DEFAULT_MINUTES;

        $durations = DB::table('generation_jobs')
            ->where('status', 'done')
            ->where('source_type', $sourceType)
            ->where('duration_minutes', $minutes)
            ->orderByDesc('created_at')
            ->limit(self::SAMPLE)
            ->get(['created_at', 'updated_at'])
            ->map(fn ($row) => Carbon::parse($row->updated_at)->getTimestamp() - Carbon::parse($row->created_at)->getTimestamp())
            ->filter(fn (int $s) => $s > 0)
            ->sort()
            ->values();

        $seconds = $durations->count() >= self::MIN_SAMPLES
            ? (int) $durations[intdiv($durations->count(), 2)]
            : self::fallback($minutes, $sourceType);

        return max(self::MIN_SECONDS, min(self::MAX_SECONDS, $seconds));
    }
}
