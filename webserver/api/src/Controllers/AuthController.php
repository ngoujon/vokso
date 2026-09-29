<?php

namespace App\Controllers;

use App\Services\MailerService;
use App\Utils\Auth;
use App\Utils\Logger;
use App\Utils\PasswordPolicy;
use App\Utils\RateLimiter;
use App\Utils\Totp;
use Dotenv\Dotenv;
use PDO;

class AuthController
{
    private PDO $db;
    private RateLimiter $rateLimiter;

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

        $this->rateLimiter = new RateLimiter(
            $this->db,
            (int) ($_ENV['RATE_LIMIT_MAX_REQUESTS'] ?? 5),
            (int) ($_ENV['RATE_LIMIT_WINDOW_SECONDS'] ?? 3600)
        );
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

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($this->rateLimiter->tooManyRequests($ip, 'auth-register')) {
            http_response_code(429);
            echo json_encode(['error' => 'Trop de tentatives, réessayez plus tard.']);
            return;
        }

        // Champ piège invisible (voir Login.js) : un humain ne le remplit
        // jamais. On répond avec la même erreur générique que pour un email
        // déjà invalide, pour ne pas signaler l'échec aux robots.
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (trim((string) ($input['website'] ?? '')) !== '') {
            http_response_code(400);
            echo json_encode(['error' => 'Inscription impossible, réessayez plus tard.']);
            return;
        }

        [$email, $password, $error] = $this->readCredentials();
        if ($error !== null) {
            http_response_code(400);
            echo json_encode(['error' => $error]);
            return;
        }

        $policyError = PasswordPolicy::validate($password);
        if ($policyError !== null) {
            http_response_code(400);
            echo json_encode(['error' => $policyError]);
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

        $this->sendWelcomeEmail($email);

        $token = Auth::createToken($this->db, $userId);
        http_response_code(201);
        echo json_encode([
            'token' => $token,
            'user' => ['id' => $userId, 'email' => $email, 'role' => 'user'],
        ]);
    }

    /** Un échec d'envoi ne doit jamais bloquer la création du compte. */
    private function sendWelcomeEmail(string $email): void
    {
        try {
            $mailer = MailerService::fromEnv();
            $mailer->send(
                $email,
                'Bienvenue sur Vokso',
                "Votre compte Vokso est créé.\n\n"
                . "Vos podcasts sont générés sur une infrastructure hébergée en Europe, "
                . "avec des modèles ouverts : vos données ne servent jamais à entraîner un modèle tiers.\n\n"
                . "Vous pouvez dès maintenant générer votre premier épisode depuis votre espace.\n\n"
                . "À bientôt,\nL'équipe Vokso"
            );
        } catch (\Throwable $e) {
            Logger::get()->error($e->getMessage(), ['controller' => 'auth', 'action' => 'welcome-email', 'exception' => get_class($e)]);
        }
    }

    public function login()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            return;
        }

        // Limite par IP : un attaquant qui brute-force un mot de passe ou fait
        // du credential stuffing émet des dizaines/centaines de requêtes,
        // là où un utilisateur légitime qui se trompe en tape rarement plus
        // de quelques-unes.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($this->rateLimiter->tooManyRequests($ip, 'auth-login')) {
            http_response_code(429);
            echo json_encode(['error' => 'Trop de tentatives, réessayez plus tard.']);
            return;
        }

        [$email, $password, $error] = $this->readCredentials();
        if ($error !== null) {
            http_response_code(400);
            echo json_encode(['error' => $error]);
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT id, email, password_hash, role, status, must_change_password, totp_secret, totp_enabled
             FROM users WHERE email = :email'
        );
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

        // La 2FA n'est imposée qu'aux comptes admin qui l'ont activée : le
        // mot de passe seul a déjà été vérifié ci-dessus, il ne manque que le
        // code TOTP pour délivrer le jeton.
        if ($user['role'] === 'admin' && $user['totp_enabled']) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $code = is_string($input['totp_code'] ?? null) ? $input['totp_code'] : '';

            if ($code === '') {
                http_response_code(401);
                echo json_encode(['error' => 'Code de double authentification requis.', 'code' => 'totp_required']);
                return;
            }

            if (!Totp::verify((string) $user['totp_secret'], $code)) {
                http_response_code(401);
                echo json_encode(['error' => 'Code de double authentification invalide.', 'code' => 'totp_required']);
                return;
            }
        }

        $token = Auth::createToken($this->db, (int) $user['id']);
        echo json_encode([
            'token' => $token,
            'user' => [
                'id' => (int) $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'must_change_password' => (bool) $user['must_change_password'],
                'totp_enabled' => (bool) $user['totp_enabled'],
            ],
        ]);
    }

    public function me()
    {
        header('Content-Type: application/json');
        $user = Auth::requireUser($this->db, allowPendingPasswordChange: true);
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

    /** Changement de mot de passe, y compris pour lever un changement imposé (compte de démarrage). */
    public function changePassword()
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            return;
        }

        $user = Auth::requireUser($this->db, allowPendingPasswordChange: true);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $currentPassword = is_string($input['current_password'] ?? null) ? $input['current_password'] : '';
        $newPassword = is_string($input['new_password'] ?? null) ? $input['new_password'] : '';

        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute([':id' => $user['id']]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($currentPassword, $hash)) {
            http_response_code(401);
            echo json_encode(['error' => 'Mot de passe actuel incorrect.']);
            return;
        }

        $policyError = PasswordPolicy::validate($newPassword);
        if ($policyError !== null) {
            http_response_code(400);
            echo json_encode(['error' => $policyError]);
            return;
        }

        $this->db->prepare(
            'UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id'
        )->execute([':hash' => password_hash($newPassword, PASSWORD_BCRYPT), ':id' => $user['id']]);

        echo json_encode(['success' => true]);
    }

    /** Étape 1 de l'activation 2FA (admin uniquement) : génère un secret en attente de confirmation. */
    public function twoFactorSetup()
    {
        header('Content-Type: application/json');
        $admin = Auth::requireAdmin($this->db);

        $secret = Totp::generateSecret();
        $this->db->prepare(
            'UPDATE users SET totp_secret = :secret, totp_enabled = 0 WHERE id = :id'
        )->execute([':secret' => $secret, ':id' => $admin['id']]);

        echo json_encode([
            'secret' => $secret,
            'otpauth_uri' => Totp::provisioningUri($secret, $admin['email']),
        ]);
    }

    /** Étape 2 : confirme la possession du secret via un code TOTP avant d'activer la 2FA. */
    public function twoFactorEnable()
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            return;
        }

        $admin = Auth::requireAdmin($this->db);

        $stmt = $this->db->prepare('SELECT totp_secret FROM users WHERE id = :id');
        $stmt->execute([':id' => $admin['id']]);
        $secret = $stmt->fetchColumn();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $code = is_string($input['totp_code'] ?? null) ? $input['totp_code'] : '';

        if (!$secret || !Totp::verify((string) $secret, $code)) {
            http_response_code(400);
            echo json_encode(['error' => 'Code invalide, réessayez.']);
            return;
        }

        $this->db->prepare('UPDATE users SET totp_enabled = 1 WHERE id = :id')->execute([':id' => $admin['id']]);
        echo json_encode(['success' => true]);
    }

    /** Désactivation de la 2FA : exige le mot de passe pour éviter qu'un jeton volé suffise à l'affaiblir. */
    public function twoFactorDisable()
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            return;
        }

        $admin = Auth::requireAdmin($this->db);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';

        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute([':id' => $admin['id']]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($password, $hash)) {
            http_response_code(401);
            echo json_encode(['error' => 'Mot de passe incorrect.']);
            return;
        }

        $this->db->prepare(
            'UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = :id'
        )->execute([':id' => $admin['id']]);

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
