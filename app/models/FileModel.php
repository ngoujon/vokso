<?php

namespace App\Models;

class FileModel {

    // Helper function to create directories if they don't exist
    private function ensureDirectoryExists($dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                throw new \Exception("Failed to create directory: $dir");
            }
        }
    }

    // Helper function to save a file to a given directory
    private function saveFile($data, $dir, $filename) {
        $this->ensureDirectoryExists($dir);
        $file_path = "$dir/$filename";
        if (file_put_contents($file_path, $data) === false) {
            throw new \Exception("Failed to write file: $file_path");
        }
        return $filename;
    }

    public function saveTextToFile($text, $prefix, $timestamp) {
        $output_dir = __DIR__ . '/../../public/output/responses';
        $filename = "{$prefix}_{$timestamp}.txt";
        return $this->saveFile($text, $output_dir, $filename);
    }

    public function saveImageToFile($image_url, $timestamp) {
        $image_response = file_get_contents($image_url);
        $output_dir = __DIR__ . '/../../public/output/images';
        $filename = "image_{$timestamp}.png";
        return $this->saveFile($image_response, $output_dir, $filename);
    }

    public function saveAudioToFile($audio_data, $timestamp) {
        $output_dir = __DIR__ . '/../../public/output/audios';
        $filename = "audio_{$timestamp}.mp3";
        return $this->saveFile($audio_data, $output_dir, $filename);
    }

    public function getGenerationFiles($generation) {
        $files = [];
    
        // Vérifiez si l'URL de l'image est définie
        if (!empty($generation['image_url'])) {
            $files['image'] = "images/" . basename($generation['image_url']);
        }
    
        // Vérifiez si l'URL de l'audio est définie
        if (!empty($generation['audio_url'])) {
            $files['audio'] = "audios/" . basename($generation['audio_url']);
        }
    
        // Vérifiez si le fichier texte existe avant d'y accéder
        if (!empty($generation['text_file'])) {
            $files['text'] = "responses/" . basename($generation['text_file']);
        }
    
        return $files;
    }
}
