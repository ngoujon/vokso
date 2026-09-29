<?php

namespace App\Services\Ai;

/**
 * Fournisseur capable de transcrire un fichier audio en texte.
 */
interface TranscriberInterface
{
    public function transcribe(string $audioFilePath): string;
}
