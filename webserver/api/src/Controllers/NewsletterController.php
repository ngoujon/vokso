<?php

namespace App\Controllers;

use App\Services\MailerService;
use App\Utils\Logger;
use Dotenv\Dotenv;
use PDO;
use PDOException;

class NewsletterController
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

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
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

        $mailer = new MailerService(
            $_ENV['SMTP_HOST'] ?? 'mailhog',
            (int) ($_ENV['SMTP_PORT'] ?? 1025),
            $_ENV['MAIL_FROM'] ?? 'contact@vokso.fr'
        );
        $mailer->send(
            $email,
            'Bienvenue sur Vokso',
            "Merci de votre inscription !\n\nVous serez prévenu par mail des nouveautés de Vokso (nouveaux modes, nouvelles voix).\n\nÀ bientôt,\nL'équipe Vokso"
        );

        echo json_encode(['message' => 'Inscription confirmée, vérifiez vos mails']);
    }
}
