<?php

namespace App\Controllers;

use GuzzleHttp\Client;
use Exception;
use Dotenv\Dotenv;
use PDO;

class GenerationController
{
    private $db;
    private $apiKey;

    public function __construct()
    {
        // Charger les variables d'environnement
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        // Récupérer la clé API
        $this->apiKey = $_ENV['OPENAI_API_KEY'];
        if (!$this->apiKey) {
            throw new Exception('OPENAI_API_KEY is missing in environment variables.');
        }

        // Connexion à la base de données
        $this->db = new PDO(
            'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASS']
        );
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function generateText()
    {
        // Vérifier si la requête est en POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $inputData = json_decode(file_get_contents('php://input'), true);

            if (isset($inputData['input'])) {
                $userInput = $inputData['input'];

                // Sécurisation contre les prompt injections
                $userInput = $this->sanitizeInput($userInput);

                // Récupérer le prompt pour la génération de texte
                $textPrompt = $this->getPrompt('texte');

                try {
                    // Générer le texte via OpenAI
                    $openAiResponse = $this->callOpenAiApi($userInput, $textPrompt);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => $e->getMessage()]);
                    return;
                }

                if ($openAiResponse) {
                    $generatedText = $openAiResponse['choices'][0]['message']['content'];

                    // Nom du fichier texte avec date/heure formatée
                    $textFileName = 'response_' . $this->getCurrentDateTime() . '.txt';
                    file_put_contents(__DIR__ . '/../../../public/output/responses/' . $textFileName, $generatedText);

                    // Générer une image avec DALL-E 3
                    try {
                        $imagePrompt = $this->getPrompt('image');
                        $imageResponse = $this->generateImageWithDallE($generatedText, $imagePrompt);
                        $imageUrl = $imageResponse['data'][0]['url'];
                        $imageContent = file_get_contents($imageUrl);

                        // Nom du fichier image avec date/heure formatée
                        $imageFileName = 'image_' . $this->getCurrentDateTime() . '.png';
                        file_put_contents(__DIR__ . '/../../../public/output/images/' . $imageFileName, $imageContent);
                    } catch (Exception $e) {
                        http_response_code(500);
                        echo json_encode(['error' => 'Erreur lors de la génération de l\'image : ' . $e->getMessage()]);
                        return;
                    }

                    // Générer un fichier audio avec OpenAI TTS
                    try {
                        $audioFileName = $this->generateAudioWithTTS($generatedText);
                    } catch (Exception $e) {
                        http_response_code(500);
                        echo json_encode(['error' => 'Erreur lors de la génération de l\'audio : ' . $e->getMessage()]);
                        return;
                    }

                    // Appel API pour la catégorisation et récupération/assignation de l'idcategorie
                    try {
                        $categoryKeyword = $this->generateCategory($inputData['input']);
                        $idcategorie = $this->saveOrGetCategory($categoryKeyword);
                    } catch (Exception $e) {
                        http_response_code(500);
                        echo json_encode(['error' => 'Erreur lors de la catégorisation : ' . $e->getMessage()]);
                        return;
                    }

                    // Enregistrer les informations dans la table generations avec l'idcategorie
                    $generationId = $this->saveGeneration($inputData['input'], $generatedText, $imageFileName, $audioFileName, $idcategorie);

                    // Retourner la réponse
                    header('Content-Type: application/json');
                    echo json_encode([
                        'message' => 'Texte, image et audio générés avec succès',
                        'file' => $textFileName,
                        'generated_text' => $generatedText,
                        'image' => $imageFileName,
                        'audio' => $audioFileName,
                        'generation_id' => $generationId,
                        'idcategorie' => $idcategorie
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erreur lors de la génération du texte']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Valeur manquante']);
            }
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
        }
    }

    private function callOpenAiApi($userInput, $prompt)
    {
        if (!$this->apiKey) {
            throw new Exception('API Key is missing.');
        }

        $client = new Client();
        
        // Récupérer le prompt de protection depuis la base de données
        $injectionPrompt = $this->getPrompt('injection');
        if (empty($injectionPrompt)) {
            throw new Exception('Le prompt de protection contre les injections est introuvable dans la base de données.');
        }

        $description = str_replace('###REPLACE###', $userInput, $prompt);

        try {
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    "model" => "gpt-4",
                    "temperature" => 0.2,
                    "messages" => [
                        ["role" => "system", "content" => $injectionPrompt],
                        ["role" => "user", "content" => $description]
                    ]
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            if (isset($data['error'])) {
                throw new Exception("OpenAI Error: " . $data['error']['message']);
            }

            return $data;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorMessage = $e->getMessage();
            $response = $e->getResponse();
            if ($response) {
                $responseBody = (string) $response->getBody();
                $errorMessage .= " - Response: " . $responseBody;
            }
            throw new Exception("Request to OpenAI failed: " . $errorMessage);
        }
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

    private function generateImageWithDallE($text, $prompt)
    {
        if (!$this->apiKey) {
            throw new Exception('API Key is missing.');
        }

        $client = new Client();

        $prompt = str_replace('###REPLACE###', $text, $prompt);

        try {
            $response = $client->post('https://api.openai.com/v1/images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'dall-e-3',
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '1024x1024',
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            if (isset($data['error'])) {
                throw new Exception("DALL-E Error: " . $data['error']['message']);
            }

            return $data;
        } catch (Exception $e) {
            throw new Exception('Error with DALL-E: ' . $e->getMessage());
        }
    }

    private function generateAudioWithTTS($text)
    {
        if (!$this->apiKey) {
            throw new Exception('API Key is missing.');
        }

        $client = new Client();
        $voice = rand(0, 1) ? "nova" : "onyx";

        try {
            $response = $client->post('https://api.openai.com/v1/audio/speech', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    "model" => "tts-1-hd",
                    "voice" => $voice,
                    "input" => $text,
                    "speed" => 1
                ],
                'sink' => fopen('php://memory', 'w')
            ]);

            $audioContent = $response->getBody();
            $audioFileName = 'audio_' . $this->getCurrentDateTime() . '.mp3';
            file_put_contents(__DIR__ . '/../../../public/output/audios/' . $audioFileName, $audioContent);

            return $audioFileName;
        } catch (Exception $e) {
            throw new Exception('Error generating audio: ' . $e->getMessage());
        }
    }

    private function sanitizeInput($input)
    {
        return htmlspecialchars(strip_tags($input));
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

    private function generateCategory($userInput)
    {
        if (!$this->apiKey) {
            throw new Exception('API Key is missing.');
        }

        $client = new Client();
    
        // Récupérer le prompt de type "keyword" depuis la base de données
        $keywordPrompt = $this->getPrompt('keyword');
        if (empty($keywordPrompt)) {
            throw new Exception('Le prompt de type "keyword" est introuvable dans la base de données.');
        }
    
        // Remplacer le placeholder par l'entrée utilisateur
        $promptContent = str_replace('###REPLACE###', $userInput, $keywordPrompt);
    
        try {
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    "model" => "gpt-4",
                    "temperature" => 0.2,
                    "messages" => [
                        ["role" => "system", "content" => "Répondez avec un seul mot décrivant la catégorie d'activité ou le domaine correspondant au sujet donné."],
                        ["role" => "user", "content" => $promptContent]
                    ]
                ]
            ]);
    
            $data = json_decode($response->getBody(), true);
    
            if (isset($data['choices'][0]['message']['content'])) {
                return trim($data['choices'][0]['message']['content']);
            } else {
                throw new Exception('Aucune réponse valide reçue de l\'API');
            }
        } catch (Exception $e) {
            throw new Exception('Erreur lors de la catégorisation : ' . $e->getMessage());
        }
    }
}