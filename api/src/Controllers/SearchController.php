<?php

namespace App\Controllers;

class SearchController
{
    private $pdo;

    public function __construct()
    {
        // Connexion à la base de données
        try {
            $this->pdo = new \PDO('mysql:host=localhost;dbname=generation_db', 'webapp', '***MOT-DE-PASSE-SUPPRIME***');
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            die('Erreur de connexion à la base de données : ' . $e->getMessage());
        }
    }

    public function searchPodcasts($searchQuery)
    {
        // Autoriser les requêtes CORS
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");

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
            $query = 'SELECT id, title, image_url, audio_url 
                      FROM generations 
                      WHERE title LIKE :searchQuery  AND statut = "on" 
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
                    // 'message' => 'Aucun résultat trouvé pour votre recherche.'
                ]);
            }
        } catch (\PDOException $e) {
            // Gestion des erreurs liées à la base de données
            header("Content-Type: application/json");
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la recherche : ' . $e->getMessage()
            ]);
        }
    }
}