<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve l'API n8n au workflow hébergé sur le serveur de Vokso : la requête
 * doit venir d'une adresse autorisée (config vokso.n8n.allowed_ips) et porter
 * le jeton secret dont l'empreinte est configurée. Les deux refus répondent
 * pareil, pour ne rien révéler sur celui des deux verrous qui a bloqué.
 */
class EnsureN8nCaller
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('vokso.n8n.token_sha256');
        $token = (string) $request->bearerToken();
        $allowedIps = (array) config('vokso.n8n.allowed_ips');

        $authorized = $expected !== ''
            && $token !== ''
            && hash_equals($expected, hash('sha256', $token))
            && IpUtils::checkIp((string) $request->ip(), $allowedIps);

        if (! $authorized) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        return $next($request);
    }
}
