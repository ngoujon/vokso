<?php

namespace App\Utils;

/**
 * Règle commune pour toute création/modification de mot de passe (inscription,
 * création de compte admin, changement de mot de passe). Volontairement plus
 * stricte que la simple limite de 8 caractères utilisée à la connexion, pour
 * ne pas casser les comptes existants qui respectaient l'ancienne règle.
 */
class PasswordPolicy
{
    private const MIN_LENGTH = 12;

    /** Retourne un message d'erreur si le mot de passe est trop faible, sinon null. */
    public static function validate(string $password): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return 'Le mot de passe doit contenir au moins ' . self::MIN_LENGTH . ' caractères.';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return 'Le mot de passe doit contenir au moins une lettre et un chiffre.';
        }

        return null;
    }
}
