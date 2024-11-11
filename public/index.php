<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../vendor/autoload.php';

use App\Controllers\ApiController;
use App\Controllers\SearchController;
use App\Models\DatabaseModel;
use App\Models\FileModel;

// Récupérer la clé API et la configuration de la base de données
$api_key = "***CLE-API-SUPPRIMEE***"; // Remplacer par votre clé API OpenAI
$db_config = [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'generation_db',
    'DB_USER' => 'webapp',
    'DB_PASS' => '***MOT-DE-PASSE-SUPPRIME***'
];

// Route pour la recherche AJAX
if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['query'])) {
    $searchController = new SearchController($db_config);
    $searchController->search($_GET['query']);
}

// Contrôleur principal pour les générations
$controller = new ApiController($api_key, $db_config);
$controller->handleRequest();

// Récupérer les 3 dernières générations pour l'affichage par défaut
$databaseModel = new DatabaseModel($db_config);
$fileModel = new FileModel();

$last_generations = $databaseModel->getLastGenerations();
$generations_with_files = [];

foreach ($last_generations as $generation) {
    $generations_with_files[] = [
        'generation' => $generation,
        'files' => $fileModel->getGenerationFiles($generation)
    ];
}

require '../app/views/index.php';  // Charge la vue
