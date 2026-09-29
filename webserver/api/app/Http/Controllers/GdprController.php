<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Droits RGPD exercables depuis l'espace compte : accès / portabilité
 * (export) et effacement (suppression de compte).
 */
class GdprController extends Controller
{
    /** Export complet des données personnelles au format JSON. */
    public function export(Request $request): Response
    {
        $userId = $request->user()->id;

        $export = [
            'exported_at' => now()->toAtomString(),
            'account' => DB::table('users')->where('id', $userId)->first(['id', 'email', 'role', 'created_at']),
            'podcasts' => DB::table('generations')->where('user_id', $userId)->where('statut', 'on')
                ->orderByDesc('created_at')->get(['title', 'text_content', 'created_at', 'cost_total']),
        ];

        return response()->json($export, 200, [
            'Content-Disposition' => 'attachment; filename="vokso-donnees-personnelles.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Suppression définitive du compte, mot de passe requis. Les podcasts
     * déjà publiés restent en ligne mais ne sont plus rattachés à personne.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! password_verify((string) $request->json('password', ''), $user->password_hash)) {
            return $this->error('Mot de passe incorrect.', 401);
        }

        DB::transaction(function () use ($user) {
            DB::table('generations')->where('user_id', $user->id)->update(['user_id' => null]);
            DB::table('generation_jobs')->where('user_id', $user->id)->update(['user_id' => null]);
            DB::table('auth_tokens')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        return response()->json(['success' => true]);
    }
}
