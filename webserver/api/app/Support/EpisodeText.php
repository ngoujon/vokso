<?php

namespace App\Support;

/** Mise en forme partagée des titres/extraits d'épisode (pages publiques, sitemap, génération). */
class EpisodeText
{
    /** Titre sur une ligne, sans guillemets englobants ni Markdown. */
    public static function cleanTitle(string $rawTitle): string
    {
        $title = trim($rawTitle, " \t\n\r\0\x0B\"'“”«»");
        // Titres/emphases Markdown ("# ", "**gras**", "_italique_", "`code`")
        // et tirets de liste en tête de ligne.
        $title = preg_replace('/^#{1,6}\s+/', '', $title);
        $title = preg_replace('/^[-*+]\s+/', '', $title);
        $title = preg_replace('/(\*\*|__)(.*?)\1/', '$2', $title);
        $title = preg_replace('/(\*|_|`)(.*?)\1/', '$2', $title);
        $title = str_replace(['*', '#', '`'], '', $title);
        $title = preg_replace('/\s+/', ' ', $title);

        return trim($title);
    }

    public static function slugify(string $text): string
    {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        if ($transliterated === false) {
            $transliterated = $text;
        }
        $slug = strtolower($transliterated);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        return mb_substr(trim($slug, '-'), 0, 80);
    }

    public static function excerpt(string $text, int $length): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($clean) <= $length) {
            return $clean;
        }

        return mb_substr($clean, 0, $length - 1).'…';
    }

    /** URL publique d'un épisode : /podcast/{id}-{slug}. */
    public static function episodeUrl(string $publicUrl, string $id, string $title): string
    {
        $slug = self::slugify(self::cleanTitle($title));

        return $publicUrl.'/podcast/'.$id.($slug !== '' ? '-'.$slug : '');
    }
}
