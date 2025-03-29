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
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization");
            header("Access-Control-Allow-Credentials: true");
        }

        // Gérer les requêtes OPTIONS (preflight)
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit(0);
        }
    }
} 