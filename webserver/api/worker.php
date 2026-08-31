<?php

// Traite un job de génération en arrière-plan. Lancé en processus détaché par
// GenerationController::generateText() (pas de queue/worker dédié dans
// l'infra actuelle : voir docs/AUDIT.md, cohérent avec RateLimiter qui évite
// aussi toute dépendance type Redis).
//
// Usage : php worker.php <job_id>

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\AiProviderFactory;
use App\Services\PodcastGenerator;
use App\Utils\ErrorTracking;
use App\Utils\Logger;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
ErrorTracking::init();
Logger::init();

$jobId = $argv[1] ?? null;
if (!$jobId) {
    fwrite(STDERR, "Usage: worker.php <job_id>\n");
    exit(1);
}

$db = new PDO(
    'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
    $_ENV['DB_USER'],
    $_ENV['DB_PASS']
);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ai = new AiProviderFactory($_ENV);
$outputDir = realpath(__DIR__ . '/../public') . '/output';

try {
    (new PodcastGenerator($db, $ai, $outputDir))->process($jobId);
} catch (Throwable $e) {
    Logger::get()->error($e->getMessage(), [
        'service' => 'worker',
        'job_id' => $jobId,
        'exception' => get_class($e),
        'trace' => $e->getTraceAsString(),
    ]);
    \Sentry\captureException($e);
    throw $e;
}
