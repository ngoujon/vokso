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

    // Par défaut on ne montre que les 3 dernières générations (comportement
    // historique) ; ?category=... et ?limit=... permettent au front de
    // parcourir plus largement une catégorie donnée (voir la navigation par
    // catégorie de Home.js). Plafonné pour éviter qu'un ?limit= abusif ne
    // charge toute la table.
    private const DEFAULT_LIMIT = 3;
    private const MAX_LIMIT = 60;

    public function getLastPodcasts()
    {
        $category = isset($_GET['category']) && is_string($_GET['category']) ? trim($_GET['category']) : '';
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : self::DEFAULT_LIMIT;
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }
        $limit = min($limit, self::MAX_LIMIT);

        $query = 'SELECT g.generation_id as id, g.title, g.text_content as description,
                         g.image_url, g.audio_url, g.created_at,
                         c.label as category, c.icon as category_icon
                  FROM generations g
                  LEFT JOIN categorie c ON c.idcategorie = g.idcategorie
                  WHERE g.statut = "on"';

        $params = [];
        if ($category !== '') {
            $query .= ' AND c.label = :category';
            $params[':category'] = $category;
        }
        $query .= ' ORDER BY g.created_at DESC LIMIT ' . $limit;

        // Le déploiement du code (scripts/deploy.sh) et l'application des
        // migrations SQL/ sont deux étapes manuelles distinctes (voir l'en-tête
        // de deploy.sh) : si ce endpoint, le plus visible de l'app, déploie
        // avant que la colonne categorie.icon n'existe encore en base, on
        // renvoie une erreur propre plutôt qu'un 500 brut.
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
        } catch (\PDOException $e) {
            Logger::get()->error($e->getMessage(), ['controller' => 'listing', 'exception' => get_class($e)]);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des générations']);
        }
    }
}