<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../vendor/autoload.php';

use GuzzleHttp\Client;

class ApiController {
    private $api_key;

    public function __construct($api_key) {
        $this->api_key = $api_key;
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
            mkdir($output_dir);
        }
        $filename = "$output_dir/{$prefix}_{$timestamp}.txt";
        file_put_contents($filename, $text);
        return $filename;
    }

    public function generateImage($subject, $timestamp) {
        $client = new Client();
        $description = "Créer une image minimaliste représentant le sujet suivant";
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
                "model" => "tts-1",  // Ajustez selon l'API
                "voice" => "alloy",               // Ajustez selon le modèle de voix disponible
                "input" => $text
            ]
        ]);

        // Crée le répertoire de sortie s'il n'existe pas
        $output_dir = __DIR__ . '/../output';
        if (!is_dir($output_dir)) {
            mkdir($output_dir);
        }

        // Nom du fichier audio
        $audio_filename = "$output_dir/audio_{$timestamp}.mp3";
        file_put_contents($audio_filename, $response->getBody());
        
        return $audio_filename;
    }

    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user_input = $_POST['user_input'];
            $timestamp = date('Ymd_His');

            $description = "La génération du texte doit être suffisamment longue et détaillée. L'exposé sera composé de la structure suivante: 
    Introduction - Définir clairement le sujet, expliquer son importance et donner un aperçu des principaux points qui seront abordés. 
    Historique - Résumer l'origine du sujet, son évolution et les événements marquants qui l'ont façonné. 
    Personnalités clés - Identifier les figures influentes, passées ou actuelles, et détailler leurs contributions majeures. 
    Concepts et théories - Présenter les idées fondamentales et les théories clés associées au sujet, avec des exemples concrets. 
    Applications et impact actuel - Décrire comment le sujet est appliqué dans le monde contemporain et son influence sur différents secteurs ou technologies. 
    Défis et perspectives - Mettre en lumière les controverses, les défis actuels et les enjeux futurs liés au sujet, tout en suggérant des pistes d'évolution possibles.";
            
            $responseData = $this->generateResponse($user_input, $description);
            $bot_response = $responseData['choices'][0]['message']['content'] ?? 'Aucune réponse disponible.';

            $text_filename = $this->saveTextToFile($bot_response, 'response', $timestamp);
            $text_tokens = $responseData['usage']['total_tokens'] ?? 0;

            // Génération d'image
            $image_filename = $this->generateImage($user_input, $timestamp);
            $image_tokens = 0;

            // Génération de la réponse audio
            $audio_filename = $this->generateAudioResponse($bot_response, $timestamp);

            header('Content-Type: application/json');
            echo json_encode([
                'bot_response' => $bot_response,
                'image_file' => $image_filename,
                'audio_file' => $audio_filename,  // Ajout du fichier audio à la réponse
                'text_tokens' => $text_tokens,
                'image_tokens' => $image_tokens,
            ]);
            exit;
        }
    }
}
