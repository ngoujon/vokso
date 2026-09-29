<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PasswordPolicy;
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

    public function createUser(Request $request): JsonResponse
    {
        $email = $request->json('email');
        $email = is_string($email) ? trim(strtolower($email)) : '';
        $password = $request->json('password');
        $password = is_string($password) ? $password : '';
        $role = in_array($request->json('role', 'user'), ['user', 'admin'], true) ? $request->json('role', 'user') : 'user';

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Email invalide.', 400);
        }

        $policyError = PasswordPolicy::validate($password);
        if ($policyError !== null) {
            return $this->error($policyError, 400);
        }

        if (User::where('email', $email)->exists()) {
            return $this->error('Un compte existe déjà avec cet email.', 409);
        }

        // Mot de passe choisi par l'admin et communiqué hors bande : son
        // changement est imposé à la première connexion.
        $user = User::create([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role,
            'status' => 'active',
            'must_change_password' => true,
        ]);

        return response()->json(['id' => $user->id, 'email' => $email, 'role' => $role], 201);
    }

    /** Mise à jour du rôle et/ou du statut d'un autre compte. */
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
        if (in_array($request->json('role'), ['user', 'admin'], true)) {
            $changes['role'] = $request->json('role');
        }
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
