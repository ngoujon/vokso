<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte avec changement de mot de passe imposé (identifiant partagé par
 * un admin) ne peut rien faire d'autre que changer son mot de passe, lire
 * son profil, exporter/supprimer ses données ou se déconnecter.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            return response()->json([
                'error' => 'Vous devez changer votre mot de passe avant de continuer.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}
