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
    private $temp_dir;
    private $pdo;

    public function __construct() {
        // Charger les variables d'environnement
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();

        // Récupérer la clé API depuis le fichier .env
        $this->api_key = $_ENV['API_KEY'];
        $this->temp_dir = __DIR__ . '/../output/temp';

        // Créer le dossier temporaire si nécessaire et lui donner les permissions adéquates
        if (!is_dir($this->temp_dir)) {
            mkdir($this->temp_dir, 0777, true);
        }

        // Connexion à la base de données avec les informations du fichier .env
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
            ],
            'sink' => $this->temp_dir . '/response_' . uniqid() . '.tmp'
        ]);

        return json_decode(file_get_contents($response->getBody()->getMetadata('uri')), true);
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

        // Récupérer la description pour la génération d'image
        $description = $this->getDescription('image');  // Utilisation de getDescription pour l'image
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
            ],
            'sink' => $this->temp_dir . '/image_' . uniqid() . '.tmp'
        ]);

        $responseData = json_decode(file_get_contents($response->getBody()->getMetadata('uri')), true);
        if (isset($responseData['data'][0]['url'])) {
            $image_url = $responseData['data'][0]['url'];
            $image_response = $client->get($image_url);
            $file_path = __DIR__ . "/../output/image_{$timestamp}.png";
            file_put_contents($file_path, $image_response->getBody());
            return $file_path;
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
                "input" => $text
            ],
            'sink' => $this->temp_dir . '/audio_' . uniqid() . '.tmp'
        ]);

        $output_dir = __DIR__ . '/../output';
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0777, true);
        }

        $audio_filename = "$output_dir/audio_{$timestamp}.mp3";
        file_put_contents($audio_filename, file_get_contents($response->getBody()->getMetadata('uri')));

        return $audio_filename;
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

    public function getDescription($type) {
        $stmt = $this->pdo->prepare("SELECT description FROM prompts WHERE type = :type LIMIT 1");
        $stmt->execute([':type' => $type]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['description'] : null;
    }

    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $startTime = microtime(true);
            
            $user_input = $_POST['user_input'];
            $timestamp = date('Ymd_His');
            $generation_id = 'gen_' . uniqid();

            // Récupérer la description pour la génération de texte
            $text_description = $this->getDescription('texte');  // Description pour le texte
            $text_response = $this->generateResponse($user_input, $text_description);
            $bot_response = $text_response['choices'][0]['message']['content'] ?? 'Aucune réponse disponible.';

            // Sauvegarder la réponse textuelle
            $text_filename = $this->saveTextToFile($bot_response, 'response', $timestamp);

            // Récupérer la description pour la génération d'image
            $image_description = $this->getDescription('image');  // Description pour l'image
            $image_filename = $this->generateImage($user_input, $timestamp);
            $image_url = $image_filename ? "{$image_filename}" : null;

            // Générer la réponse audio
            $audio_filename = $this->generateAudioResponse($bot_response, $timestamp);
            $audio_url = $audio_filename ? "{$audio_filename}" : null;

            // Insérer les données générées dans la base de données
            $this->insertGenerationData($generation_id, $user_input, $bot_response, $image_url, $audio_url);

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
