<?php

namespace App\Services;

use GuzzleHttp\Promise\PromiseInterface;

/**
 * Fournisseur capable de générer du texte à partir d'un prompt système
 * et d'un prompt utilisateur.
 */
interface TextGeneratorInterface
{
    public function generateText(string $systemPrompt, string $userPrompt): string;

    /** Variante asynchrone : la promesse se résout avec le texte généré. */
    public function generateTextAsync(string $systemPrompt, string $userPrompt): PromiseInterface;
}
