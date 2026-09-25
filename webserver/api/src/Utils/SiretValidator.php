<?php

namespace App\Utils;

/**
 * Vérifie le format (14 chiffres) et la clé de contrôle (algorithme de Luhn,
 * utilisé par l'INSEE pour les SIRET) d'un identifiant saisi dans le profil
 * de facturation "pro".
 */
class SiretValidator
{
    public static function isValid(string $siret): bool
    {
        $siret = preg_replace('/\s+/', '', $siret);
        if (!preg_match('/^\d{14}$/', $siret)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 14; $i++) {
            $digit = (int) $siret[$i];
            if ($i % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    /** Les 9 premiers chiffres du SIRET identifient l'entreprise (SIREN), les 5 derniers l'établissement (NIC). */
    public static function siren(string $siret): string
    {
        return substr(preg_replace('/\s+/', '', $siret), 0, 9);
    }
}
