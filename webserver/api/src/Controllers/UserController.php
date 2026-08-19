<?php

namespace App\Controllers;

use App\Utils\Auth;
use Dotenv\Dotenv;
use PDO;

class UserController
{
    private PDO $db;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $this->db = new PDO(
            'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
            $_ENV['DB_USER'],
            $_ENV['DB_PASS']
        );
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** Les podcasts générés par l'utilisateur connecté, avec le détail des coûts. */
    public function myPodcasts()
    {
        header('Content-Type: application/json');
        $user = Auth::requireUser($this->db);

        $stmt = $this->db->prepare(
            "SELECT g.generation_id, g.title, g.description, g.image_url, g.audio_url, g.created_at,
                    g.cost_text, g.cost_image, g.cost_audio, g.cost_total, c.keyword AS category
             FROM generations g
             LEFT JOIN categorie c ON c.idcategorie = g.idcategorie
             WHERE g.user_id = :user_id AND g.statut = 'on'
             ORDER BY g.created_at DESC"
        );
        $stmt->execute([':user_id' => $user['id']]);

        echo json_encode(['data' => $stmt->fetchAll()]);
    }
}
