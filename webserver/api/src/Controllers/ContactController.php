<?php

namespace App\Controllers;

use App\Services\CaptchaService;
use App\Services\MailerService;
use App\Utils\Logger;
use App\Utils\RateLimiter;
use Dotenv\Dotenv;
use PDO;

class ContactController
{
    private const MAX_NAME_LENGTH = 200;
    private const MAX_MESSAGE_LENGTH = 5000;

    private PDO $db;
    private CaptchaService $captcha;
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

        $this->captcha = new CaptchaService($_ENV['CAPTCHA_SECRET'] ?? '');
        $this->rateLimiter = new RateLimiter(
            $this->db,
            (int) ($_ENV['RATE_LIMIT_MAX_REQUESTS'] ?? 5),
            (int) ($_ENV['RATE_LIMIT_WINDOW_SECONDS'] ?? 3600)
        );
    }

    /** Jeton invisible à récupérer au chargement du formulaire, voir CaptchaService et src/pages/Contact.js. */
    public function challenge()
    {
        header('Content-Type: application/json');
        echo json_encode(['token' => $this->captcha->issueToken()]);
    }

    public function send()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit(0);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($this->rateLimiter->tooManyRequests($ip, 'contact')) {
            http_response_code(429);
            echo json_encode(['error' => 'Trop de messages envoyés, réessayez plus tard.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        // Champ piège invisible (voir Contact.js) : un humain ne le remplit
        // jamais, seuls les robots qui soumettent tous les champs du
        // formulaire le font. On répond succès pour ne pas leur signaler
        // l'échec (sinon ils ajustent leur script).
        if (trim((string) ($input['website'] ?? '')) !== '') {
            echo json_encode(['message' => 'Message envoyé, merci !']);
            return;
        }

        if (!$this->captcha->verify($input['captchaToken'] ?? null)) {
            http_response_code(400);
            echo json_encode(['error' => 'Vérification anti-spam échouée, rechargez la page et réessayez.']);
            return;
        }

        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $message = trim((string) ($input['message'] ?? ''));

        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            http_response_code(400);
            echo json_encode(['error' => 'Nom invalide.']);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Adresse email invalide.']);
            return;
        }
        if ($message === '' || mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            http_response_code(400);
            echo json_encode(['error' => 'Message invalide.']);
            return;
        }

        $mailer = MailerService::fromEnv();
        $sent = $mailer->send(
            $_ENV['CONTACT_TO'] ?? $_ENV['MAIL_FROM'] ?? 'contact@vokso.fr',
            'Nouveau message via le formulaire de contact',
            "De : {$name} <{$email}>\n\n{$message}",
            $email
        );

        if (!$sent) {
            Logger::get()->error('Envoi du message de contact échoué', ['controller' => 'contact']);
            http_response_code(502);
            echo json_encode(['error' => "L'envoi a échoué, réessayez plus tard."]);
            return;
        }

        echo json_encode(['message' => 'Message envoyé, merci !']);
    }
}
