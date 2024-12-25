<?php

// Inclure l'autoloader de Composer
require_once 'vendor/autoload.php';

// Obtenir l'URL après le domaine
$request = trim($_SERVER['REQUEST_URI'], '/');

// Supprimer les éventuels paramètres de requête (ex: ?param=value)
$request = strtok($request, '?');

// Séparer les parties de l'URL
$segments = explode('/', $request);

// Nom du contrôleur (par défaut : HomeController)
$controllerName = !empty($segments[0]) ? ucfirst($segments[0]) . 'Controller' : 'HomeController';

// Nom de la méthode (par défaut : index)
$methodName = !empty($segments[1]) ? $segments[1] : 'index';

// Paramètres supplémentaires (après le nom de la méthode)
$params = array_slice($segments, 2);

try {
    // Ajouter l'espace de noms avant le contrôleur
    $controllerClass = 'App\\Controllers\\' . $controllerName;

    // Vérifier si la classe existe
    if (!class_exists($controllerClass)) {
        throw new Exception("Le contrôleur $controllerClass n'existe pas.");
    }

    // Instancier le contrôleur
    $controller = new $controllerClass();

    // Vérifier si la méthode existe
    if (!method_exists($controller, $methodName)) {
        throw new Exception("La méthode $methodName n'existe pas dans le contrôleur $controllerClass.");
    }

    // Appeler la méthode avec les paramètres
    call_user_func_array([$controller, $methodName], $params);

} catch (Exception $e) {
    // Gestion des erreurs
    http_response_code(404);
    echo "Erreur : " . $e->getMessage();
}
