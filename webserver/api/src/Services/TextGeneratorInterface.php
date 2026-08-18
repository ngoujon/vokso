<?php

namespace App\Services;

/**
 * Fournisseur capable de générer du texte à partir d'un prompt système
 * et d'un prompt utilisateur.
 */
interface TextGeneratorInterface
{
    public function generateText(string $systemPrompt, string $userPrompt): string;
}
