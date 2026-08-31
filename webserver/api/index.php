<?php

// Inclure l'autoloader de Composer
require_once 'vendor/autoload.php';

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// L'affichage des erreurs n'est activé qu'en mode debug explicite : en
// production, une trace PHP exposerait les identifiants de base de données.
$debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// Initialiser et gérer le CORS AVANT toute autre opération
use App\Utils\CorsHandler;
CorsHandler::init();
CorsHandler::handleCors();

use App\Utils\ErrorTracking;
ErrorTracking::init();

// Table de routage explicite : seules ces routes sont exposées. Instancier un
// contrôleur et appeler une méthode dont le nom vient de l'URL permettrait
// d'atteindre n'importe quelle classe publique du projet.
$routes = [
    '' => ['App\Controllers\HomeController', 'index'],
    'home' => ['App\Controllers\HomeController', 'index'],
    'ping' => ['App\Controllers\PingController', 'index'],
    'generation' => ['App\Controllers\GenerationController', 'generateText'],
    'generation-audio' => ['App\Controllers\GenerationController', 'generateFromAudio'],
    'generation-status' => ['App\Controllers\GenerationController', 'status'],
    'listing' => ['App\Controllers\ListingController', 'getLastPodcasts'],
    'search' => ['App\Controllers\SearchController', 'searchPodcasts'],
    'newsletter' => ['App\Controllers\NewsletterController', 'subscribe'],
    'auth-register' => ['App\Controllers\AuthController', 'register'],
    'auth-login' => ['App\Controllers\AuthController', 'login'],
    'auth-me' => ['App\Controllers\AuthController', 'me'],
    'auth-logout' => ['App\Controllers\AuthController', 'logout'],
    'user-podcasts' => ['App\Controllers\UserController', 'myPodcasts'],
    'admin-kpis' => ['App\Controllers\AdminController', 'kpis'],
    'admin-users' => ['App\Controllers\AdminController', 'users'],
    'admin-podcasts' => ['App\Controllers\AdminController', 'podcasts'],
];

// Obtenir l'URL après le domaine (par exemple : "/generation" ou "/listing")
// Utiliser PATH_INFO si disponible (avec mod_rewrite), sinon REQUEST_URI
$request = isset($_SERVER['PATH_INFO'])
    ? trim($_SERVER['PATH_INFO'], '/')
    : trim($_SERVER['REQUEST_URI'], '/');

// Si la requête commence par '/api/', enlever ce préfixe
if (strpos($request, 'api/') === 0) {
    $request = substr($request, 4);
}

// Supprimer les éventuels paramètres de requête (ex: ?param=value)
$request = strtok($request, '?');

// Seul le premier segment identifie la route
$route = strtolower(explode('/', $request)[0]);

if (!isset($routes[$route])) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Route inconnue']);
    return;
}

[$controllerClass, $methodName] = $routes[$route];

try {
    $controller = new $controllerClass();

    if ($route === 'search') {
        $controller->{$methodName}($_GET['query'] ?? '');
    } else {
        $controller->{$methodName}();
    }
} catch (Throwable $e) {
    error_log('[api] ' . $e->getMessage());
    \Sentry\captureException($e);
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $debug ? $e->getMessage() : 'Erreur interne du serveur']);
}
