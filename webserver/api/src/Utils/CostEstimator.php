<?php

namespace App\Utils;

/**
 * Estimation du coût (en dollars US) de chaque étape de génération, à partir
 * des grilles tarifaires publiques des modèles configurés. Ce sont des
 * estimations (le nombre exact de tokens consommés par le fournisseur n'est
 * pas remonté par les intégrations actuelles) basées sur ~4 caractères par
 * token, suffisantes pour comparer les générations entre elles côté admin.
 */
class CostEstimator
{
    // $ pour 1 million de tokens (entrée / sortie), grilles publiques OpenAI et Mistral.
    private const TEXT_MODEL_PRICES = [
        'gpt-4o-mini' => ['in' => 0.15, 'out' => 0.60],
        'gpt-4o' => ['in' => 2.50, 'out' => 10.00],
        'gpt-4.1-mini' => ['in' => 0.40, 'out' => 1.60],
        'mistral-small-latest' => ['in' => 0.15, 'out' => 0.60],
        'mistral-medium-latest' => ['in' => 1.50, 'out' => 7.50],
        'mistral-large-latest' => ['in' => 0.50, 'out' => 1.50],
    ];
    private const DEFAULT_TEXT_PRICE = ['in' => 0.15, 'out' => 0.60];

    // $ / image générée (taille standard 1024x1024).
    private const IMAGE_MODEL_PRICES = [
        'dall-e-3' => 0.040,
        'dall-e-2' => 0.020,
        // 100 $ / 1000 images (tarif public Mistral, outil "image_generation").
        'mistral-medium-latest' => 0.10,
        'mistral-large-latest' => 0.10,
    ];
    private const DEFAULT_IMAGE_PRICE = 0.040;

    // $ pour 1 million de caractères synthétisés.
    private const SPEECH_MODEL_PRICES = [
        'tts-1' => 15.00,
        'tts-1-hd' => 30.00,
        // 0,016 $ / 1 000 caractères (tarif public Mistral, Voxtral TTS).
        'voxtral-mini-tts-2603' => 16.00,
        'voxtral-mini-tts-latest' => 16.00,
    ];
    private const DEFAULT_SPEECH_PRICE = 30.00;

    // Fournisseurs auto-hébergés (Ollama Cloud, TTS/Whisper locaux) : pas de
    // coût direct à l'appel (infra déjà payée), on ne compte que ce qui
    // passe réellement par un fournisseur facturé à l'usage (OpenAI, Mistral).
    public static function textCost(string $provider, string $model, string $inputText, string $outputText): float
    {
        if ($provider !== 'openai' && $provider !== 'mistral') {
            return 0.0;
        }

        $prices = self::TEXT_MODEL_PRICES[$model] ?? self::DEFAULT_TEXT_PRICE;
        $inTokens = self::estimateTokens($inputText);
        $outTokens = self::estimateTokens($outputText);

        return ($inTokens / 1_000_000 * $prices['in']) + ($outTokens / 1_000_000 * $prices['out']);
    }

    public static function imageCost(string $model): float
    {
        return self::IMAGE_MODEL_PRICES[$model] ?? self::DEFAULT_IMAGE_PRICE;
    }

    public static function speechCost(string $provider, string $model, string $text): float
    {
        if ($provider !== 'openai' && $provider !== 'mistral') {
            return 0.0;
        }

        $price = self::SPEECH_MODEL_PRICES[$model] ?? self::DEFAULT_SPEECH_PRICE;
        return mb_strlen($text) / 1_000_000 * $price;
    }

    private static function estimateTokens(string $text): float
    {
        return mb_strlen($text) / 4;
    }
}
