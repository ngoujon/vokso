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

        // Autoriser les requêtes CORS
        header("Access-Control-Allow-Origin: *"); // Autorise toutes les origines (*), vous pouvez restreindre à un domaine spécifique
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Méthodes autorisées
        header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Headers autorisés
        header("Content-Type: application/json"); // Définition du type de contenu

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
            die(json_encode(['error' => 'Erreur de connexion à la base de données: ' . $e->getMessage()]));
        }
    }

    public function getLastPodcasts()
    {
        // Récupérer les 3 derniers podcasts générés
        $query = 'SELECT id, title, image_url, audio_url, created_at FROM generations WHERE statut = "on" ORDER BY created_at DESC LIMIT 3';
        $stmt = $this->pdo->query($query);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Vérifier si des podcasts ont été trouvés
        echo json_encode([
            'success' => true,
            'data' => $results
        ]);
    }
}