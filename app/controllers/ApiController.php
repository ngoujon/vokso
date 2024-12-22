<?php

namespace App\Controllers;

use App\Models\ApiModel;
use App\Models\FileModel;
use App\Models\DatabaseModel;
use Exception;

class ApiController {
    private $api_model;
    private $file_model;
    private $db_model;

    public function __construct($api_key, $db_config) {
        // Création d'une instance de DatabaseModel avec la configuration de la base de données
        $this->db_model = new DatabaseModel($db_config, $api_key);
        
        // Création de l'instance de ApiModel en passant la clé API et l'instance de DatabaseModel
        $this->api_model = new ApiModel($api_key, $this->db_model);

        // Création de l'instance de FileModel
        $this->file_model = new FileModel();
    }

    public function handleRequest() {

        try {
            $startTime = microtime(true);

            // Validation des entrées utilisateur
            $user_input = htmlspecialchars(trim($_POST['user_input']));

            $timestamp = date('Ymd_His');
            $generation_id = 'gen_' . uniqid();

            // Récupération de la description depuis la base de données
            $text_description = $this->db_model->getDescription('texte');
            if (!$text_description) {
                throw new Exception('Description de texte introuvable dans la base de données.');
            }

            // Génération de la réponse texte
            $text_response = $this->api_model->generateResponse($user_input, $text_description);
            $bot_response = $text_response['choices'][0]['message']['content'] ?? 'Aucune réponse disponible.';

            // Sauvegarde de la réponse texte
            $text_filename = $this->file_model->saveTextToFile($bot_response, 'response', $timestamp);

            // Génération et sauvegarde de l'image
            $image_url = $this->api_model->generateImage($user_input, $timestamp);
            $image_filename = $this->file_model->saveImageToFile($image_url, $timestamp);

            // Génération et sauvegarde de l'audio
            $audio_data = $this->api_model->generateAudioResponse($bot_response, $timestamp);
            $audio_filename = $this->file_model->saveAudioToFile($audio_data, $timestamp);

            // Extraction et insertion des mots-clés
            $keywords_text = $this->api_model->getKeywordsFromApi($user_input);
            if ($keywords_text) {
                $this->db_model->insertCategories($generation_id, $keywords_text);
            }

            // Sauvegarde des données de génération
            $this->db_model->insertGenerationData(
                $generation_id,
                $user_input,
                $bot_response,
                $image_filename,
                $audio_filename
            );

            $endTime = microtime(true);
            $totalTime = $endTime - $startTime;

            // Réponse en JSON
            header('Content-Type: application/json');
            echo json_encode([
                'bot_response' => $bot_response,
                'image_file' => $image_filename,
                'audio_file' => $audio_filename,
                'total_time' => round($totalTime, 2)
            ]);
        } catch (Exception $e) {
            // Log des erreurs
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Une erreur est survenue : ' . $e->getMessage()]);
        }
    }
}
