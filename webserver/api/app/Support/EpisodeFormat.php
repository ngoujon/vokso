<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Format d'un épisode choisi à la création : durée visée (1 à 15 minutes) et
 * niveau de profondeur (1 = survol, 5 = expert). Construit aussi la consigne
 * de narration, sans plan imposé : le modèle structure l'épisode comme il
 * l'entend, seules la longueur et la profondeur sont cadrées.
 */
class EpisodeFormat
{
    public const MIN_MINUTES = 1;
    public const MAX_MINUTES = 15;
    public const DEFAULT_MINUTES = 5;

    public const LEVELS = [
        1 => 'Survol',
        2 => 'Découverte',
        3 => 'Approfondi',
        4 => 'Avancé',
        5 => 'Expert',
    ];
    public const DEFAULT_LEVEL = 2;

    /** Débit de la voix de synthèse (≈ 720 mots pour 5 minutes, mesuré en septembre 2026). */
    public const WORDS_PER_MINUTE = 145;

    private const LEVEL_GUIDANCE = [
        1 => 'Level 1/5 — quick overview. The listener knows nothing about the topic and just wants the gist: stay on the surface, keep only the essentials, use everyday words with no jargon, and favour vivid images over details.',
        2 => 'Level 2/5 — discovery. For a curious general audience: explain the basic concepts simply, define every technical term you use, and give concrete examples.',
        3 => 'Level 3/5 — in depth. For a listener who already has some general culture on the subject: go beyond the basics, explain how things work and why, bring in figures, nuances and several angles.',
        4 => 'Level 4/5 — advanced. For an informed audience: use the field\'s vocabulary (briefly defined when rare), go into mechanisms, data, methods, debates and recent research, without oversimplifying.',
        5 => 'Level 5/5 — expert. For specialists of the field: precise terminology without simplification, state of the art, quantitative details, competing theories or approaches, controversies, limits of current knowledge and open research questions. Skip the basics the audience already knows.',
    ];

    private static ?bool $columnsReady = null;

    /** Durée en minutes ; null si la valeur fournie n'est pas un entier dans les bornes. */
    public static function minutes(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return self::DEFAULT_MINUTES;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT);

        return $value !== false && $value >= self::MIN_MINUTES && $value <= self::MAX_MINUTES ? $value : null;
    }

    /** Niveau de 1 à 5 ; null si la valeur fournie est invalide. */
    public static function level(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return self::DEFAULT_LEVEL;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT);

        return $value !== false && isset(self::LEVELS[$value]) ? $value : null;
    }

    public static function levelLabel(?int $level): ?string
    {
        return $level !== null ? (self::LEVELS[$level] ?? null) : null;
    }

    /** « Expert · 12 min » ; null pour un épisode sans format enregistré. */
    public static function summary(?int $minutes, ?int $level): ?string
    {
        $parts = array_filter([
            self::levelLabel($level),
            $minutes ? $minutes.' min' : null,
        ]);

        return $parts ? implode(' · ', $parts) : null;
    }

    /**
     * Colonnes de format à ajouter à une requête sur `generations` (aucune
     * tant que la migration n'est pas appliquée).
     *
     * @return list<string>
     */
    public static function columns(string $alias = 'g'): array
    {
        $prefix = $alias !== '' ? $alias.'.' : '';

        return self::columnsReady() ? [$prefix.'duration_minutes', $prefix.'level'] : [];
    }

    /**
     * Nombre de mots visé pour une durée donnée.
     *
     * @return array{0: int, 1: int, 2: int} [minimum, cible, maximum]
     */
    public static function wordRange(int $minutes): array
    {
        $target = $minutes * self::WORDS_PER_MINUTE;

        return [(int) round($target * 0.93), $target, (int) round($target * 1.05)];
    }

    public static function wordCount(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * Les modèles écrivent volontiers plus court que demandé sur les formats
     * longs : un texte nettement trop court est renvoyé une fois au modèle
     * pour être développé jusqu'à la longueur visée.
     */
    public static function isTooShort(string $text, int $minutes): bool
    {
        return self::wordCount($text) < self::wordRange($minutes)[0] * 0.85;
    }

    public static function expansionPrompt(string $draft, string $subject, int $minutes, int $level): string
    {
        [$min, $target, $max] = self::wordRange($minutes);

        return implode(' ', [
            "Below is the French narration of a podcast episode on \"{$subject}\". It is too short: it has ".self::wordCount($draft)." words, but it must last about {$minutes} minutes when read aloud, i.e. between {$min} and {$max} words (aim for {$target}).",
            "Rewrite it in French at that length: develop it with new substance (explanations, examples, facts, angles), not filler or repetition, keeping the same tone and staying at this depth:",
            self::LEVEL_GUIDANCE[$level],
            "Keep or freely rework its structure. Write only the spoken text: no title, no headings, no lists, no Markdown, no comment about the rewrite.",
            "Draft:\n\n".$draft,
        ]);
    }

    /** Consigne de narration complète (le sujet y est inséré tel quel, déjà assaini). */
    public static function narrationPrompt(string $subject, int $minutes, int $level): string
    {
        [$min, $target, $max] = self::wordRange($minutes);
        $duration = $minutes === 1 ? '1 minute' : $minutes.' minutes';

        return implode(' ', [
            "Write, in French, the narration of a podcast episode on the topic below.",
            "Length: the text is read aloud by a text-to-speech voice and must last about {$duration}, so write between {$min} and {$max} words (aim for {$target}). This is a strict constraint: adapt the breadth of what you cover to this length.",
            self::LEVEL_GUIDANCE[$level],
            "Structure: you are entirely free to organise the episode as you see fit — narrative, chronological, questions and answers, a single striking angle, a tour of several aspects, or anything else — choose whatever best serves this topic, this length and this level. There is no mandatory outline.",
            "Do not open by restating or echoing the listener's request (avoid phrasing like \"Vous avez demandé...\" or starting with the topic verbatim as a heading): start directly as a podcast host would.",
            "If you are unsure about recent facts, figures or developments, use web search to verify them before writing; never mention the search in the narration.",
            "Write only the spoken text: no title, no headings, no lists, no Markdown or other markup, nothing that cannot be read aloud. Keep it accurate, fluid and engaging, with proper grammar.",
            "Topic: {$subject}",
        ]);
    }

    /**
     * Les colonnes level / duration_minutes arrivent par une migration SQL
     * appliquée à la main (SQL/20261005000000.sql), éventuellement après le
     * déploiement du code : d'ici là, on n'y lit ni n'y écrit rien et les
     * épisodes sont créés au format par défaut, plutôt que d'échouer.
     */
    public static function columnsReady(): bool
    {
        return self::$columnsReady ??= Schema::hasColumn('generations', 'level')
            && Schema::hasColumn('generations', 'duration_minutes')
            && Schema::hasColumn('generation_jobs', 'level')
            && Schema::hasColumn('generation_jobs', 'duration_minutes');
    }

    /** Réinitialise le cache de columnsReady() (tests). */
    public static function forgetColumns(): void
    {
        self::$columnsReady = null;
    }
}
