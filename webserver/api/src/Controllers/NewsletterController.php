<?php

namespace App\Controllers;

use App\Services\MailerService;
use App\Utils\Logger;
use App\Utils\RateLimiter;
use Dotenv\Dotenv;
use PDO;
use PDOException;

class NewsletterController
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

    public function subscribe()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit(0);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['message' => 'Méthode non autorisée']);
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($this->rateLimiter->tooManyRequests($ip, 'newsletter')) {
            http_response_code(429);
            echo json_encode(['message' => 'Trop de tentatives, réessayez plus tard']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        // Champ piège invisible (voir site/index.html) : un humain ne le
        // remplit jamais. On répond succès pour ne pas signaler l'échec aux
        // robots, comme pour le formulaire de contact.
        if (trim((string) ($input['website'] ?? '')) !== '') {
            echo json_encode(['message' => 'Inscription confirmée, vérifiez vos mails']);
            return;
        }

        $email = trim((string) ($input['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['message' => 'Adresse email invalide']);
            return;
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO newsletter_subscribers (email) VALUES (:email)'
            );
            $stmt->execute([':email' => $email]);
        } catch (PDOException $e) {
            // Code 23000 : contrainte unique déjà présente en base, l'email
            // est donc déjà inscrit. Ce n'est pas une erreur pour l'appelant.
            if ($e->getCode() !== '23000') {
                Logger::get()->error($e->getMessage(), ['controller' => 'newsletter', 'exception' => get_class($e)]);
                http_response_code(500);
                echo json_encode(['message' => "L'inscription a échoué, réessayez plus tard"]);
                return;
            }
        }

        $mailer = MailerService::fromEnv();
        $mailer->send(
            $email,
            'Bienvenue sur Vokso',
            "Merci de votre inscription !\n\nVous serez prévenu par mail des nouveautés de Vokso (nouveaux modes, nouvelles voix).\n\nÀ bientôt,\nL'équipe Vokso"
        );

        echo json_encode(['message' => 'Inscription confirmée, vérifiez vos mails']);
    }
}
