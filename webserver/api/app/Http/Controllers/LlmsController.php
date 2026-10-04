<?php

namespace App\Http\Controllers;

use App\Support\EpisodeText;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * /llms-full.txt (réécrit vers /api/llms-full par site/.htaccess) : index en
 * Markdown de tous les épisodes publiés, par catégorie, avec un résumé et
 * l'URL de chaque page. Destiné aux moteurs de réponse et assistants IA
 * (GEO) : un seul fichier texte, sans JavaScript, à jour en permanence.
 * Le /llms.txt statique (site/) le référence.
 */
class LlmsController extends Controller
{
    private const MAX_EPISODES = 2000;

    public function full(): Response
    {
        $base = config('vokso.public_url');
        $lines = [
            '# Vokso — tous les épisodes',
            '',
            '> Index complet des podcasts publiés sur Vokso (vokso.fr) : épisodes courts en français, '
            .'générés par IA (texte vérifié par une recherche web, voix de synthèse), gratuits et sans inscription. '
            .'Chaque épisode a sa page avec la transcription intégrale.',
            '',
            'Mis à jour : '.date('Y-m-d'),
            '',
        ];

        try {
            $episodes = DB::table('generations as g')
                ->leftJoin('categorie as c', 'c.idcategorie', '=', 'g.idcategorie')
                ->where('g.statut', 'on')
                ->orderBy('c.label')
                ->orderByDesc('g.created_at')
                ->limit(self::MAX_EPISODES)
                ->get(['g.generation_id', 'g.slug', 'g.title', 'g.text_content', 'g.created_at', 'c.label as category']);
        } catch (Throwable $e) {
            report($e);
            $episodes = collect();
        }

        foreach ($episodes->groupBy(fn ($row) => trim((string) $row->category) ?: 'Divers') as $category => $rows) {
            $lines[] = '## '.$category;
            $lines[] = '';
            foreach ($rows as $row) {
                $title = EpisodeText::cleanTitle((string) $row->title) ?: 'Épisode Vokso';
                $url = EpisodeText::episodeUrl($base, $row->slug, $row->generation_id, $title);
                $date = $row->created_at ? date('Y-m-d', strtotime((string) $row->created_at)) : '';
                $lines[] = '- ['.$title.']('.$url.')'.($date !== '' ? ' ('.$date.')' : '').' : '.EpisodeText::metaDescription((string) $row->text_content, 220);
            }
            $lines[] = '';
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
