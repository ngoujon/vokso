<?php

namespace App\Services;

/**
 * Fournisseur capable de générer une image à partir d'un prompt.
 * Retourne le contenu binaire de l'image (PNG).
 */
interface ImageGeneratorInterface
{
    public function generateImage(string $prompt): string;
}
