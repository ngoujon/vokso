<?php

namespace App\Models;

use PDO;
use Dotenv\Dotenv;
use GuzzleHttp\Client;

class DatabaseModel {
    private $pdo;
    private $api_key;

    public function __construct(array $db_config) {
        // Load environment variables once on class construction
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        // Retrieve the API key directly; throws exception if not set
        $this->api_key = $_ENV['API_KEY'] ?? null;
        if (!$this->api_key) {
            throw new \RuntimeException("API key not set in .env file");
        }

        // Database connection setup
        $dsn = "mysql:host={$db_config['DB_HOST']};dbname={$db_config['DB_NAME']};charset=utf8";
        try {
            $this->pdo = new PDO($dsn, $db_config['DB_USER'], $db_config['DB_PASS'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false, // Use native prepared statements
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException("Database connection error: " . $e->getMessage());
        }
    }

    public function insertGenerationData(string $generation_id, string $title, string $description, string $image_url, string $audio_url): void {
        $sql = "INSERT INTO generations (generation_id, title, description, image_url, audio_url) 
                VALUES (:generation_id, :title, :description, :image_url, :audio_url)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(compact('generation_id', 'title', 'description', 'image_url', 'audio_url'));
    }

    public function insertCategories(string $generation_id, string $keywords_text): void {
        $keywords = array_filter(array_map('trim', explode(',', $keywords_text)));

        $sql = "INSERT INTO categorie (generation_id, keyword) VALUES (:generation_id, :keyword)";
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($keywords as $keyword) {
            $stmt->execute([':generation_id' => $generation_id, ':keyword' => $keyword]);
        }
    }

    public function getDescription(string $type): ?string {
        $stmt = $this->pdo->prepare("SELECT description FROM prompts WHERE type = :type LIMIT 1");
        $stmt->execute([':type' => $type]);
        return $stmt->fetchColumn() ?: null; // fetchColumn is faster for single column
    }

    public function getKeywordsFromApi(string $user_input): string {
        if (!$this->api_key) {
            throw new \RuntimeException("API key is missing. Please check your .env file.");
        }

        $client = new Client(['base_uri' => 'https://api.openai.com']);
        $response = $client->post('/v1/chat/completions', [
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

    public function getLastGenerations(): array {
        $stmt = $this->pdo->query("SELECT * FROM generations ORDER BY created_at DESC LIMIT 3");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
