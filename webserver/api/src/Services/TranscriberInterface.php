<?php

namespace App\Services;

/**
 * Fournisseur capable de transcrire un fichier audio en texte.
 */
interface TranscriberInterface
{
    public function transcribe(string $audioFilePath): string;
}
