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
 *
 * La suppression anonymise le compte plutôt que de le supprimer : les
 * factures déjà émises doivent être conservées 10 ans (art. L123-22 du code
 * de commerce), ce qui prime sur le droit à l'effacement pour ces données
 * (RGPD art. 17.3.b). invoices.user_id est d'ailleurs en ON DELETE RESTRICT.
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
            'billing_profile' => DB::table('billing_profiles')->where('user_id', $userId)->first([
                'client_type', 'full_name', 'company_name', 'siret', 'vat_number',
                'address_line1', 'address_line2', 'postal_code', 'city', 'country_code',
            ]),
            'invoices' => DB::table('invoices')->where('user_id', $userId)->orderByDesc('issued_at')->get([
                'number', 'issued_at', 'currency', 'plan', 'description',
                'amount_excl_tax', 'vat_rate', 'amount_tax', 'amount_total',
            ]),
            'podcasts' => DB::table('generations')->where('user_id', $userId)->where('statut', 'on')
                ->orderByDesc('created_at')->get(['title', 'text_content', 'created_at', 'cost_total']),
        ];

        return response()->json($export, 200, [
            'Content-Disposition' => 'attachment; filename="vokso-donnees-personnelles.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /** Suppression de compte : anonymisation, mot de passe requis. */
    public function deleteAccount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! password_verify((string) $request->json('password', ''), $user->password_hash)) {
            return $this->error('Mot de passe incorrect.', 401);
        }

        DB::transaction(function () use ($user) {
            DB::table('auth_tokens')->where('user_id', $user->id)->delete();
            DB::table('billing_profiles')->where('user_id', $user->id)->delete();

            // Email rendu unique et non réutilisable, compte désactivé,
            // mot de passe et 2FA invalidés.
            $user->forceFill([
                'email' => sprintf('deleted-user-%d@deleted.vokso.fr', $user->id),
                'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT),
                'status' => 'disabled',
                'totp_secret' => null,
                'totp_enabled' => false,
                'must_change_password' => false,
            ])->save();
        });

        return response()->json(['success' => true]);
    }
}
