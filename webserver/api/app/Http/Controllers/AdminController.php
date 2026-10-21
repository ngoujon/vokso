<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AiBudget;
use App\Support\Stats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /** Chiffres clés du tableau de bord admin. */
    public function kpis(): JsonResponse
    {
        $published = fn () => DB::table('generations')->where('statut', 'on');

        $costs = $published()->selectRaw(
            'COALESCE(SUM(cost_text), 0) AS cost_text, COALESCE(SUM(cost_image), 0) AS cost_image,
             COALESCE(SUM(cost_audio), 0) AS cost_audio, COALESCE(SUM(cost_total), 0) AS cost_total,
             COALESCE(AVG(cost_total), 0) AS cost_average'
        )->first();

        $jobsByStatus = DB::table('generation_jobs')
            ->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $topCategories = DB::table('generations as g')
            ->join('categorie as c', 'c.idcategorie', '=', 'g.idcategorie')
            ->where('g.statut', 'on')
            ->groupBy('c.label')
            ->orderByDesc('total')
            ->limit(5)
            ->get(['c.label', DB::raw('COUNT(*) AS total')]);

        return response()->json([
            'total_users' => User::count(),
            'total_podcasts' => $published()->count(),
            'podcasts_today' => $published()->where('created_at', '>=', now()->startOfDay())->count(),
            'podcasts_this_week' => $published()->where('created_at', '>=', now()->subDays(7))->count(),
            'cost' => [
                'text' => round((float) $costs->cost_text, 4),
                'image' => round((float) $costs->cost_image, 4),
                'audio' => round((float) $costs->cost_audio, 4),
                'total' => round((float) $costs->cost_total, 4),
                'average_per_podcast' => round((float) $costs->cost_average, 4),
            ],
            'jobs_by_status' => [
                'pending' => (int) ($jobsByStatus['pending'] ?? 0),
                'processing' => (int) ($jobsByStatus['processing'] ?? 0),
                'done' => (int) ($jobsByStatus['done'] ?? 0),
                'error' => (int) ($jobsByStatus['error'] ?? 0),
            ],
            'top_categories' => $topCategories,
        ]);
    }

    /**
     * Séries quotidiennes et totaux sur une période (7 à 365 jours) :
     * générations, échecs, coût, écoutes et téléchargements, comparés à la
     * période précédente de même durée ; épisodes les plus écoutés ; état du
     * garde-fou de dépense IA.
     */
    public function stats(Request $request): JsonResponse
    {
        $days = min(365, max(7, (int) $request->query('days', 30)));
        $start = now()->subDays($days - 1)->startOfDay();
        $previousStart = $start->copy()->subDays($days);

        $daily = fn ($query, string $column = 'created_at') => $query
            ->where($column, '>=', $start->format('Y-m-d H:i:s'))
            ->groupBy(DB::raw("DATE($column)"))
            ->pluck('total', 'day');

        $generations = $daily(DB::table('generations')->where('statut', 'on')->selectRaw('DATE(created_at) AS day, COUNT(*) AS total'));
        $costs = $daily(DB::table('generations')->where('statut', 'on')->selectRaw('DATE(created_at) AS day, SUM(cost_total) AS total'));
        $errors = $daily(DB::table('generation_jobs')->where('status', 'error')->selectRaw('DATE(created_at) AS day, COUNT(*) AS total'));

        $events = [];
        $eventsReady = Stats::ready('podcast_events');
        foreach (Stats::EVENT_TYPES as $type) {
            $events[$type] = $eventsReady
                ? $daily(DB::table('podcast_events')->where('type', $type)->selectRaw('DATE(created_at) AS day, COUNT(*) AS total'))
                : collect();
        }

        $series = [];
        for ($day = $start->copy(); $day->lte(now()); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $series[] = [
                'date' => $key,
                'generations' => (int) ($generations[$key] ?? 0),
                'errors' => (int) ($errors[$key] ?? 0),
                'cost' => round((float) ($costs[$key] ?? 0), 4),
                'plays' => (int) ($events['play'][$key] ?? 0),
                'downloads_audio' => (int) ($events['download_audio'][$key] ?? 0),
                'downloads_pdf' => (int) ($events['download_pdf'][$key] ?? 0),
            ];
        }

        $totals = function ($from, $to) use ($eventsReady) {
            $between = fn ($query) => $query->where('created_at', '>=', $from->format('Y-m-d H:i:s'))->where('created_at', '<', $to->format('Y-m-d H:i:s'));
            $eventCount = fn (array $types) => $eventsReady ? $between(DB::table('podcast_events')->whereIn('type', $types))->count() : 0;
            $published = $between(DB::table('generations')->where('statut', 'on'));
            $jobs = $between(DB::table('generation_jobs'))->count();
            $failed = $between(DB::table('generation_jobs')->where('status', 'error'))->count();

            return [
                'generations' => (clone $published)->count(),
                'cost' => round((float) (clone $published)->sum('cost_total'), 4),
                'errors' => $failed,
                'error_rate' => $jobs > 0 ? round($failed / $jobs * 100, 1) : 0,
                'plays' => $eventCount(['play']),
                'downloads' => $eventCount(['download_audio', 'download_pdf']),
            ];
        };

        $topEpisodes = $eventsReady
            ? DB::table('podcast_events as e')
                ->join('generations as g', 'g.generation_id', '=', 'e.generation_id')
                ->where('e.created_at', '>=', $start->format('Y-m-d H:i:s'))
                ->groupBy('e.generation_id', 'g.title', 'g.slug')
                ->orderByDesc('plays')
                ->limit(10)
                ->get([
                    'e.generation_id', 'g.title', 'g.slug',
                    DB::raw("SUM(CASE WHEN e.type = 'play' THEN 1 ELSE 0 END) AS plays"),
                    DB::raw("SUM(CASE WHEN e.type <> 'play' THEN 1 ELSE 0 END) AS downloads"),
                ])
            : [];

        return response()->json([
            'days' => $days,
            'events_ready' => $eventsReady,
            'series' => $series,
            'current' => $totals($start, now()->addSecond()),
            'previous' => $totals($previousStart, $start),
            'all_time' => [
                'plays' => $eventsReady ? DB::table('podcast_events')->where('type', 'play')->count() : 0,
                'downloads' => $eventsReady ? DB::table('podcast_events')->where('type', '<>', 'play')->count() : 0,
            ],
            'top_episodes' => $topEpisodes,
            'ai_budget' => AiBudget::usage(),
        ]);
    }

    public function users(): JsonResponse
    {
        $users = DB::table('users as u')
            ->orderByDesc('u.created_at')
            ->get([
                'u.id', 'u.email', 'u.role', 'u.status', 'u.created_at',
                DB::raw('(SELECT COUNT(*) FROM generations g WHERE g.user_id = u.id) AS podcasts_count'),
            ]);

        return response()->json(['data' => $users]);
    }

    /** Désactivation / réactivation d'un autre compte (un seul compte peut se connecter : voir vokso:sole-admin). */
    public function updateUser(Request $request): JsonResponse
    {
        $id = (int) $request->json('id', 0);
        if ($id <= 0) {
            return $this->error('Identifiant manquant.', 400);
        }
        if ($id === $request->user()->id) {
            return $this->error('Vous ne pouvez pas modifier votre propre compte depuis cet écran.', 400);
        }

        $changes = [];
        if (in_array($request->json('status'), ['active', 'disabled'], true)) {
            $changes['status'] = $request->json('status');
        }
        if ($changes === []) {
            return $this->error('Rien à mettre à jour.', 400);
        }

        $user = User::find($id);
        if (! $user) {
            return $this->error('Compte introuvable.', 404);
        }

        $user->forceFill($changes)->save();

        // Un compte désactivé perd immédiatement ses sessions.
        if (($changes['status'] ?? null) === 'disabled') {
            DB::table('auth_tokens')->where('user_id', $id)->delete();
        }

        return response()->json(['success' => true]);
    }

    /** Monitoring : tous les podcasts générés, avec coût détaillé et compte associé. */
    public function podcasts(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $search = trim((string) $request->query('search', ''));

        $query = DB::table('generations as g')
            ->leftJoin('users as u', 'u.id', '=', 'g.user_id')
            ->leftJoin('categorie as c', 'c.idcategorie', '=', 'g.idcategorie')
            ->where('g.statut', 'on');

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($q) => $q->where('g.title', 'like', $like)
                ->orWhere('g.text_content', 'like', $like)
                ->orWhere('u.email', 'like', $like));
        }

        $total = (clone $query)->count();

        $data = $query->orderByDesc('g.created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'g.generation_id', 'g.title', 'g.text_content as description',
                'g.image_url', 'g.audio_url', 'g.created_at',
                'g.cost_text', 'g.cost_image', 'g.cost_audio', 'g.cost_total',
                'c.label as category', 'u.email as user_email',
            ]);

        return response()->json(['data' => $data, 'page' => $page, 'per_page' => $perPage, 'total' => $total]);
    }
}
