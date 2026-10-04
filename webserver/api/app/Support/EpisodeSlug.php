<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Slug unique d'un épisode, tiré de son titre : /podcast/les-arbres-parlent-ils-sous-terre.
 * En cas de titre déjà pris, un suffixe numérique départage (-2, -3...).
 */
class EpisodeSlug
{
    public static function forTitle(string $title, ?string $exceptGenerationId = null): string
    {
        $base = EpisodeText::slugify(EpisodeText::cleanTitle($title));
        if ($base === '') {
            $base = 'episode';
        }

        $slug = $base;
        for ($suffix = 2; self::taken($slug, $exceptGenerationId); $suffix++) {
            $slug = EpisodeText::slugify($base, EpisodeText::SLUG_MAX_LENGTH - strlen((string) $suffix) - 1).'-'.$suffix;
        }

        return $slug;
    }

    private static function taken(string $slug, ?string $exceptGenerationId): bool
    {
        return DB::table('generations')
            ->where('slug', $slug)
            ->when($exceptGenerationId !== null, fn ($q) => $q->where('generation_id', '<>', $exceptGenerationId))
            ->exists();
    }
}
