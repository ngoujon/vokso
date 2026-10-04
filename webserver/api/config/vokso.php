<?php

/*
 * Réglages propres à Vokso. Toute lecture de l'environnement passe par ce
 * fichier (jamais env() dans le code : incompatible avec config:cache).
 */
return [

    // Base publique du site (pages épisode, sitemap).
    'public_url' => rtrim((string) env('PUBLIC_URL', 'https://vokso.fr'), '/'),

    // Fichiers générés (images, audios, textes) servis sous /static par Apache.
    'output_dir' => env('OUTPUT_DIR', base_path('../public/output')),

    // Fichiers audio déposés, en attente de transcription par le worker.
    'upload_dir' => env('UPLOAD_DIR', storage_path('uploads')),

    // Limites anti-abus des routes sensibles (connexion, contact...), voir
    // App\Support\RateLimiter.
    'rate_limit' => [
        'max_requests' => (int) env('RATE_LIMIT_MAX_REQUESTS', 5),
        'window_seconds' => (int) env('RATE_LIMIT_WINDOW_SECONDS', 3600),
    ],

    // Génération sans compte : plafonds par IP et plafond global sur 24 h
    // glissantes (garde-fou du coût des appels IA), voir GenerationLimits.
    'generation_limits' => [
        'per_ip_hourly' => max(0, (int) env('GENERATION_IP_HOURLY_LIMIT', 5)),
        'per_ip_daily' => max(0, (int) env('GENERATION_IP_DAILY_LIMIT', 10)),
        'global_daily' => max(0, (int) env('GENERATION_GLOBAL_DAILY_LIMIT', 150)),
    ],

    // Jeton anti-spam invisible du formulaire de contact (CaptchaService).
    'captcha_secret' => (string) env('CAPTCHA_SECRET', ''),

    /*
     * API réservée au workflow n8n (POST /api/n8n/podcasts) : n8n tourne sur
     * le même serveur (vps-3962b7dc.vps.ovh.net, même IP que vokso.fr).
     * Double verrou : adresse d'origine dans la liste ci-dessous (IP publiques
     * du serveur, boucle locale, réseaux Docker) ET jeton secret en
     * « Authorization: Bearer ». Seule l'empreinte SHA-256 du jeton est
     * versionnée ; pour changer de jeton, définir N8N_API_TOKEN_SHA256.
     */
    'n8n' => [
        'token_sha256' => strtolower((string) env('N8N_API_TOKEN_SHA256', '0d91ec5c0b152a2c5948ce0b574419b56be5dee2289f1aae267536fa53656f3e')),
        'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'N8N_ALLOWED_IPS',
            '51.254.211.144,2001:41d0:305:2100::aeed,127.0.0.1,::1,172.16.0.0/12'
        ))))),
    ],

    'contact_to' => env('CONTACT_TO', env('MAIL_FROM', 'contact@vokso.fr')),

    /*
     * Moteurs d'IA. Tout passe par Mistral (texte, image, voix, transcription) ;
     * le texte peut aussi passer par Ollama Cloud.
     */
    'ai' => [
        'text_provider' => strtolower(trim((string) env('AI_TEXT_PROVIDER', 'mistral'))),

        'mistral' => [
            'api_key' => (string) env('MISTRAL_API_KEY', ''),
            'base_url' => (string) env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1'),
            'text_model' => (string) env('MISTRAL_TEXT_MODEL', 'mistral-small-latest'),
            'text_agent_id' => env('MISTRAL_TEXT_AGENT_ID') ?: null,
            'image_model' => (string) env('MISTRAL_IMAGE_MODEL', 'mistral-medium-latest'),
            'image_agent_id' => env('MISTRAL_IMAGE_AGENT_ID') ?: null,
            'transcription_model' => (string) env('MISTRAL_TRANSCRIPTION_MODEL', 'voxtral-mini-latest'),
            'speech_model' => (string) env('MISTRAL_SPEECH_MODEL', 'voxtral-mini-tts-latest'),
            'speech_voice' => (string) env('MISTRAL_SPEECH_VOICE', 'fr_marie_neutral'),
        ],

        'ollama' => [
            'api_key' => (string) env('OLLAMA_API_KEY', ''),
            'base_url' => (string) env('OLLAMA_BASE_URL', 'https://ollama.com/v1'),
            'text_model' => (string) env('OLLAMA_TEXT_MODEL', ''),
        ],
    ],

];
