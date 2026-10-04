<?php

namespace App\Http\Controllers;

use App\Support\EpisodeText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Page publique d'un épisode, rendue côté serveur (/podcast/{slug}, réécrite
 * vers /api/podcast/... par site/.htaccess) : le HTML contient tout le contenu
 * au premier octet (titre, transcript, JSON-LD PodcastEpisode + fil d'Ariane,
 * épisodes liés), sans JavaScript, pour être indexable et citable, y compris
 * par les robots d'IA qui n'exécutent pas le JavaScript.
 *
 * Les anciennes URL /podcast/{generation_id}-{titre} redirigent en 301 vers
 * l'URL lisible, pour conserver les liens et le référencement acquis.
 *
 * Les vues sont des gabarits PHP simples (pas Blade) : aucune compilation,
 * donc aucune écriture disque au rendu.
 */
class PodcastPageController extends Controller
{
    private const RELATED_COUNT = 4;

    /** @param string $path ex. "les-arbres-parlent-ils-sous-terre", ou l'ancienne forme "gen_6ab267b302f6d-jupiter-la-geante". */
    public function show(string $path): Response|RedirectResponse
    {
        $path = trim($path, '/');
        $base = config('vokso.public_url');

        try {
            if (preg_match('#^(gen_[A-Za-z0-9]+)#', $path, $matches)) {
                $episode = $this->find('g.generation_id', $matches[1]);
                if ($episode && $episode->slug) {
                    return redirect()->away(EpisodeText::episodeUrl($base, $episode->slug), 301);
                }
            } elseif (preg_match('#^[a-z0-9-]+$#', $path)) {
                $episode = $this->find('g.slug', $path);
            } else {
                $episode = null;
            }
        } catch (Throwable $e) {
            report($e);

            return $this->notFound();
        }

        if (! $episode) {
            return $this->notFound();
        }

        $title = EpisodeText::cleanTitle((string) $episode->title) ?: 'Épisode Vokso';
        $canonical = EpisodeText::episodeUrl($base, $episode->slug, $episode->id, $title);
        $description = EpisodeText::metaDescription((string) $episode->text_content);
        $imageUrl = $base.'/static/images/'.rawurlencode((string) $episode->image_url);
        $audioUrl = $base.'/static/audios/'.rawurlencode((string) $episode->audio_url);
        $category = trim((string) $episode->category);
        $categoryUrl = $category !== '' ? $base.EpisodeText::categoryPath($category) : null;
        $publishedAt = $episode->created_at ? strtotime((string) $episode->created_at) : null;
        $wordCount = str_word_count(\Illuminate\Support\Str::ascii((string) $episode->text_content));

        $breadcrumb = [['Accueil', $base.'/'], ['Discothèque', $base.'/discotheque']];
        if ($categoryUrl) {
            $breadcrumb[] = [$category, $categoryUrl];
        }
        $breadcrumb[] = [$title, $canonical];

        $organization = ['@type' => 'Organization', '@id' => $base.'/#organisation', 'name' => 'Vokso', 'url' => $base.'/'];
        $episodeLd = array_filter([
            '@type' => 'PodcastEpisode',
            '@id' => $canonical.'#episode',
            'name' => $title,
            'headline' => $title,
            'url' => $canonical,
            'description' => $description,
            'inLanguage' => 'fr-FR',
            'isAccessibleForFree' => true,
            'datePublished' => $publishedAt ? date('c', $publishedAt) : null,
            'image' => $episode->image_url ? $imageUrl : null,
            'genre' => $category !== '' ? $category : null,
            'keywords' => $category !== '' ? $category : null,
            'wordCount' => $wordCount ?: null,
            'associatedMedia' => $episode->audio_url ? [
                '@type' => 'AudioObject',
                'contentUrl' => $audioUrl,
                'encodingFormat' => 'audio/mpeg',
                'inLanguage' => 'fr-FR',
            ] : null,
            'partOfSeries' => ['@type' => 'PodcastSeries', 'name' => 'Vokso', 'url' => $base.'/'],
            'publisher' => $organization,
            'creativeWorkStatus' => 'Published',
            'mainEntityOfPage' => $canonical,
        ], fn ($value) => $value !== null);
        $breadcrumbLd = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($item, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $item[0], 'item' => $item[1],
            ], $breadcrumb, array_keys($breadcrumb)),
        ];
        $jsonLd = ['@context' => 'https://schema.org', '@graph' => [$episodeLd, $breadcrumbLd]];

        $response = response()->view('podcast.show', [
            'title' => $title,
            'canonical' => $canonical,
            'description' => $description,
            'imageUrl' => $episode->image_url ? $imageUrl : null,
            'audioUrl' => $episode->audio_url ? $audioUrl : null,
            'category' => $category,
            'categoryUrl' => $categoryUrl,
            'breadcrumb' => $breadcrumb,
            'related' => $this->related($episode),
            'publishedIso' => $publishedAt ? date('c', $publishedAt) : '',
            'publishedHuman' => $publishedAt ? date('d/m/Y', $publishedAt) : '',
            'text' => (string) $episode->text_content,
            'jsonLd' => json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP),
        ])->header('Content-Type', 'text/html; charset=utf-8');

        // Cacheable par les navigateurs et robots (la page ne change pas après publication).
        $response->setPublic()->setMaxAge(3600);
        if ($publishedAt) {
            $response->setLastModified((new \DateTime())->setTimestamp($publishedAt));
        }

        return $response;
    }

    private function find(string $column, string $value): ?object
    {
        return DB::table('generations as g')
            ->leftJoin('categorie as c', 'c.idcategorie', '=', 'g.idcategorie')
            ->where($column, $value)
            ->where('g.statut', 'on')
            ->first(['g.generation_id as id', 'g.slug', 'g.title', 'g.text_content', 'g.image_url', 'g.audio_url', 'g.created_at', 'g.idcategorie', 'c.label as category']);
    }

    /**
     * Maillage interne : derniers épisodes de la même catégorie, complétés par
     * les plus récents si la catégorie en compte trop peu.
     *
     * @return list<array{title: string, url: string, category: string}>
     */
    private function related(object $episode): array
    {
        $query = fn () => DB::table('generations as g')
            ->leftJoin('categorie as c', 'c.idcategorie', '=', 'g.idcategorie')
            ->where('g.statut', 'on')
            ->where('g.generation_id', '<>', $episode->id)
            ->orderByDesc('g.created_at')
            ->select(['g.generation_id as id', 'g.slug', 'g.title', 'c.label as category']);

        try {
            $rows = $query()->where('g.idcategorie', $episode->idcategorie)->limit(self::RELATED_COUNT)->get();
            if ($rows->count() < self::RELATED_COUNT) {
                $more = $query()->whereNotIn('g.generation_id', $rows->pluck('id'))->limit(self::RELATED_COUNT - $rows->count())->get();
                $rows = $rows->concat($more);
            }
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return $rows->map(fn ($row) => [
            'title' => EpisodeText::cleanTitle((string) $row->title),
            'url' => EpisodeText::episodePath($row->slug, $row->id, (string) $row->title),
            'category' => trim((string) $row->category),
        ])->all();
    }

    private function notFound(): Response
    {
        return response()->view('podcast.not-found', [], 404)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('X-Robots-Tag', 'noindex');
    }
}
