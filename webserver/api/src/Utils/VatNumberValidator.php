<?php

namespace App\Utils;

/**
 * Vérifie le format d'un numéro de TVA intracommunautaire. Pour la France,
 * la clé à deux chiffres est recalculée à partir du SIREN (somme de
 * contrôle réelle) ; pour les autres pays de l'UE, seul le format générique
 * (2 lettres ISO + identifiant local) est vérifié, l'algorithme de clé
 * variant selon le pays.
 */
class VatNumberValidator
{
    /** Codes pays utilisés pour la TVA intracommunautaire (EL pour la Grèce, XI pour l'Irlande du Nord post-Brexit). */
    private const EU_VAT_COUNTRY_CODES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR', 'HR', 'HU', 'IE', 'IT',
        'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI',
    ];

    public static function isValid(string $vatNumber): bool
    {
        $vatNumber = strtoupper(preg_replace('/\s+/', '', $vatNumber));

        if (str_starts_with($vatNumber, 'FR')) {
            return self::isValidFrench($vatNumber);
        }

        if (!preg_match('/^([A-Z]{2})([0-9A-Z]{2,12})$/', $vatNumber, $matches)) {
            return false;
        }

        return in_array($matches[1], self::EU_VAT_COUNTRY_CODES, true);
    }

    private static function isValidFrench(string $vatNumber): bool
    {
        if (!preg_match('/^FR([0-9A-Z]{2})(\d{9})$/', $vatNumber, $matches)) {
            return false;
        }

        [, $key, $siren] = $matches;

        if (!ctype_digit($key)) {
            // Clé alphanumérique (régime particulier, rare) : format valide, pas de somme de contrôle possible.
            return true;
        }

        $expectedKey = (12 + 3 * ((int) $siren % 97)) % 97;

        return (int) $key === $expectedKey;
    }
}
