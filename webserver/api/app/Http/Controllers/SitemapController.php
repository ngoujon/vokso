<?php

namespace App\Http\Controllers;

use App\Support\CategoryIndex;
use App\Support\EpisodeText;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sitemap dynamique (/sitemap.xml réécrit vers /api/sitemap par
 * site/.htaccess) : chaque épisode publié obtient son URL /podcast/{slug},
 * chaque catégorie assez fournie sa page /discotheque/{slug}.
 */
class SitemapController extends Controller
{
    // Sous la limite du protocole sitemap (50 000 URLs).
    private const MAX_EPISODES = 45000;

    public function show(): Response
    {
        $base = config('vokso.public_url');
        $urls = [
            ['loc' => $base.'/', 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => $base.'/discotheque', 'changefreq' => 'daily', 'priority' => '0.8'],
            ['loc' => $base.'/comment-ca-marche', 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => $base.'/app/contact', 'changefreq' => 'monthly', 'priority' => '0.3'],
            ['loc' => $base.'/app/politique-de-confidentialite', 'changefreq' => 'yearly', 'priority' => '0.2'],
        ];

        try {
            foreach (CategoryIndex::all() as $category) {
                if ($category->count >= CategoryIndex::MIN_EPISODES_TO_INDEX) {
                    $urls[] = ['loc' => $base.EpisodeText::categoryPath($category->label), 'changefreq' => 'weekly', 'priority' => '0.7'];
                }
            }

            $episodes = DB::table('generations')
                ->where('statut', 'on')
                ->orderByDesc('created_at')
                ->limit(self::MAX_EPISODES)
                ->get(['generation_id', 'slug', 'title', 'created_at']);

            foreach ($episodes as $episode) {
                $urls[] = [
                    'loc' => EpisodeText::episodeUrl($base, $episode->slug, $episode->generation_id, (string) $episode->title),
                    'lastmod' => date('c', strtotime((string) $episode->created_at)),
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                ];
            }
        } catch (Throwable $e) {
            // Un sitemap partiel reste préférable à une erreur 500.
            report($e);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $xml .= "  <url>\n    <loc>".htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8')."</loc>\n";
            if (isset($url['lastmod'])) {
                $xml .= '    <lastmod>'.$url['lastmod']."</lastmod>\n";
            }
            $xml .= '    <changefreq>'.$url['changefreq']."</changefreq>\n";
            $xml .= '    <priority>'.$url['priority']."</priority>\n  </url>\n";
        }
        $xml .= "</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
