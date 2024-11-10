<?php

namespace App\Models;

use PDO;
use Dotenv\Dotenv;

class DatabaseModel {
    private $pdo;
    private $api_key;

    public function __construct($db_config) {
        // Charger les variables d'environnement depuis le fichier .env
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../'); // Assurez-vous que le chemin est correct (niveau racine)
        $dotenv->load();
        
        // Récupérer la clé API du fichier .env
        $this->api_key = $_ENV['API_KEY'] ?? null; // Utiliser null si la clé n'est pas définie dans le .env

        if (!$this->api_key) {
            throw new \Exception("API key not set in .env file");
        }

        // Connexion à la base de données
        try {
            $this->pdo = new PDO(
                "mysql:host={$db_config['DB_HOST']};dbname={$db_config['DB_NAME']};charset=utf8",
                $db_config['DB_USER'],
                $db_config['DB_PASS']
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }

    public function insertGenerationData($generation_id, $title, $description, $image_url, $audio_url) {
        $stmt = $this->pdo->prepare("INSERT INTO generations (generation_id, title, description, image_url, audio_url) 
                                     VALUES (:generation_id, :title, :description, :image_url, :audio_url)");
        $stmt->execute([
            ':generation_id' => $generation_id,
            ':title' => $title,
            ':description' => $description,
            ':image_url' => $image_url,
            ':audio_url' => $audio_url
        ]);
    }

    public function insertCategories($generation_id, $keywords_text) {
        $keywords = explode(',', $keywords_text);

        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (!empty($keyword)) {
                $stmt = $this->pdo->prepare("INSERT INTO categorie (generation_id, keyword) VALUES (:generation_id, :keyword)");
                $stmt->execute([
                    ':generation_id' => $generation_id,
                    ':keyword' => $keyword
                ]);
            }
        }
    }

    public function getDescription($type) {
        $stmt = $this->pdo->prepare("SELECT description FROM prompts WHERE type = :type LIMIT 1");
        $stmt->execute([':type' => $type]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['description'] : null;
    }

    public function getKeywordsFromApi($user_input) {
        // Vérifier que l'API key est bien définie
        if (!$this->api_key) {
            throw new \Exception("API key is missing. Please check your .env file.");
        }

        $client = new \GuzzleHttp\Client();
        $response = $client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                "model" => "gpt-3.5-turbo",
                "messages" => [
                    ["role" => "user", "content" => "Quels serait le mot qui permettrait de ranger dans une catégorie par thème le sujet: $user_input ?"]
                ]
            ]
        ]);

        $responseData = json_decode($response->getBody(), true);
        return $responseData['choices'][0]['message']['content'] ?? '';
    }
}
