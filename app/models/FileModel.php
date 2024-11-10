<?php

namespace App\Models;

class FileModel {
    public function saveTextToFile($text, $prefix, $timestamp) {
        $output_dir = __DIR__ . '/../../output';
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0777, true);
        }
        $filename = "$output_dir/{$prefix}_{$timestamp}.txt";
        file_put_contents($filename, $text);
        return $filename;
    }

    public function saveImageToFile($image_url, $timestamp) {
        $image_response = file_get_contents($image_url);
        $output_dir = __DIR__ . '/../../output';
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0777, true);
        }

        $file_name = "image_{$timestamp}.png";
        file_put_contents("$output_dir/$file_name", $image_response);

        return $file_name;
    }

    public function saveAudioToFile($audio_data, $timestamp) {
        $output_dir = __DIR__ . '/../../output';
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0777, true);
        }

        $file_name = "audio_{$timestamp}.mp3";
        file_put_contents("$output_dir/$file_name", $audio_data);

        return $file_name;
    }
}
    