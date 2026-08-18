<?php

namespace App\Controllers;

use Dotenv\Dotenv;
use Exception;

class ListingController
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
            // Le message PDO contient l'hôte et l'utilisateur : il reste dans les logs.
            error_log('[listing] ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Erreur de connexion à la base de données']));
        }
    }

    public function getLastPodcasts()
    {
        // Récupérer les 3 derniers podcasts générés
        $query = 'SELECT generation_id as id, text_content as title, image_url, audio_url, created_at FROM generations WHERE statut = "on" ORDER BY created_at DESC LIMIT 3';
        $stmt = $this->pdo->query($query);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Vérifier si des podcasts ont été trouvés
        echo json_encode([
            'success' => true,
            'data' => $results
        ]);
    }
}