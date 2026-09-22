<?php

namespace App\Services;

use App\Utils\CostEstimator;
use App\Utils\Logger;
use Exception;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use PDO;

/**
 * Exécute la chaîne complète de génération (texte, image, audio, catégorie)
 * pour un job donné et journalise la progression en base au fil des étapes.
 * Extrait de GenerationController pour pouvoir tourner hors requête HTTP,
 * dans le processus détaché lancé par le worker CLI.
 */
class PodcastGenerator
{
    private const MAX_INPUT_LENGTH = 300;

    public function __construct(
        private PDO $db,
        private AiProviderFactory $ai,
        private string $outputDir
    ) {
    }

    public function process(string $jobId): void
    {
        $job = $this->fetchJob($jobId);
        if (!$job) {
            return;
        }

        $userInput = $job['input'];

        if ($job['source_type'] === 'audio') {
            $this->updateJob($jobId, 'processing', 'transcription', 5);
            try {
                $userInput = $this->transcribeAudio($job['audio_path']);
            } catch (Exception $e) {
                $this->failJob($jobId, 'Erreur lors de la transcription audio', $e);
                return;
            } finally {
                $this->deleteUploadedAudio($job['audio_path']);
            }

            $stmt = $this->db->prepare('UPDATE generation_jobs SET input = :input WHERE job_id = :job_id');
            $stmt->execute([':input' => $userInput, ':job_id' => $jobId]);
        }

        // Le texte et la catégorie ne dépendent que du sujet d'entrée (pas l'un
        // de l'autre) : les lancer en parallèle via des promesses Guzzle
        // économise un aller-retour réseau complet par rapport à deux appels
        // séquentiels.
        $this->updateJob($jobId, 'processing', 'text', 10);
        try {
            [$generatedText, $categoryKeyword] = $this->generateTextAndCategory($userInput);
            $this->storeOutput('responses', 'response_' . $this->getCurrentDateTime() . '.txt', $generatedText);
            $costText = CostEstimator::textCost(
                $this->textProvider(),
                $this->textModel(),
                $userInput,
                $generatedText
            );
        } catch (Exception $e) {
            $this->failJob($jobId, 'Erreur lors de la génération du texte', $e);
            return;
        }

        // Idem pour l'image et l'audio : toutes deux ne dépendent que du texte
        // généré, jamais l'une de l'autre, donc autant les lancer de front.
        $this->updateJob($jobId, 'processing', 'media', 45);
        try {
            [$imageContent, $audioContent, $audioExtension] = $this->generateImageAndAudio($generatedText);

            $imageFileName = $this->storeOutput('images', 'image_' . $this->getCurrentDateTime() . '.png', $imageContent);
            $this->convertImageToWebP($imageFileName);
            $costImage = CostEstimator::imageCost($_ENV['OPENAI_IMAGE_MODEL'] ?? 'dall-e-3');

            $audioFileName = $this->storeOutput(
                'audios',
                'audio_' . $this->getCurrentDateTime() . '.' . $audioExtension,
                $audioContent
            );
            $costAudio = CostEstimator::speechCost($this->speechProvider(), $this->speechModel(), $generatedText);
        } catch (Exception $e) {
            $this->failJob($jobId, $e->getMessage(), $e);
            return;
        }

        $this->updateJob($jobId, 'processing', 'finalizing', 90);
        $idcategorie = $this->saveOrGetCategory($categoryKeyword);

        $generationId = $this->saveGeneration(
            $userInput,
            $generatedText,
            $imageFileName,
            $audioFileName,
            $idcategorie,
            $job['user_id'] ?? null,
            $costText,
            $costImage,
            $costAudio
        );

        $stmt = $this->db->prepare(
            'UPDATE generation_jobs SET status = "done", step = "done", progress = 100, generation_id = :gid WHERE job_id = :job_id'
        );
        $stmt->execute([':gid' => $generationId, ':job_id' => $jobId]);
    }

    private function fetchJob(string $jobId): ?array
    {
        $stmt = $this->db->prepare('SELECT input, source_type, audio_path, user_id FROM generation_jobs WHERE job_id = :job_id');
        $stmt->execute([':job_id' => $jobId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Transcrit le fichier audio déposé et le ramène au même format qu'un
     * sujet saisi au clavier : un texte assaini et borné à MAX_INPUT_LENGTH,
     * pour pouvoir alimenter la même chaîne de prompts.
     */
    private function transcribeAudio(?string $audioPath): string
    {
        if ($audioPath === null || $audioPath === '') {
            throw new Exception('Aucun fichier audio associé à ce job.');
        }

        $transcript = $this->ai->transcriber()->transcribe($audioPath);
        $transcript = trim(htmlspecialchars(strip_tags($transcript)));
        if ($transcript === '') {
            throw new Exception('La transcription audio est vide.');
        }

        return mb_substr($transcript, 0, self::MAX_INPUT_LENGTH);
    }

    private function deleteUploadedAudio(?string $audioPath): void
    {
        if ($audioPath !== null && is_file($audioPath)) {
            @unlink($audioPath);
        }
    }

    private function updateJob(string $jobId, string $status, string $step, int $progress): void
    {
        $stmt = $this->db->prepare(
            'UPDATE generation_jobs SET status = :status, step = :step, progress = :progress WHERE job_id = :job_id'
        );
        $stmt->execute([
            ':status' => $status,
            ':step' => $step,
            ':progress' => $progress,
            ':job_id' => $jobId,
        ]);
    }

    private function failJob(string $jobId, string $message, Exception $e): void
    {
        Logger::get()->error($message, [
            'service' => 'generation-worker',
            'job_id' => $jobId,
            'exception' => get_class($e),
            'reason' => $e->getMessage(),
        ]);
        $stmt = $this->db->prepare(
            'UPDATE generation_jobs SET status = "error", error_message = :error WHERE job_id = :job_id'
        );
        $stmt->execute([':error' => $message, ':job_id' => $jobId]);
    }

    /**
     * Lance en parallèle la génération du texte principal et celle de la
     * catégorie (deux appels indépendants au même modèle de texte, tous deux
     * fonction du seul sujet d'entrée) et attend les deux résultats.
     *
     * @return array{0: string, 1: string} [texte généré, mot-clé de catégorie]
     */
    private function generateTextAndCategory(string $userInput): array
    {
        $textPrompt = $this->getPrompt('texte');
        if ($textPrompt === '') {
            throw new Exception('Le prompt de type "texte" est introuvable dans la base de données.');
        }

        $injectionPrompt = $this->getPrompt('injection');
        if ($injectionPrompt === '') {
            throw new Exception('Le prompt de protection contre les injections est introuvable dans la base de données.');
        }

        $keywordPrompt = $this->getPrompt('keyword');
        if ($keywordPrompt === '') {
            throw new Exception('Le prompt de type "keyword" est introuvable dans la base de données.');
        }

        $generator = $this->ai->textGenerator();
        $results = PromiseUtils::settle([
            'text' => $generator->generateTextAsync(
                $injectionPrompt,
                str_replace('###REPLACE###', $userInput, $textPrompt)
            ),
            'category' => $generator->generateTextAsync(
                'Répondez avec un seul mot décrivant la catégorie d\'activité ou le domaine correspondant au sujet donné.',
                str_replace('###REPLACE###', $userInput, $keywordPrompt)
            ),
        ])->wait();

        if ($results['text']['state'] !== 'fulfilled') {
            throw $this->toException($results['text']['reason']);
        }
        if ($results['category']['state'] !== 'fulfilled') {
            throw $this->toException($results['category']['reason']);
        }

        return [$results['text']['value'], $this->sanitizeCategory($results['category']['value'])];
    }

    private function sanitizeCategory(string $rawCategory): string
    {
        $category = trim(preg_replace('/[^\p{L}\p{N}\- ]/u', '', $rawCategory));
        $category = explode(' ', trim($category))[0] ?? '';
        if ($category === '') {
            throw new Exception('Aucune catégorie exploitable n\'a été renvoyée par le modèle.');
        }

        return mb_substr($category, 0, 50);
    }

    /**
     * Lance en parallèle la génération de l'image et celle de l'audio (deux
     * appels indépendants, tous deux fonction du seul texte déjà généré) et
     * attend les deux résultats.
     *
     * @return array{0: string, 1: string, 2: string} [image binaire, audio binaire, extension audio]
     */
    private function generateImageAndAudio(string $generatedText): array
    {
        $imagePrompt = str_replace('###REPLACE###', $generatedText, $this->getPrompt('image'));
        $synthesizer = $this->ai->speechSynthesizer();

        $results = PromiseUtils::settle([
            'image' => $this->ai->imageGenerator()->generateImageAsync($imagePrompt),
            'audio' => $synthesizer->synthesizeAsync($generatedText),
        ])->wait();

        if ($results['image']['state'] !== 'fulfilled') {
            throw new Exception('Erreur lors de la génération de l\'image : ' . $this->toException($results['image']['reason'])->getMessage());
        }
        if ($results['audio']['state'] !== 'fulfilled') {
            throw new Exception('Erreur lors de la génération de l\'audio : ' . $this->toException($results['audio']['reason'])->getMessage());
        }

        return [$results['image']['value'], $results['audio']['value'], $synthesizer->audioExtension()];
    }

    private function toException(mixed $reason): Exception
    {
        return $reason instanceof Exception ? $reason : new Exception((string) $reason);
    }

    /** Écrit un fichier de sortie et retourne son nom. */
    private function storeOutput(string $subDir, string $fileName, string $content): string
    {
        $directory = $this->outputDir . '/' . $subDir;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new Exception('Impossible de créer le dossier de sortie : ' . $subDir);
        }

        if (file_put_contents($directory . '/' . $fileName, $content) === false) {
            throw new Exception('Impossible d\'écrire le fichier : ' . $fileName);
        }

        return $fileName;
    }

    private function saveOrGetCategory(string $categoryKeyword)
    {
        $stmt = $this->db->prepare('SELECT idcategorie FROM categorie WHERE keyword = :keyword');
        $stmt->execute([':keyword' => $categoryKeyword]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row['idcategorie'];
        }

        $stmt = $this->db->prepare('INSERT INTO categorie (keyword) VALUES (:keyword)');
        $stmt->execute([':keyword' => $categoryKeyword]);

        return $this->db->lastInsertId();
    }

    private function saveGeneration(
        $title,
        $description,
        $imageUrl,
        $audioUrl,
        $idcategorie,
        ?int $userId,
        float $costText,
        float $costImage,
        float $costAudio
    ): string {
        $generationId = 'gen_' . uniqid();
        $stmt = $this->db->prepare(
            'INSERT INTO generations
                (generation_id, title, description, image_url, audio_url, idcategorie, user_id, cost_text, cost_image, cost_audio, cost_total)
             VALUES
                (:generation_id, :title, :description, :image_url, :audio_url, :idcategorie, :user_id, :cost_text, :cost_image, :cost_audio, :cost_total)'
        );
        $stmt->execute([
            ':generation_id' => $generationId,
            ':title' => $title,
            ':description' => $description,
            ':image_url' => $imageUrl,
            ':audio_url' => $audioUrl,
            ':idcategorie' => $idcategorie,
            ':user_id' => $userId,
            ':cost_text' => $costText,
            ':cost_image' => $costImage,
            ':cost_audio' => $costAudio,
            ':cost_total' => $costText + $costImage + $costAudio,
        ]);
        return $generationId;
    }

    private function textProvider(): string
    {
        return strtolower(trim((string) ($_ENV['AI_TEXT_PROVIDER'] ?? 'openai')));
    }

    private function textModel(): string
    {
        return $this->textProvider() === 'ollama'
            ? (string) ($_ENV['OLLAMA_TEXT_MODEL'] ?? '')
            : (string) ($_ENV['OPENAI_TEXT_MODEL'] ?? 'gpt-4o-mini');
    }

    private function speechProvider(): string
    {
        return strtolower(trim((string) ($_ENV['AI_SPEECH_PROVIDER'] ?? 'openai')));
    }

    private function speechModel(): string
    {
        return (string) ($_ENV['OPENAI_SPEECH_MODEL'] ?? 'tts-1-hd');
    }

    private function getPrompt(string $type): string
    {
        $stmt = $this->db->prepare('SELECT content FROM prompt WHERE type = :type');
        $stmt->execute([':type' => $type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['content'] : '';
    }

    private function getCurrentDateTime(): string
    {
        return date('Ymd_His');
    }

    private function convertImageToWebP(string $pngFileName): void
    {
        $pngPath = $this->outputDir . '/images/' . $pngFileName;
        if (!file_exists($pngPath)) {
            return;
        }

        $webpPath = str_replace('.png', '.webp', $pngPath);
        if (extension_loaded('imagick')) {
            try {
                $image = new \Imagick($pngPath);
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality(80);
                $image->writeImage($webpPath);
                $image->clear();
            } catch (\Exception $e) {
                Logger::get()->error('Erreur conversion WebP via Imagick', ['exception' => get_class($e), 'reason' => $e->getMessage()]);
            }
        } elseif (extension_loaded('gd')) {
            try {
                $image = imagecreatefrompng($pngPath);
                if ($image !== false) {
                    imagewebp($image, $webpPath, 80);
                    imagedestroy($image);
                }
            } catch (\Exception $e) {
                Logger::get()->error('Erreur conversion WebP via GD', ['exception' => get_class($e), 'reason' => $e->getMessage()]);
            }
        }
    }
}
