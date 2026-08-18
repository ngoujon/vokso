<?php

namespace App\Controllers;

use Dotenv\Dotenv;
use Exception;

class SearchController
{
    private $pdo;

    public function __construct()
    {
        // Charger les variables d'environnement
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        // Connexion à la base de données
        try {
            $this->pdo = new \PDO(
                'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'],
                $_ENV['DB_USER'],
                $_ENV['DB_PASS']
            );
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('[search] ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            die(json_encode(['error' => 'Erreur de connexion à la base de données']));
        }
    }

    public function searchPodcasts($searchQuery)
    {
        // Vérifier que le texte de recherche est fourni et a une longueur de 3 caractères minimum
        if (strlen(trim($searchQuery)) < 3) {
            header("Content-Type: application/json");
            echo json_encode([
                'success' => false,
                'message' => 'Le texte de recherche doit contenir au moins 3 caractères.'
            ]);
            return;
        }

        try {
            // Préparer et exécuter la requête SQL
            $query = 'SELECT generation_id as id, text_content as title, image_url, audio_url, created_at
                      FROM generations
                      WHERE text_content LIKE :searchQuery AND statut = "on"
                      ORDER BY created_at DESC';

            $stmt = $this->pdo->prepare($query);
            $stmt->execute(['searchQuery' => '%' . $searchQuery . '%']);
            $results = $stmt->fetchAll();

            // Vérifier si des résultats ont été trouvés
            if ($results) {
                header("Content-Type: application/json");
                echo json_encode([
                    'success' => true,
                    'data' => $results
                ]);
            } else {
                header("Content-Type: application/json");
                echo json_encode([
                    'success' => false,
                    'data' => []
                ]);
            }
        } catch (\PDOException $e) {
            // Gestion des erreurs liées à la base de données
            error_log('[search] ' . $e->getMessage());
            http_response_code(500);
            header("Content-Type: application/json");
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la recherche'
            ]);
        }
    }
}