<?php

// Inclure l'autoloader de Composer
require_once 'vendor/autoload.php';

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialiser et gérer le CORS
use App\Utils\CorsHandler;
CorsHandler::init();
CorsHandler::handleCors();

// Obtenir l'URL après le domaine (par exemple : "/generation" ou "/listing")
$request = trim($_SERVER['REQUEST_URI'], '/');

// Supprimer les éventuels paramètres de requête (ex: ?param=value)
$request = strtok($request, '?');

// Séparer les parties de l'URL (par exemple : "generation" et "generateText")
$segments = explode('/', $request);

// Nom du contrôleur (par défaut : GenerationController)
$controllerName = !empty($segments[0]) ? ucfirst($segments[0]) . 'Controller' : 'GenerationController';

// Nom de la méthode (par défaut : generateText)
$methodName = !empty($segments[1]) ? $segments[1] : 'generateText';

// Paramètres supplémentaires (après le nom de la méthode)
$params = array_slice($segments, 2);

// Si la route est "/listing", on remplace la méthode par "getLastPodcasts"
if ($controllerName === 'ListingController') {
    $methodName = 'getLastPodcasts';
}

// Si la route est "/search", configurer pour effectuer une recherche
if ($controllerName === 'SearchController') {
    $methodName = 'searchPodcasts'; // Nom de la méthode de recherche
}

try {
    // Ajouter l'espace de noms avant le contrôleur
    $controllerClass = 'App\\Controllers\\' . $controllerName;

    // Vérifier si la classe existe
    if (!class_exists($controllerClass)) {
        throw new Exception("Le contrôleur $controllerClass n'existe pas.");
    }

    // Instancier le contrôleur
    $controller = new $controllerClass();

    // Vérifier si la méthode existe dans le contrôleur
    if (!method_exists($controller, $methodName)) {
        throw new Exception("La méthode $methodName n'existe pas dans le contrôleur $controllerClass.");
    }

    // Si c'est une recherche, récupérer la requête "query" depuis les paramètres GET
    if ($controllerName === 'SearchController' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $query = $_GET['query'] ?? '';
        call_user_func_array([$controller, $methodName], [$query]);
    } else {
        // Appeler la méthode avec les paramètres normaux
        call_user_func_array([$controller, $methodName], $params);
    }

} catch (Exception $e) {
    // Gestion des erreurs : retourne un code 404 si un problème est rencontré
    http_response_code(404);
    echo json_encode(['error' => $e->getMessage()]);
}