<?php

namespace App\Controllers;

use App\Utils\Auth;
use Dotenv\Dotenv;
use PDO;

class AuthController
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
    }

    /** Inscription publique : toujours en rôle "user", les comptes admin se créent depuis l'espace admin. */
    public function register()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            return;
        }

        [$email, $password, $error] = $this->readCredentials();
        if ($error !== null) {
            http_response_code(400);
            echo json_encode(['error' => $error]);
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
            'INSERT INTO users (email, password_hash, role, status) VALUES (:email, :password_hash, "user", "active")'
        );
        $stmt->execute([':email' => $email, ':password_hash' => password_hash($password, PASSWORD_BCRYPT)]);
        $userId = (int) $this->db->lastInsertId();

        $token = Auth::createToken($this->db, $userId);
        http_response_code(201);
        echo json_encode([
            'token' => $token,
            'user' => ['id' => $userId, 'email' => $email, 'role' => 'user'],
        ]);
    }

    public function login()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            return;
        }

        [$email, $password, $error] = $this->readCredentials();
        if ($error !== null) {
            http_response_code(400);
            echo json_encode(['error' => $error]);
            return;
        }

        $stmt = $this->db->prepare('SELECT id, email, password_hash, role, status FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Email ou mot de passe incorrect.']);
            return;
        }

        if ($user['status'] !== 'active') {
            http_response_code(403);
            echo json_encode(['error' => 'Ce compte est désactivé.']);
            return;
        }

        $token = Auth::createToken($this->db, (int) $user['id']);
        echo json_encode([
            'token' => $token,
            'user' => ['id' => (int) $user['id'], 'email' => $user['email'], 'role' => $user['role']],
        ]);
    }

    public function me()
    {
        header('Content-Type: application/json');
        $user = Auth::requireUser($this->db);
        echo json_encode(['user' => $user]);
    }

    public function logout()
    {
        header('Content-Type: application/json');
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            Auth::deleteToken($this->db, $matches[1]);
        }
        echo json_encode(['success' => true]);
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} [email, password, error] */
    private function readCredentials(): array
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = is_string($input['email'] ?? null) ? trim(strtolower($input['email'])) : '';
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [null, null, 'Email invalide.'];
        }
        if (mb_strlen($password) < 8) {
            return [null, null, 'Le mot de passe doit contenir au moins 8 caractères.'];
        }

        return [$email, $password, null];
    }
}
