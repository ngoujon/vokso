<?php

namespace App\Support;

/**
 * Préparation de la synthèse vocale par morceaux : un épisode de 15 minutes
 * dépasse 13 000 caractères, la voix est donc synthétisée en parallèle par
 * morceaux, puis les MP3 sont mis bout à bout.
 */
final class SpeechAudio
{
    /** Taille maximale d'un morceau : un épisode de 5 minutes (≈ 4 500 caractères) reste en un seul appel. */
    public const CHUNK_LENGTH = 5000;

    /**
     * Morceaux d'au plus CHUNK_LENGTH caractères, coupés entre paragraphes de
     * préférence, sinon entre phrases.
     *
     * @return list<string>
     */
    public static function chunks(string $text): array
    {
        $text = trim($text);
        if (mb_strlen($text) <= self::CHUNK_LENGTH) {
            return [$text];
        }

        $pieces = [];
        foreach (preg_split('/\R+/u', $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            // Paragraphe trop long : coupé entre phrases.
            $pieces = array_merge($pieces, mb_strlen($paragraph) <= self::CHUNK_LENGTH
                ? [$paragraph]
                : (preg_split('/(?<=[.!?…])\s+/u', $paragraph) ?: []));
        }

        // Regroupe les pièces tant que le morceau courant reste sous la limite.
        $chunks = [];
        $current = '';
        foreach ($pieces as $piece) {
            $candidate = $current === '' ? $piece : $current."\n\n".$piece;
            if ($current !== '' && mb_strlen($candidate) > self::CHUNK_LENGTH) {
                $chunks[] = $current;
                $current = $piece;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Met bout à bout des MP3 en débit constant (sortie Voxtral : en-tête ID3v2
     * puis trames brutes, sans en-tête Xing) : les étiquettes ID3 des morceaux
     * suivants sont retirées pour obtenir un flux continu dont la durée se
     * déduit correctement de la taille.
     *
     * @param  list<string>  $parts
     */
    public static function joinMp3(array $parts): string
    {
        if (count($parts) === 1) {
            return $parts[0];
        }

        $joined = '';
        foreach ($parts as $i => $part) {
            // Étiquette ID3v1 (128 octets en fin de fichier).
            if (strlen($part) > 128 && substr($part, -128, 3) === 'TAG') {
                $part = substr($part, 0, -128);
            }
            // Étiquette ID3v2 en tête : taille codée sur 4 octets « synchsafe ».
            if ($i > 0 && strlen($part) >= 10 && substr($part, 0, 3) === 'ID3') {
                $size = ((ord($part[6]) & 0x7F) << 21) | ((ord($part[7]) & 0x7F) << 14) | ((ord($part[8]) & 0x7F) << 7) | (ord($part[9]) & 0x7F);
                $footer = (ord($part[5]) & 0x10) ? 10 : 0;
                $part = substr($part, 10 + $size + $footer);
            }
            $joined .= $part;
        }

        return $joined;
    }
}
