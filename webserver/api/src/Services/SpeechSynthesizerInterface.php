<?php

namespace App\Services;

/**
 * Fournisseur capable de convertir du texte en audio.
 * Retourne le contenu binaire de l'audio et son extension de fichier.
 */
interface SpeechSynthesizerInterface
{
    public function synthesize(string $text): string;

    /** Extension du fichier produit, sans le point (ex: "mp3", "wav"). */
    public function audioExtension(): string;
}
