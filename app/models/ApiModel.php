<?php

namespace App\Models;

use GuzzleHttp\Client;

class ApiModel
{
    private $api_key;
    private $db_model;

    // Passer l'instance de DatabaseModel au constructeur
    public function __construct($api_key, DatabaseModel $db_model)
    {
        $this->api_key = $api_key;
        $this->db_model = $db_model; // stocker l'instance de DatabaseModel
    }

    // Génération de la réponse texte
    public function generateResponse($user_input, $description)
    {
        $client = new Client();
    
        $response = $client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                "model" => "gpt-4o", // Utilisation du modèle gpt-4o
                "temperature" => 0.2, // Température faible pour des réponses plus déterministes
                "messages" => [
                    ["role" => "system", "content" => $description],
                    ["role" => "user", "content" => $user_input]
                ]
            ]
        ]);
    
        return json_decode($response->getBody(), true);
    }
    

    // Génération d'une image en utilisant la description depuis la base de données
    public function generateImage($subject, $timestamp)
    {
        $client = new Client();

        // Récupérer la description depuis la base de données
        $description = $this->db_model->getDescription('image'); // Appeler la méthode getDescription de DatabaseModel

        if (!$description) {
            throw new \RuntimeException("Description for image not found in the database.");
        }

        $prompt = "$description $subject";
        $size = "1024x1024";

        $response = $client->post('https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                "model" => "dall-e-3",
                "prompt" => $prompt,
                "n" => 1,
                "size" => $size
            ]
        ]);

        $responseData = json_decode($response->getBody(), true);
        if (isset($responseData['data'][0]['url'])) {
            return $responseData['data'][0]['url'];
        }
        return null;
    }

    // Génération de la réponse audio (inchangée)
    public function generateAudioResponse($text, $timestamp)
    {
        $client = new Client();

        $response = $client->post('https://api.openai.com/v1/audio/speech', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                "model" => "tts-1-hd",
                "voice" => "nova",
                "input" => $text,
                "speed" => 1
            ],
            'sink' => fopen('php://memory', 'w')  // Garde la réponse en mémoire
        ]);

        return $response->getBody();
    }
}
