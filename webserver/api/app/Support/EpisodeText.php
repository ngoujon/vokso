<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Mise en forme partagée des titres/extraits d'épisode (pages publiques, sitemap, génération). */
class EpisodeText
{
    /** Longueur maximale d'un slug d'URL (assez pour un titre complet, sans URL à rallonge). */
    public const SLUG_MAX_LENGTH = 80;

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

    /**
     * « Les arbres parlent-ils sous terre ? » -> « les-arbres-parlent-ils-sous-terre ».
     * Tronqué sur une limite de mot : un slug coupé au milieu d'un mot se lit mal.
     */
    public static function slugify(string $text, int $maxLength = self::SLUG_MAX_LENGTH): string
    {
        // Str::ascii (et non iconv //TRANSLIT, dont le résultat dépend de la
        // locale du système : « é » -> « 'e » ou « ? ») : « cœur » -> « coeur ».
        $slug = strtolower(Str::ascii($text, 'fr'));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        if (strlen($slug) > $maxLength) {
            $cut = substr($slug, 0, $maxLength + 1);
            $lastHyphen = strrpos($cut, '-');
            $slug = $lastHyphen !== false && $lastHyphen > $maxLength / 2 ? substr($cut, 0, $lastHyphen) : substr($slug, 0, $maxLength);
        }

        return trim($slug, '-');
    }

    public static function excerpt(string $text, int $length): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($clean) <= $length) {
            return $clean;
        }

        return mb_substr($clean, 0, $length - 1).'…';
    }

    /**
     * Meta description : les premières phrases entières tenant dans $length
     * caractères (taille affichée par les moteurs), sinon une coupe sur un mot.
     */
    public static function metaDescription(string $text, int $length = 155): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($clean) <= $length) {
            return $clean;
        }

        $sentences = preg_split('/(?<=[.!?…])\s+/u', $clean) ?: [];
        $description = '';
        foreach ($sentences as $sentence) {
            $candidate = $description === '' ? $sentence : $description.' '.$sentence;
            if (mb_strlen($candidate) > $length) {
                break;
            }
            $description = $candidate;
        }
        if (mb_strlen($description) >= 70) {
            return $description;
        }

        $cut = mb_substr($clean, 0, $length - 1);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim($lastSpace !== false ? mb_substr($cut, 0, $lastSpace) : $cut, " ,;:—-").'…';
    }

    /**
     * Chemin public d'un épisode : /podcast/{slug}. Les épisodes sans slug
     * (pas encore migrés) gardent l'ancienne forme /podcast/{id}-{titre}.
     */
    public static function episodePath(?string $slug, string $id = '', string $title = ''): string
    {
        if ($slug !== null && $slug !== '') {
            return '/podcast/'.$slug;
        }
        $legacy = self::slugify(self::cleanTitle($title));

        return '/podcast/'.$id.($legacy !== '' ? '-'.$legacy : '');
    }

    public static function episodeUrl(string $publicUrl, ?string $slug, string $id = '', string $title = ''): string
    {
        return $publicUrl.self::episodePath($slug, $id, $title);
    }

    /** Page d'une catégorie : /discotheque/{slug}. */
    public static function categoryPath(string $label): string
    {
        return '/discotheque/'.self::slugify($label);
    }
}
