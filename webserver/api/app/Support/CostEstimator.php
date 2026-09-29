<?php

namespace App\Support;

/**
 * Estimation du coût (en dollars US) de chaque étape de génération, à partir
 * des grilles tarifaires publiques de Mistral. Ce sont des estimations
 * (~4 caractères par token), suffisantes pour comparer les générations
 * entre elles côté admin.
 */
class CostEstimator
{
    // $ pour 1 million de tokens (entrée / sortie).
    private const TEXT_MODEL_PRICES = [
        'mistral-small-latest' => ['in' => 0.15, 'out' => 0.60],
        'mistral-medium-latest' => ['in' => 1.50, 'out' => 7.50],
        'mistral-large-latest' => ['in' => 0.50, 'out' => 1.50],
    ];
    private const DEFAULT_TEXT_PRICE = ['in' => 0.15, 'out' => 0.60];

    // $ / image générée : 100 $ / 1000 images (outil "image_generation").
    private const IMAGE_PRICE = 0.10;

    // $ pour 1 million de caractères synthétisés (Voxtral TTS : 0,016 $ / 1 000 caractères).
    private const SPEECH_PRICE = 16.00;

    /** Ollama Cloud (forfait) n'a pas de coût direct à l'appel : seul Mistral est compté. */
    public static function textCost(string $provider, string $model, string $inputText, string $outputText): float
    {
        if ($provider !== 'mistral') {
            return 0.0;
        }

        $prices = self::TEXT_MODEL_PRICES[$model] ?? self::DEFAULT_TEXT_PRICE;

        return (self::estimateTokens($inputText) / 1_000_000 * $prices['in'])
            + (self::estimateTokens($outputText) / 1_000_000 * $prices['out']);
    }

    public static function imageCost(): float
    {
        return self::IMAGE_PRICE;
    }

    public static function speechCost(string $text): float
    {
        return mb_strlen($text) / 1_000_000 * self::SPEECH_PRICE;
    }

    private static function estimateTokens(string $text): float
    {
        return mb_strlen($text) / 4;
    }
}
