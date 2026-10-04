<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Catégories ayant au moins un épisode publié, avec leur slug d'URL
 * (/discotheque/{slug}) : partagé par les pages catégorie, le sitemap et llms-full.txt.
 */
class CategoryIndex
{
    /** Une page catégorie n'est indexée qu'à partir de ce nombre d'épisodes (pas de page « mince »). */
    public const MIN_EPISODES_TO_INDEX = 2;

    /** @return list<object{id: int, label: string, slug: string, count: int}> */
    public static function all(): array
    {
        return DB::table('categorie as c')
            ->join('generations as g', function ($join) {
                $join->on('g.idcategorie', '=', 'c.idcategorie')->where('g.statut', '=', 'on');
            })
            ->groupBy('c.idcategorie', 'c.label')
            ->orderBy('c.label')
            ->get(['c.idcategorie as id', 'c.label', DB::raw('COUNT(g.id) as count')])
            ->map(fn ($row) => (object) [
                'id' => (int) $row->id,
                'label' => (string) $row->label,
                'slug' => EpisodeText::slugify((string) $row->label),
                'count' => (int) $row->count,
            ])
            ->filter(fn ($row) => $row->slug !== '')
            ->values()
            ->all();
    }

    public static function find(string $slug): ?object
    {
        foreach (self::all() as $category) {
            if ($category->slug === $slug) {
                return $category;
            }
        }

        return null;
    }
}
