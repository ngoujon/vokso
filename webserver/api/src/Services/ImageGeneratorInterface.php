<?php

namespace App\Services;

use GuzzleHttp\Promise\PromiseInterface;

/**
 * Fournisseur capable de générer une image à partir d'un prompt.
 * Retourne le contenu binaire de l'image (PNG).
 */
interface ImageGeneratorInterface
{
    public function generateImage(string $prompt): string;

    /** Variante asynchrone : la promesse se résout avec le contenu binaire de l'image. */
    public function generateImageAsync(string $prompt): PromiseInterface;
}
