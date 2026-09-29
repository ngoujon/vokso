<?php

namespace App\Http\Controllers;

use App\Support\EpisodeText;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Page publique d'un épisode, rendue côté serveur (/podcast/{id}-{slug},
 * réécrite vers /api/podcast/... par site/.htaccess) : le HTML contient tout
 * le contenu au premier octet (titre, transcript, JSON-LD PodcastEpisode),
 * sans JavaScript, pour être indexable et citable.
 *
 * Les vues sont des gabarits PHP simples (pas Blade) : aucune compilation,
 * donc aucune écriture disque au rendu.
 */
class PodcastPageController extends Controller
{
    /** @param string $path ex. "gen_6ab267b302f6d-jupiter-la-geante" (generation_id est un VARCHAR). */
    public function show(string $path): Response
    {
        if (! preg_match('#^([A-Za-z0-9_]+)#', $path, $matches)) {
            return $this->notFound();
        }

        try {
            $episode = DB::table('generations as g')
                ->leftJoin('categorie as c', 'c.idcategorie', '=', 'g.idcategorie')
                ->where('g.generation_id', $matches[1])
                ->where('g.statut', 'on')
                ->first(['g.generation_id as id', 'g.title', 'g.text_content', 'g.image_url', 'g.audio_url', 'g.created_at', 'c.label as category']);
        } catch (Throwable $e) {
            report($e);

            return $this->notFound();
        }

        if (! $episode) {
            return $this->notFound();
        }

        $base = config('vokso.public_url');
        $title = EpisodeText::cleanTitle((string) $episode->title) ?: 'Épisode Vokso';
        $canonical = EpisodeText::episodeUrl($base, $episode->id, $title);
        $description = EpisodeText::excerpt((string) $episode->text_content, 200);
        $imageUrl = $base.'/static/images/'.rawurlencode((string) $episode->image_url);
        $audioUrl = $base.'/static/audios/'.rawurlencode((string) $episode->audio_url);
        $category = trim((string) $episode->category);
        $publishedAt = $episode->created_at ? strtotime((string) $episode->created_at) : null;

        $jsonLd = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'PodcastEpisode',
            'name' => $title,
            'url' => $canonical,
            'description' => $description,
            'datePublished' => $publishedAt ? date('c', $publishedAt) : null,
            'image' => $imageUrl,
            'associatedMedia' => ['@type' => 'MediaObject', 'contentUrl' => $audioUrl],
            'partOfSeries' => ['@type' => 'PodcastSeries', 'name' => 'Vokso', 'url' => $base.'/'],
            'genre' => $category !== '' ? $category : null,
        ], fn ($value) => $value !== null);

        return response()->view('podcast.show', [
            'title' => $title,
            'canonical' => $canonical,
            'description' => $description,
            'imageUrl' => $episode->image_url ? $imageUrl : null,
            'audioUrl' => $episode->audio_url ? $audioUrl : null,
            'category' => $category,
            'publishedHuman' => $publishedAt ? date('d/m/Y', $publishedAt) : '',
            'text' => (string) $episode->text_content,
            'jsonLd' => json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP),
        ])->header('Content-Type', 'text/html; charset=utf-8');
    }

    private function notFound(): Response
    {
        return response()->view('podcast.not-found', [], 404)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('X-Robots-Tag', 'noindex');
    }
}
