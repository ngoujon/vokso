<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../vendor/autoload.php';

use GuzzleHttp\Client;
use PDO;
use Dotenv\Dotenv;

class ApiController {
    private $api_key;
    private $pdo;

    public function __construct() {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();

        $this->api_key = $_ENV['API_KEY'];

        try {
            $this->pdo = new PDO(
                "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8",
                $_ENV['DB_USER'],
                $_ENV['DB_PASS']
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }

    public function generateResponse($user_input, $description) {
        $client = new Client();

        $response = $client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                "model" => "gpt-3.5-turbo",
                "messages" => [
                    ["role" => "system", "content" => $description],
                    ["role" => "user", "content" => $user_input]
                ]
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    public function saveTextToFile($text, $prefix, $timestamp) {
        $output_dir = __DIR__ . '/../output';
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0777, true);
        }
        $filename = "$output_dir/{$prefix}_{$timestamp}.txt";
        file_put_contents($filename, $text);
        return $filename;
    }

    public function generateImage($subject, $timestamp) {
        $client = new Client();
        $description = $this->getDescription('image');
        $prompt = "$description $subject";
        $size = "256x256";

        $response = $client->post('https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                "model" => "dall-e-2",
                "prompt" => $prompt,
                "n" => 1,
                "size" => $size
            ]
        ]);

        $responseData = json_decode($response->getBody(), true);
        if (isset($responseData['data'][0]['url'])) {
            $image_url = $responseData['data'][0]['url'];
            $image_response = $client->get($image_url);

            $output_dir = __DIR__ . '/../output';
            if (!is_dir($output_dir)) {
                mkdir($output_dir, 0777, true);
            }

            $file_name = "image_{$timestamp}.png";
            file_put_contents("$output_dir/$file_name", $image_response->getBody());

            return $file_name;
        }
        return null;
    }

    public function generateAudioResponse($text, $timestamp) {
        $client = new Client();

        $response = $client->post('https://api.openai.com/v1/audio/speech', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                "model" => "tts-1",
                "voice" => "alloy",
                "input" => $text,
                "speed" => 1.1
            ],
            'sink' => fopen('php://memory', 'w')  // Garde la réponse en mémoire
        ]);
        $output_dir = __DIR__ . '/../output';
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0777, true);
        }

        $file_name = "audio_{$timestamp}.mp3";
        file_put_contents("$output_dir/$file_name", $response->getBody());

        return $file_name;
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
        $client = new Client();
        $response = $client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                "model" => "gpt-3.5-turbo",
                "messages" => [
                    ["role" => "user", "content" => "Quels serait le mot qui permettrait de ranger dans une catégorie par thème le sujet: $user_input ? "]
                ]
            ]
        ]);

        $responseData = json_decode($response->getBody(), true);
        return $responseData['choices'][0]['message']['content'] ?? '';
    }

    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $startTime = microtime(true);
            
            $user_input = $_POST['user_input'];
            $timestamp = date('Ymd_His');
            $generation_id = 'gen_' . uniqid();

            $text_description = $this->getDescription('texte');
            $text_response = $this->generateResponse($user_input, $text_description);
            $bot_response = $text_response['choices'][0]['message']['content'] ?? 'Aucune réponse disponible.';

            $text_filename = $this->saveTextToFile($bot_response, 'response', $timestamp);

            $image_filename = $this->generateImage($user_input, $timestamp);
            $image_url = $image_filename ? "https://example.com/{$image_filename}" : null;

            $audio_filename = $this->generateAudioResponse($bot_response, $timestamp);
            $audio_url = $audio_filename ? "https://example.com/{$audio_filename}" : null;

            $keywords_text = $this->getKeywordsFromApi($user_input);
            $this->insertCategories($generation_id, $keywords_text);

            $this->insertGenerationData($generation_id, $user_input, $bot_response, $image_filename, $audio_filename);

            $endTime = microtime(true);
            $totalTime = $endTime - $startTime;

            header('Content-Type: application/json');
            echo json_encode([
                'bot_response' => $bot_response,
                'image_file' => $image_url,
                'audio_file' => $audio_url,
                'total_time' => round($totalTime, 2)
            ]);
            exit;
        }
    }
}
