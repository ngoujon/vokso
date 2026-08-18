<?php

namespace App\Controllers;

use App\Services\AiProviderFactory;
use Exception;
use Dotenv\Dotenv;
use PDO;

class GenerationController
{
    private const MAX_INPUT_LENGTH = 300;

    private $db;
    private AiProviderFactory $ai;
    private string $outputDir;

    public function __construct()
    {
        // Charger les variables d'environnement
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $this->ai = new AiProviderFactory($_ENV);
        $this->outputDir = realpath(__DIR__ . '/../../../public') . '/output';

        // Connexion à la base de données
        $this->db = new PDO(
            'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
            $_ENV['DB_USER'],
            $_ENV['DB_PASS']
        );
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function generateText()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
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

        try {
            $generatedText = $this->generatePodcastText($userInput);
            $textFileName = $this->storeOutput('responses', 'response_' . $this->getCurrentDateTime() . '.txt', $generatedText);
        } catch (Exception $e) {
            $this->fail('Erreur lors de la génération du texte', $e);
            return;
        }

        try {
            $imagePrompt = str_replace('###REPLACE###', $generatedText, $this->getPrompt('image'));
            $imageContent = $this->ai->imageGenerator()->generateImage($imagePrompt);
            $imageFileName = $this->storeOutput('images', 'image_' . $this->getCurrentDateTime() . '.png', $imageContent);
        } catch (Exception $e) {
            $this->fail('Erreur lors de la génération de l\'image', $e);
            return;
        }

        try {
            $synthesizer = $this->ai->speechSynthesizer();
            $audioContent = $synthesizer->synthesize($generatedText);
            $audioFileName = $this->storeOutput(
                'audios',
                'audio_' . $this->getCurrentDateTime() . '.' . $synthesizer->audioExtension(),
                $audioContent
            );
        } catch (Exception $e) {
            $this->fail('Erreur lors de la génération de l\'audio', $e);
            return;
        }

        try {
            $categoryKeyword = $this->generateCategory($userInput);
            $idcategorie = $this->saveOrGetCategory($categoryKeyword);
        } catch (Exception $e) {
            $this->fail('Erreur lors de la catégorisation', $e);
            return;
        }

        $generationId = $this->saveGeneration($userInput, $generatedText, $imageFileName, $audioFileName, $idcategorie);

        echo json_encode([
            'message' => 'Texte, image et audio générés avec succès',
            'file' => $textFileName,
            'generated_text' => $generatedText,
            'image' => $imageFileName,
            'audio' => $audioFileName,
            'generation_id' => $generationId,
            'idcategorie' => $idcategorie,
        ]);
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

        // Certains modèles locaux ajoutent une ponctuation ou une phrase : on ne
        // conserve que le premier mot, tronqué à la taille de la colonne.
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

    private function fail(string $message, Exception $e): void
    {
        error_log('[generation] ' . $message . ' : ' . $e->getMessage());
        http_response_code(500);
        // Le détail technique reste dans les logs sauf en mode debug explicite.
        $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
        echo json_encode(['error' => $debug ? $message . ' : ' . $e->getMessage() : $message]);
    }

    private function saveOrGetCategory($categoryKeyword)
    {
        // Vérifier si la catégorie existe déjà
        $stmt = $this->db->prepare('SELECT idcategorie FROM categorie WHERE keyword = :keyword');
        $stmt->execute([':keyword' => $categoryKeyword]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row['idcategorie']; // Retourner l'idcategorie existant
        }

        // Sinon, insérer une nouvelle catégorie
        $stmt = $this->db->prepare('INSERT INTO categorie (keyword) VALUES (:keyword)');
        $stmt->execute([':keyword' => $categoryKeyword]);

        return $this->db->lastInsertId(); // Retourner l'id de la nouvelle catégorie
    }

    private function saveGeneration($title, $description, $imageUrl, $audioUrl, $idcategorie)
    {
        $generationId = 'gen_' . uniqid(); // Générer un ID unique avec "gen_"
        $stmt = $this->db->prepare('INSERT INTO generations (generation_id, title, description, image_url, audio_url, idcategorie) VALUES (:generation_id, :title, :description, :image_url, :audio_url, :idcategorie)');
        $stmt->execute([
            ':generation_id' => $generationId,
            ':title' => $title,
            ':description' => $description,
            ':image_url' => $imageUrl,
            ':audio_url' => $audioUrl,
            ':idcategorie' => $idcategorie
        ]);
        return $generationId;
    }

    private function sanitizeInput($input)
    {
        return trim(htmlspecialchars(strip_tags($input)));
    }

    private function getPrompt($type)
    {
        $stmt = $this->db->prepare('SELECT content FROM prompt WHERE type = :type');
        $stmt->execute([':type' => $type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['content'] : '';
    }

    private function getCurrentDateTime()
    {
        return date('Ymd_His');
    }
}
