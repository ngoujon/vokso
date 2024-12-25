<?php

namespace App\Controllers;

use App\Models\ApiModel;
use App\Models\FileModel;
use App\Models\DatabaseModel;

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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $startTime = microtime(true);

            // Validation de l'entrée utilisateur
            if (empty($_POST['user_input']) || !is_string($_POST['user_input'])) {
                $this->sendErrorResponse('Entrée utilisateur invalide.');
                return;
            }
            $user_input = trim($_POST['user_input']);

            // Génération d'un identifiant unique pour cette génération
            $timestamp = date('Ymd_His');
            $generation_id = 'gen_' . uniqid();

            // Récupérer la description du texte à partir de la base de données
            $text_description = $this->db_model->getDescription('texte');
            if (!$text_description) {
                $this->sendErrorResponse('Aucune description texte trouvée.');
                return;
            }

            // Générer la réponse texte
            $text_response = $this->api_model->generateResponse($user_input, $text_description);
            $bot_response = $text_response['choices'][0]['message']['content'] ?? 'Aucune réponse disponible.';

            // Sauvegarder la réponse texte dans un fichier
            $text_filename = $this->file_model->saveTextToFile($bot_response, 'response', $timestamp);
            if (!$text_filename) {
                $this->sendErrorResponse('Erreur lors de la sauvegarde du texte.');
                return;
            }

            // Générer une image à partir de la description
            $image_url = $this->api_model->generateImage($user_input, $timestamp);
            if (!$image_url) {
                $this->sendErrorResponse('Erreur lors de la génération de l\'image.');
                return;
            }

            // Sauvegarder l'image
            $image_filename = $this->file_model->saveImageToFile($image_url, $timestamp);
            if (!$image_filename) {
                $this->sendErrorResponse('Erreur lors de la sauvegarde de l\'image.');
                return;
            }

            // Générer la réponse audio
            $audio_data = $this->api_model->generateAudioResponse($bot_response, $timestamp);
            if (!$audio_data) {
                $this->sendErrorResponse('Erreur lors de la génération de l\'audio.');
                return;
            }

            // Sauvegarder l'audio
            $audio_filename = $this->file_model->saveAudioToFile($audio_data, $timestamp);
            if (!$audio_filename) {
                $this->sendErrorResponse('Erreur lors de la sauvegarde de l\'audio.');
                return;
            }

            // Extraire les mots-clés du texte et insérer dans la base de données
            $keywords_text = $this->api_model->getKeywordsFromApi($user_input);
            if (!$keywords_text) {
                $this->sendErrorResponse('Erreur lors de l\'extraction des mots-clés.');
                return;
            }
            $this->db_model->insertCategories($generation_id, $keywords_text);

            // Sauvegarder les informations de la génération dans la base de données
            $insert_result = $this->db_model->insertGenerationData($generation_id, $user_input, $bot_response, $image_filename, $audio_filename);
            if (!$insert_result) {
                $this->sendErrorResponse('Erreur lors de l\'insertion des données de génération dans la base.');
                return;
            }

            $endTime = microtime(true);
            $totalTime = $endTime - $startTime;

            // Répondre en JSON avec les résultats
            header('Content-Type: application/json');
            echo json_encode([
                'bot_response' => $bot_response,
                'image_file' => $image_filename,
                'audio_file' => $audio_filename,
                'total_time' => round($totalTime, 2)
            ]);
            exit;
        }
    }

    // Méthode pour envoyer une réponse d'erreur en JSON
    private function sendErrorResponse($message) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => true,
            'message' => $message
        ]);
        exit;
    }
}
