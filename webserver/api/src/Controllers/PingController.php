<?php

namespace App\Controllers;

class PingController
{
    public function index()
    {
        // Gérer les pré-requêtes OPTIONS pour CORS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            // Permettre les méthodes et origines spécifiques
            header("Access-Control-Allow-Origin: *"); // Ou spécifie ton domaine si besoin
            header("Access-Control-Allow-Methods: POST, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type");
            http_response_code(204); // Pas de contenu
            exit(0);
        }

        // Vérifier si la méthode est POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Récupérer le corps de la requête JSON
            $inputData = json_decode(file_get_contents('php://input'), true);

            // Vérifier que la donnée attendue ('value') est présente
            if (isset($inputData['value'])) {
                // Si la valeur est 'ping', on répond 'pong'
                if ($inputData['value'] === 'ping') {
                    header("Access-Control-Allow-Origin: *");
                    header('Content-Type: application/json');
                    echo json_encode(['message' => 'pong']);
                } else {
                    // Autre valeur : Mauvais choix
                    header("Access-Control-Allow-Origin: *");
                    http_response_code(400); // Mauvaise requête
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Mauvais choix']);
                }
            } else {
                // Si 'value' est absent, retourner une erreur
                http_response_code(400); // Mauvaise requête
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Valeur manquante']);
            }
        } else {
            // Si la méthode HTTP n'est pas POST, retourner une erreur
            http_response_code(405); // Méthode non autorisée
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Méthode non autorisée']);
        }
    }
}
