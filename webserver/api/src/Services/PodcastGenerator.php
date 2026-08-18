<?php

namespace App\Services;

use Exception;
use PDO;

/**
 * Exécute la chaîne complète de génération (texte, image, audio, catégorie)
 * pour un job donné et journalise la progression en base au fil des étapes.
 * Extrait de GenerationController pour pouvoir tourner hors requête HTTP,
 * dans le processus détaché lancé par le worker CLI.
 */
class PodcastGenerator
{
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
        $this->updateJob($jobId, 'processing', 'text', 10);

        try {
            $generatedText = $this->generatePodcastText($userInput);
            $this->storeOutput('responses', 'response_' . $this->getCurrentDateTime() . '.txt', $generatedText);
        } catch (Exception $e) {
            $this->failJob($jobId, 'Erreur lors de la génération du texte', $e);
            return;
        }

        $this->updateJob($jobId, 'processing', 'image', 35);
        try {
            $imagePrompt = str_replace('###REPLACE###', $generatedText, $this->getPrompt('image'));
            $imageContent = $this->ai->imageGenerator()->generateImage($imagePrompt);
            $imageFileName = $this->storeOutput('images', 'image_' . $this->getCurrentDateTime() . '.png', $imageContent);
        } catch (Exception $e) {
            $this->failJob($jobId, 'Erreur lors de la génération de l\'image', $e);
            return;
        }

        $this->updateJob($jobId, 'processing', 'audio', 65);
        try {
            $synthesizer = $this->ai->speechSynthesizer();
            $audioContent = $synthesizer->synthesize($generatedText);
            $audioFileName = $this->storeOutput(
                'audios',
                'audio_' . $this->getCurrentDateTime() . '.' . $synthesizer->audioExtension(),
                $audioContent
            );
        } catch (Exception $e) {
            $this->failJob($jobId, 'Erreur lors de la génération de l\'audio', $e);
            return;
        }

        $this->updateJob($jobId, 'processing', 'category', 90);
        try {
            $categoryKeyword = $this->generateCategory($userInput);
            $idcategorie = $this->saveOrGetCategory($categoryKeyword);
        } catch (Exception $e) {
            $this->failJob($jobId, 'Erreur lors de la catégorisation', $e);
            return;
        }

        $generationId = $this->saveGeneration($userInput, $generatedText, $imageFileName, $audioFileName, $idcategorie);

        $stmt = $this->db->prepare(
            'UPDATE generation_jobs SET status = "done", step = "done", progress = 100, generation_id = :gid WHERE job_id = :job_id'
        );
        $stmt->execute([':gid' => $generationId, ':job_id' => $jobId]);
    }

    private function fetchJob(string $jobId): ?array
    {
        $stmt = $this->db->prepare('SELECT input FROM generation_jobs WHERE job_id = :job_id');
        $stmt->execute([':job_id' => $jobId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
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
        error_log('[generation-worker] ' . $message . ' : ' . $e->getMessage());
        $stmt = $this->db->prepare(
            'UPDATE generation_jobs SET status = "error", error_message = :error WHERE job_id = :job_id'
        );
        $stmt->execute([':error' => $message, ':job_id' => $jobId]);
    }

    private function generatePodcastText(string $userInput): string
    {
        $textPrompt = $this->getPrompt('texte');
        if ($textPrompt === '') {
            throw new Exception('Le prompt de type "texte" est introuvable dans la base de données.');
        }

        $injectionPrompt = $this->getPrompt('injection');
        if ($injectionPrompt === '') {
            throw new Exception('Le prompt de protection contre les injections est introuvable dans la base de données.');
        }

        return $this->ai->textGenerator()->generateText(
            $injectionPrompt,
            str_replace('###REPLACE###', $userInput, $textPrompt)
        );
    }

    private function generateCategory(string $userInput): string
    {
        $keywordPrompt = $this->getPrompt('keyword');
        if ($keywordPrompt === '') {
            throw new Exception('Le prompt de type "keyword" est introuvable dans la base de données.');
        }

        $category = $this->ai->textGenerator()->generateText(
            'Répondez avec un seul mot décrivant la catégorie d\'activité ou le domaine correspondant au sujet donné.',
            str_replace('###REPLACE###', $userInput, $keywordPrompt)
        );

        $category = trim(preg_replace('/[^\p{L}\p{N}\- ]/u', '', $category));
        $category = explode(' ', trim($category))[0] ?? '';
        if ($category === '') {
            throw new Exception('Aucune catégorie exploitable n\'a été renvoyée par le modèle.');
        }

        return mb_substr($category, 0, 50);
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

    private function saveGeneration($title, $description, $imageUrl, $audioUrl, $idcategorie): string
    {
        $generationId = 'gen_' . uniqid();
        $stmt = $this->db->prepare(
            'INSERT INTO generations (generation_id, title, description, image_url, audio_url, idcategorie) VALUES (:generation_id, :title, :description, :image_url, :audio_url, :idcategorie)'
        );
        $stmt->execute([
            ':generation_id' => $generationId,
            ':title' => $title,
            ':description' => $description,
            ':image_url' => $imageUrl,
            ':audio_url' => $audioUrl,
            ':idcategorie' => $idcategorie,
        ]);
        return $generationId;
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
}
