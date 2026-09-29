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

    // Limites anti-abus (voir App\Support\RateLimiter et GenerationQuota).
    'rate_limit' => [
        'max_requests' => (int) env('RATE_LIMIT_MAX_REQUESTS', 5),
        'window_seconds' => (int) env('RATE_LIMIT_WINDOW_SECONDS', 3600),
    ],
    'generation_monthly_quota' => max(0, (int) env('GENERATION_MONTHLY_QUOTA', 5)),

    // Jeton anti-spam invisible du formulaire de contact (CaptchaService).
    'captcha_secret' => (string) env('CAPTCHA_SECRET', ''),

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

    /*
     * Identité légale du vendeur, pour régénérer les factures émises du temps
     * des anciens abonnements (conservation légale 10 ans). Plus aucune
     * facture n'est émise : le service est gratuit.
     */
    'invoice_seller' => [
        'name' => trim((string) env('INVOICE_SELLER_NAME', '')),
        'address_line1' => trim((string) env('INVOICE_SELLER_ADDRESS', '')),
        'postal_code' => trim((string) env('INVOICE_SELLER_POSTAL_CODE', '')),
        'city' => trim((string) env('INVOICE_SELLER_CITY', '')),
        'country_code' => strtoupper((string) env('INVOICE_SELLER_COUNTRY', 'FR')),
        'siren' => trim((string) env('INVOICE_SELLER_SIREN', '')),
        'vat_number' => trim((string) env('INVOICE_SELLER_VAT_NUMBER', '')),
    ],

];
