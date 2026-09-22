<?php

namespace App\Services;

use GuzzleHttp\Promise\PromiseInterface;

/**
 * Fournisseur capable de convertir du texte en audio.
 * Retourne le contenu binaire de l'audio et son extension de fichier.
 */
interface SpeechSynthesizerInterface
{
    public function synthesize(string $text): string;

    /** Variante asynchrone : la promesse se résout avec le contenu binaire de l'audio. */
    public function synthesizeAsync(string $text): PromiseInterface;

    /** Extension du fichier produit, sans le point (ex: "mp3", "wav"). */
    public function audioExtension(): string;
}
