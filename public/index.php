<?php
require '../controllers/ApiController.php';

$api_key = "***CLE-API-SUPPRIMEE***"; // Ne pas exposer dans un environnement de production
$controller = new ApiController($api_key);
$controller->handleRequest();
?>
