<?php

namespace App\Support;

use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Jetons de connexion opaques, envoyés par le front dans
 * "Authorization: Bearer <token>" (pas de cookie : le front et l'API ne sont
 * pas garantis d'être sur le même sous-domaine).
 */
class TokenAuth
{
    public static function issue(User $user): string
    {
        $plain = bin2hex(random_bytes(32));

        AuthToken::create([
            'token' => AuthToken::hash($plain),
            'user_id' => $user->id,
            'expires_at' => now()->addSeconds((int) config('auth.token_ttl')),
        ]);

        return $plain;
    }

    public static function revoke(string $plainToken): void
    {
        AuthToken::query()->whereKey(AuthToken::hash($plainToken))->delete();
    }

    /** Utilisateur actif associé au jeton de la requête, ou null (absent, expiré, compte désactivé). */
    public static function userFromRequest(Request $request): ?User
    {
        $plain = $request->bearerToken();
        if (! is_string($plain) || $plain === '') {
            return null;
        }

        $token = AuthToken::query()
            ->whereKey(AuthToken::hash($plain))
            ->where('expires_at', '>', now())
            ->first();

        $user = $token?->user;

        return $user && $user->isActive() ? $user : null;
    }
}
