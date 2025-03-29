<?php

namespace App\Utils;

class CorsHandler
{
    private static $allowedOrigins = [];

    public static function init()
    {
        // Charger les origines autorisées depuis les variables d'environnement
        $allowedOriginsString = $_ENV['ALLOWED_ORIGINS'] ?? '';
        self::$allowedOrigins = array_map('trim', explode(',', $allowedOriginsString));
    }

    public static function handleCors()
    {
        // Récupérer l'origine de la requête
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Vérifier si l'origine est autorisée
        if (in_array($origin, self::$allowedOrigins)) {
            // Toujours envoyer les en-têtes CORS pour les origines autorisées
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Max-Age: 86400"); // 24 heures
        }

        // Gérer les requêtes OPTIONS (preflight)
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            // Pour les requêtes OPTIONS, on renvoie un code 200 (OK)
            http_response_code(200);
            exit(0);
        }
    }
} 