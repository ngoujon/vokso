<?php

namespace App\Controllers;

use App\Utils\Auth;
use Dotenv\Dotenv;
use PDO;

class AdminController
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

    /** Chiffres clés du tableau de bord admin. */
    public function kpis()
    {
        header('Content-Type: application/json');
        Auth::requireAdmin($this->db);

        $totalUsers = (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $totalPodcasts = (int) $this->db->query("SELECT COUNT(*) FROM generations WHERE statut = 'on'")->fetchColumn();
        $podcastsToday = (int) $this->db->query(
            "SELECT COUNT(*) FROM generations WHERE statut = 'on' AND DATE(created_at) = CURDATE()"
        )->fetchColumn();
        $podcastsThisWeek = (int) $this->db->query(
            "SELECT COUNT(*) FROM generations WHERE statut = 'on' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->fetchColumn();

        $costs = $this->db->query(
            "SELECT
                COALESCE(SUM(cost_text), 0) AS cost_text,
                COALESCE(SUM(cost_image), 0) AS cost_image,
                COALESCE(SUM(cost_audio), 0) AS cost_audio,
                COALESCE(SUM(cost_total), 0) AS cost_total,
                COALESCE(AVG(cost_total), 0) AS cost_average
             FROM generations WHERE statut = 'on'"
        )->fetch();

        $jobs = $this->db->query(
            "SELECT status, COUNT(*) AS total FROM generation_jobs GROUP BY status"
        )->fetchAll();
        $jobsByStatus = array_column($jobs, 'total', 'status');

        $topCategories = $this->db->query(
            "SELECT c.label, COUNT(*) AS total
             FROM generations g
             JOIN categorie c ON c.idcategorie = g.idcategorie
             WHERE g.statut = 'on'
             GROUP BY c.label
             ORDER BY total DESC
             LIMIT 5"
        )->fetchAll();

        echo json_encode([
            'total_users' => $totalUsers,
            'total_podcasts' => $totalPodcasts,
            'podcasts_today' => $podcastsToday,
            'podcasts_this_week' => $podcastsThisWeek,
            'cost' => [
                'text' => round((float) $costs['cost_text'], 4),
                'image' => round((float) $costs['cost_image'], 4),
                'audio' => round((float) $costs['cost_audio'], 4),
                'total' => round((float) $costs['cost_total'], 4),
                'average_per_podcast' => round((float) $costs['cost_average'], 4),
            ],
            'jobs_by_status' => [
                'pending' => (int) ($jobsByStatus['pending'] ?? 0),
                'processing' => (int) ($jobsByStatus['processing'] ?? 0),
                'done' => (int) ($jobsByStatus['done'] ?? 0),
                'error' => (int) ($jobsByStatus['error'] ?? 0),
            ],
            'top_categories' => $topCategories,
        ]);
    }

    /** Liste (GET), création (POST) ou mise à jour rôle/statut (PATCH) des comptes. */
    public function users()
    {
        header('Content-Type: application/json');
        $admin = Auth::requireAdmin($this->db);
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            $stmt = $this->db->query(
                "SELECT u.id, u.email, u.role, u.status, u.created_at,
                        (SELECT COUNT(*) FROM generations g WHERE g.user_id = u.id) AS podcasts_count
                 FROM users u
                 ORDER BY u.created_at DESC"
            );
            echo json_encode(['data' => $stmt->fetchAll()]);
            return;
        }

        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $email = is_string($input['email'] ?? null) ? trim(strtolower($input['email'])) : '';
            $password = is_string($input['password'] ?? null) ? $input['password'] : '';
            $role = in_array($input['role'] ?? 'user', ['user', 'admin'], true) ? $input['role'] : 'user';

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8) {
                http_response_code(400);
                echo json_encode(['error' => 'Email invalide ou mot de passe trop court (8 caractères minimum).']);
                return;
            }

            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :email');
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Un compte existe déjà avec cet email.']);
                return;
            }

            $stmt = $this->db->prepare(
                'INSERT INTO users (email, password_hash, role, status) VALUES (:email, :password_hash, :role, "active")'
            );
            $stmt->execute([
                ':email' => $email,
                ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
                ':role' => $role,
            ]);

            http_response_code(201);
            echo json_encode(['id' => (int) $this->db->lastInsertId(), 'email' => $email, 'role' => $role]);
            return;
        }

        if ($method === 'PATCH') {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Identifiant manquant.']);
                return;
            }
            if ($id === (int) $admin['id']) {
                http_response_code(400);
                echo json_encode(['error' => 'Vous ne pouvez pas modifier votre propre compte depuis cet écran.']);
                return;
            }

            $fields = [];
            $params = [':id' => $id];
            if (in_array($input['role'] ?? null, ['user', 'admin'], true)) {
                $fields[] = 'role = :role';
                $params[':role'] = $input['role'];
            }
            if (in_array($input['status'] ?? null, ['active', 'disabled'], true)) {
                $fields[] = 'status = :status';
                $params[':status'] = $input['status'];
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['error' => 'Rien à mettre à jour.']);
                return;
            }

            $stmt = $this->db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id');
            $stmt->execute($params);
            echo json_encode(['success' => true]);
            return;
        }

        http_response_code(405);
        echo json_encode(['error' => 'Méthode non autorisée']);
    }

    /** Monitoring : tous les podcasts générés, avec coût détaillé et compte associé. */
    public function podcasts()
    {
        header('Content-Type: application/json');
        Auth::requireAdmin($this->db);

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        $search = trim((string) ($_GET['search'] ?? ''));

        $where = "g.statut = 'on'";
        $params = [];
        if ($search !== '') {
            $where .= ' AND (g.title LIKE :search OR g.text_content LIKE :search OR u.email LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM generations g LEFT JOIN users u ON u.id = g.user_id WHERE $where"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT g.generation_id, g.title, g.text_content AS description,
                    g.image_url, g.audio_url, g.created_at,
                    g.cost_text, g.cost_image, g.cost_audio, g.cost_total,
                    c.label AS category, u.email AS user_email
             FROM generations g
             LEFT JOIN users u ON u.id = g.user_id
             LEFT JOIN categorie c ON c.idcategorie = g.idcategorie
             WHERE $where
             ORDER BY g.created_at DESC
             LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);

        echo json_encode([
            'data' => $stmt->fetchAll(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }
}
