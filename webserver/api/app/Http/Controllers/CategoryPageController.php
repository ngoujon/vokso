<?php

namespace App\Http\Controllers;

use App\Support\CategoryIndex;
use App\Support\EpisodeText;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Page d'une catégorie de la discothèque (/discotheque/{slug}, réécrite vers
 * /api/discotheque/... par site/.htaccess), rendue côté serveur : la liste des
 * épisodes est dans le HTML, lisible par les moteurs et par les robots d'IA
 * qui n'exécutent pas le JavaScript (la discothèque statique les charge en JS).
 */
class CategoryPageController extends Controller
{
    private const MAX_EPISODES = 200;

    public function show(string $slug): Response
    {
        try {
            $category = CategoryIndex::find(trim($slug, '/'));
            $episodes = $category ? DB::table('generations')
                ->where('statut', 'on')
                ->where('idcategorie', $category->id)
                ->orderByDesc('created_at')
                ->limit(self::MAX_EPISODES)
                ->get(['generation_id', 'slug', 'title', 'text_content', 'image_url', 'created_at']) : collect();
        } catch (Throwable $e) {
            report($e);
            $category = null;
        }

        if (! $category || $episodes->isEmpty()) {
            return response()->view('podcast.not-found', [], 404)
                ->header('Content-Type', 'text/html; charset=utf-8')
                ->header('X-Robots-Tag', 'noindex');
        }

        $base = config('vokso.public_url');
        $canonical = $base.EpisodeText::categoryPath($category->label);
        $items = $episodes->map(function ($row) use ($base) {
            $title = EpisodeText::cleanTitle((string) $row->title) ?: 'Épisode Vokso';

            return [
                'title' => $title,
                'url' => EpisodeText::episodeUrl($base, $row->slug, $row->generation_id, $title),
                'path' => EpisodeText::episodePath($row->slug, $row->generation_id, $title),
                'excerpt' => EpisodeText::metaDescription((string) $row->text_content, 180),
                'image' => $row->image_url ? $base.'/static/images/'.rawurlencode((string) $row->image_url) : null,
                'date' => $row->created_at ? date('d/m/Y', strtotime((string) $row->created_at)) : '',
            ];
        })->all();

        $count = count($items);
        $title = 'Podcasts '.$category->label.' : '.$count.' épisode'.($count > 1 ? 's' : '').' à écouter';
        $description = 'Écoutez gratuitement '.$count.' podcast'.($count > 1 ? 's' : '').' Vokso sur le thème « '.$category->label.' » : '
            .implode(', ', array_slice(array_column($items, 'title'), 0, 3)).($count > 3 ? '…' : '.');
        $breadcrumb = [['Accueil', $base.'/'], ['Discothèque', $base.'/discotheque'], [$category->label, $canonical]];

        $jsonLd = ['@context' => 'https://schema.org', '@graph' => [
            [
                '@type' => 'CollectionPage',
                '@id' => $canonical.'#page',
                'name' => $title,
                'url' => $canonical,
                'description' => EpisodeText::metaDescription($description),
                'inLanguage' => 'fr-FR',
                'isPartOf' => ['@type' => 'WebSite', 'name' => 'Vokso', 'url' => $base.'/'],
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'numberOfItems' => $count,
                    'itemListElement' => array_map(fn ($item, $i) => [
                        '@type' => 'ListItem', 'position' => $i + 1, 'url' => $item['url'], 'name' => $item['title'],
                    ], $items, array_keys($items)),
                ],
            ],
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_map(fn ($item, $i) => [
                    '@type' => 'ListItem', 'position' => $i + 1, 'name' => $item[0], 'item' => $item[1],
                ], $breadcrumb, array_keys($breadcrumb)),
            ],
        ]];

        $others = array_values(array_filter(CategoryIndex::all(), fn ($c) => $c->id !== $category->id));

        $response = response()->view('podcast.category', [
            'label' => $category->label,
            'title' => $title,
            'description' => EpisodeText::metaDescription($description),
            'canonical' => $canonical,
            'indexable' => $count >= CategoryIndex::MIN_EPISODES_TO_INDEX,
            'items' => $items,
            'breadcrumb' => $breadcrumb,
            'others' => array_map(fn ($c) => ['label' => $c->label, 'path' => EpisodeText::categoryPath($c->label), 'count' => $c->count], $others),
            'jsonLd' => json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP),
        ])->header('Content-Type', 'text/html; charset=utf-8');
        $response->setPublic()->setMaxAge(1800);

        return $response;
    }
}
