<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureTokenAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // L'API est servie sous /api par Apache (Alias vers public/, voir
        // apache-config/vokso.conf) : Laravel ne voit donc que la suite du
        // chemin, d'où l'absence de préfixe ici.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // nginx termine le TLS et relaie vers Apache en local : sans cette
        // confiance, toutes les requêtes auraient l'IP 127.0.0.1 et les
        // limites par IP s'appliqueraient à tout le monde à la fois.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        $middleware->alias([
            'auth.token' => EnsureTokenAuthenticated::class,
            'password.changed' => EnsurePasswordChanged::class,
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        // Tout est JSON (API) sauf la page épisode et le sitemap, qui gèrent
        // eux-mêmes leurs erreurs.
        $exceptions->shouldRenderJsonWhen(fn () => true);

        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => response()->json(['error' => 'Route inconnue'], 404));
        $exceptions->render(fn (MethodNotAllowedHttpException $e, Request $request) => response()->json(['error' => 'Méthode non autorisée'], 405));
    })->create();
