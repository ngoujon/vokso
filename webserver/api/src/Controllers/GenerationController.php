<?php

namespace App\Controllers;

use App\Utils\RateLimiter;
use Dotenv\Dotenv;
use PDO;

class GenerationController
{
    private const MAX_INPUT_LENGTH = 300;

    private PDO $db;
    private RateLimiter $rateLimiter;

    public function __construct()
    {
        // Charger les variables d'environnement
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        // Connexion à la base de données
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

    /**
     * Met la génération en file d'attente et répond immédiatement avec un
     * identifiant de suivi : la chaîne texte → image → audio → catégorie
     * (plusieurs minutes) tourne dans un processus détaché (voir worker.php),
     * plus dans le thread de la requête HTTP.
     */
    public function generateText()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            return;
        }

        // Quota par IP : cet endpoint déclenche 2 à 3 appels IA payants (texte,
        // image, audio), il ne doit pas pouvoir être appelé en boucle par un
        // visiteur anonyme. On se base sur REMOTE_ADDR (pas X-Forwarded-For,
        // trivialement falsifiable tant qu'aucun reverse proxy de confiance
        // n'est configuré en amont).
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if ($this->rateLimiter->tooManyRequests($clientIp, 'generation')) {
            http_response_code(429);
            echo json_encode(['error' => 'Trop de requêtes. Merci de réessayer plus tard.']);
            return;
        }

        $inputData = json_decode(file_get_contents('php://input'), true);
        if (!isset($inputData['input']) || !is_string($inputData['input']) || trim($inputData['input']) === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Valeur manquante']);
            return;
        }

        // Sécurisation contre les prompt injections : une seule valeur nettoyée
        // est utilisée pour tous les appels IA et pour l'enregistrement en base.
        $userInput = $this->sanitizeInput($inputData['input']);
        if (mb_strlen($userInput) > self::MAX_INPUT_LENGTH) {
            http_response_code(400);
            echo json_encode(['error' => 'Le sujet ne doit pas dépasser ' . self::MAX_INPUT_LENGTH . ' caractères.']);
            return;
        }

        $jobId = 'job_' . bin2hex(random_bytes(16));
        $stmt = $this->db->prepare(
            'INSERT INTO generation_jobs (job_id, status, step, progress, input) VALUES (:job_id, "pending", "queued", 0, :input)'
        );
        $stmt->execute([':job_id' => $jobId, ':input' => $userInput]);

        $this->dispatch($jobId);

        http_response_code(202);
        echo json_encode([
            'message' => 'Génération mise en file d\'attente',
            'job_id' => $jobId,
            'status' => 'pending',
        ]);
    }

    /** Suivi de progression d'un job, interrogé par le front en polling. */
    public function status()
    {
        header('Content-Type: application/json');

        $jobId = $_GET['id'] ?? '';
        if (!is_string($jobId) || $jobId === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Identifiant de suivi manquant']);
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT j.status, j.step, j.progress, j.error_message, j.generation_id,
                    g.title, g.image_url, g.audio_url
             FROM generation_jobs j
             LEFT JOIN generations g ON g.generation_id = j.generation_id
             WHERE j.job_id = :job_id'
        );
        $stmt->execute([':job_id' => $jobId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Suivi introuvable']);
            return;
        }

        echo json_encode([
            'status' => $row['status'],
            'step' => $row['step'],
            'progress' => (int) $row['progress'],
            'error' => $row['error_message'],
            'generation_id' => $row['generation_id'],
            'title' => $row['title'],
            'image' => $row['image_url'],
            'audio' => $row['audio_url'],
        ]);
    }

    /** Lance le traitement du job dans un processus PHP CLI détaché. */
    private function dispatch(string $jobId): void
    {
        $php = escapeshellarg(PHP_BINARY);
        $script = escapeshellarg(__DIR__ . '/../../worker.php');
        $arg = escapeshellarg($jobId);
        exec("$php $script $arg > /dev/null 2>&1 &");
    }

    private function sanitizeInput($input)
    {
        return trim(htmlspecialchars(strip_tags($input)));
    }
}
