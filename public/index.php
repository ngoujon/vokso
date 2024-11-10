<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../vendor/autoload.php';

use App\Controllers\ApiController;

$api_key = "***CLE-API-SUPPRIMEE***";  // Remplacer par votre clé API OpenAI

$db_config = [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'generation_db',
    'DB_USER' => 'webapp',
    'DB_PASS' => '***MOT-DE-PASSE-SUPPRIME***'
];

// Initialiser le contrôleur avec la clé API et les paramètres de base de données
$controller = new ApiController($api_key, $db_config);
$controller->handleRequest();

require '../app/views/index.php';