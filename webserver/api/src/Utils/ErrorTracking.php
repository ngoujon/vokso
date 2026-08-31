<?php

namespace App\Utils;

// Initialise Sentry si SENTRY_DSN_API est renseigné : sans DSN, le SDK reste
// inactif et capture()/captureException() ne font rien (no-op), donc aucune
// configuration supplémentaire n'est nécessaire en local.
class ErrorTracking
{
    public static function init(): void
    {
        $dsn = $_ENV['SENTRY_DSN_API'] ?? '';
        if ($dsn === '') {
            return;
        }

        \Sentry\init([
            'dsn' => $dsn,
            'environment' => $_ENV['APP_ENV'] ?? ($_ENV['APP_DEBUG'] ?? false ? 'development' : 'production'),
        ]);
    }
}
