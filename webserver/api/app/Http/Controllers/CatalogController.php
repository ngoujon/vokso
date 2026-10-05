<?php

namespace App\Http\Controllers;

use App\Support\EpisodeFormat;
use App\Support\EpisodeText;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Lecture publique des podcasts publiés : derniers épisodes, recherche, catégories. */
class CatalogController extends Controller
{
    // Par défaut les 3 derniers épisodes ; ?category=, ?limit= et ?offset=
    // servent à la navigation par catégorie. Plafonné pour qu'un ?limit= abusif ne charge
    // pas toute la table.
    private const DEFAULT_LIMIT = 3;
    private const MAX_LIMIT = 60;
    private const MAX_SEARCH_RESULTS = 60;

    public function latest(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', self::DEFAULT_LIMIT);
        $limit = min($limit > 0 ? $limit : self::DEFAULT_LIMIT, self::MAX_LIMIT);

        // ?offset= pagine la discothèque (bouton « Afficher plus »).
        $offset = max(0, (int) $request->query('offset', 0));
        $query = $this->episodes()->limit($limit)->offset($offset);

        $category = $request->query('category');
        if (is_string($category) && trim($category) !== '') {
            $query->where('c.label', trim($category));
        }

        return response()->json(['success' => true, 'data' => $this->withFormat($query->get())]);
    }

    public function search(Request $request): JsonResponse
    {
        $search = $request->query('query');
        $search = is_string($search) ? trim($search) : '';

        if (mb_strlen($search) < 3) {
            return response()->json([
                'success' => false,
                'message' => 'Le texte de recherche doit contenir au moins 3 caractères.',
            ]);
        }

        $like = '%'.addcslashes($search, '%_\\').'%';
        $results = $this->episodes()
            ->where(fn (Builder $q) => $q->where('g.title', 'like', $like)->orWhere('g.text_content', 'like', $like))
            ->limit(self::MAX_SEARCH_RESULTS)
            ->get();

        return response()->json(['success' => $results->isNotEmpty(), 'data' => $this->withFormat($results)]);
    }

    /** Catégories ayant au moins un podcast publié, par nombre de podcasts décroissant. */
    public function categories(): JsonResponse
    {
        $results = DB::table('categorie as c')
            ->join('generations as g', function ($join) {
                $join->on('g.idcategorie', '=', 'c.idcategorie')->where('g.statut', '=', 'on');
            })
            ->groupBy('c.idcategorie', 'c.label', 'c.icon', 'c.cover_image')
            ->orderByDesc('podcast_count')
            ->orderBy('c.label')
            ->get(['c.idcategorie as id', 'c.label', 'c.icon', 'c.cover_image', DB::raw('COUNT(g.id) as podcast_count')]);

        // Lien vers la page de la catégorie (/discotheque/{slug}), rendue côté serveur.
        $results = $results->map(function ($row) {
            $row->slug = EpisodeText::slugify((string) $row->label);
            $row->url = EpisodeText::categoryPath((string) $row->label);

            return $row;
        });

        return response()->json(['success' => true, 'data' => $results]);
    }

    private function episodes(): Builder
    {
        return DB::table('generations as g')
            ->leftJoin('categorie as c', 'c.idcategorie', '=', 'g.idcategorie')
            ->where('g.statut', 'on')
            ->orderByDesc('g.created_at')
            ->select([
                'g.generation_id as id', 'g.slug', 'g.title', 'g.text_content as description',
                'g.image_url', 'g.audio_url', 'g.created_at',
                'c.label as category', 'c.icon as category_icon',
                ...EpisodeFormat::columns(),
            ]);
    }

    /** Ajoute le libellé du niveau (« Expert ») à chaque épisode. */
    private function withFormat(Collection $rows): Collection
    {
        return $rows->map(function ($row) {
            $row->level_label = EpisodeFormat::levelLabel(isset($row->level) ? (int) $row->level : null);

            return $row;
        });
    }
}
