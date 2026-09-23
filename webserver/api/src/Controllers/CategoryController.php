<?php

namespace App\Controllers;

use App\Utils\Logger;
use Dotenv\Dotenv;

/**
 * Catégories gérées par IA (voir PodcastGenerator::ensureCategoryAssets()) :
 * label, icône Bootstrap Icons et image de couverture générées à la volée à
 * la création de chaque nouvelle catégorie. Sert la navigation par catégorie
 * du front (affichée seulement s'il y en a plus d'une, voir Home.js).
 */
class CategoryController
{
    private $pdo;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        try {
            $this->pdo = new \PDO(
                'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'],
                $_ENV['DB_USER'],
                $_ENV['DB_PASS']
            );
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            Logger::get()->error($e->getMessage(), ['controller' => 'category', 'exception' => get_class($e)]);
            http_response_code(500);
            header('Content-Type: application/json');
            die(json_encode(['error' => 'Erreur de connexion à la base de données']));
        }
    }

    /** Catégories ayant au moins un podcast publié, triées par nombre de podcasts décroissant. */
    public function list()
    {
        header('Content-Type: application/json');

        try {
            $query = 'SELECT c.idcategorie as id, c.label, c.icon, c.cover_image, COUNT(g.id) as podcast_count
                      FROM categorie c
                      INNER JOIN generations g ON g.idcategorie = c.idcategorie AND g.statut = "on"
                      GROUP BY c.idcategorie, c.label, c.icon, c.cover_image
                      ORDER BY podcast_count DESC, c.label ASC';
            $stmt = $this->pdo->query($query);
            $results = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $results]);
        } catch (\PDOException $e) {
            Logger::get()->error($e->getMessage(), ['controller' => 'category', 'exception' => get_class($e)]);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des catégories']);
        }
    }
}
