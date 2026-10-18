<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Fichiers produits par la génération (narrations, images, audios), rangés
 * dans {output_dir}/{responses,images,audios} et servis sous /static. Les
 * noms sont horodatés : {préfixe}_{AAAAMMJJ_HHMMSS}.{extension}.
 */
class OutputStorage
{
    public function __construct(private string $outputDir)
    {
    }

    public static function timestamp(): string
    {
        return date('Ymd_His');
    }

    /** Écrit un fichier et retourne son nom. */
    public function store(string $subDir, string $fileName, string $content): string
    {
        $directory = $this->outputDir.'/'.$subDir;
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new Exception('Impossible de créer le dossier de sortie : '.$subDir);
        }
        if (file_put_contents($directory.'/'.$fileName, $content) === false) {
            throw new Exception('Impossible d\'écrire le fichier : '.$fileName);
        }

        return $fileName;
    }

    /**
     * Enregistre une image générée et la convertit en WebP (bien plus légère) ;
     * l'original est gardé si la conversion échoue. Retourne le nom final.
     */
    public function storeImage(string $prefix, string $content): string
    {
        $fileName = $this->store('images', $prefix.'_'.self::timestamp().'.png', $content);
        $webp = $this->convertToWebP($fileName);
        if ($webp === null) {
            return $fileName;
        }
        @unlink($this->outputDir.'/images/'.$fileName);

        return $webp;
    }

    /** Copie d'une image existante sous un nouveau nom horodaté, ou null si elle est introuvable. */
    public function copyImage(?string $sourceName, string $prefix): ?string
    {
        $source = $this->outputDir.'/images/'.$sourceName;
        if (empty($sourceName) || ! is_file($source)) {
            return null;
        }
        $fileName = $prefix.'_'.self::timestamp().'.'.pathinfo($sourceName, PATHINFO_EXTENSION);

        return copy($source, $this->outputDir.'/images/'.$fileName) ? $fileName : null;
    }

    /**
     * Le format réel dépend du fournisseur (Mistral renvoie du JPEG malgré
     * l'extension .png) : il est détecté d'après le contenu, pas le nom.
     * Retourne le nom du fichier WebP, ou null s'il n'a pas pu être produit.
     */
    private function convertToWebP(string $fileName): ?string
    {
        $sourcePath = $this->outputDir.'/images/'.$fileName;
        $webpName = preg_replace('/\.png$/', '.webp', $fileName);
        $webpPath = $this->outputDir.'/images/'.$webpName;
        try {
            if (extension_loaded('imagick')) {
                $image = new \Imagick($sourcePath);
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality(80);
                $image->writeImage($webpPath);
                $image->clear();
            } elseif (function_exists('imagewebp')) {
                $image = @imagecreatefromstring((string) file_get_contents($sourcePath));
                if ($image !== false) {
                    imagewebp($image, $webpPath, 80);
                    imagedestroy($image);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Erreur de conversion WebP', ['exception' => get_class($e), 'reason' => $e->getMessage()]);
        }

        return file_exists($webpPath) ? $webpName : null;
    }
}
