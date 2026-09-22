<?php

namespace App\Controllers;

use App\Utils\Logger;
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
            Logger::get()->error($e->getMessage(), ['controller' => 'listing', 'exception' => get_class($e)]);
            http_response_code(500);
            die(json_encode(['error' => 'Erreur de connexion à la base de données']));
        }
    }

    public function getLastPodcasts()
    {
        // Récupérer les 3 derniers podcasts générés
        $query = 'SELECT g.generation_id as id, g.title, g.text_content as description,
                         g.image_url, g.audio_url, g.created_at, c.label as category
                  FROM generations g
                  LEFT JOIN categorie c ON c.idcategorie = g.idcategorie
                  WHERE g.statut = "on"
                  ORDER BY g.created_at DESC LIMIT 3';
        $stmt = $this->pdo->query($query);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Vérifier si des podcasts ont été trouvés
        echo json_encode([
            'success' => true,
            'data' => $results
        ]);
    }
}