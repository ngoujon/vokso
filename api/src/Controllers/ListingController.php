<?php

namespace App\Controllers;

class ListingController
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

    public function getLastPodcasts()
    {
                // Autoriser les requêtes CORS
                header("Access-Control-Allow-Origin: *");
                header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
                header("Access-Control-Allow-Headers: Content-Type, Authorization");
        // Connexion à la base de données
        $pdo = new \PDO('mysql:host=localhost;dbname=generation_db', 'webapp', '***MOT-DE-PASSE-SUPPRIME***');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    
        // Récupérer les 3 derniers podcasts générés
        $query = 'SELECT id, title, image_url, audio_url FROM generations ORDER BY created_at DESC LIMIT 3';
        $stmt = $pdo->query($query);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
        // Vérifier si des podcasts ont été trouvés
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
                'message' => 'Aucun podcast disponible.'
            ]);
        }
    }
}
