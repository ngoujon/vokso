<?php

namespace App\Models;

use GuzzleHttp\Client;

class ApiModel {
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
            return $responseData['data'][0]['url'];
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

        return $response->getBody();
    }

    private function getDescription($type) {
        // Utilise la logique pour obtenir une description pour générer l'image, par exemple
        // Vous pouvez récupérer la description dans une base de données ou configurer des descriptions statiques
        return "A description for the type $type";
    }
}
