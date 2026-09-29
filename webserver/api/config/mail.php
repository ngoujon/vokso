<?php

/*
 * Les variables historiques SMTP_* / MAIL_FROM restent acceptées (.env de
 * production). SMTP_ENCRYPTION=ssl correspond au schéma "smtps" (port 465).
 */
$encryption = strtolower((string) env('SMTP_ENCRYPTION', ''));

return [

    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME', $encryption === 'ssl' ? 'smtps' : null),
            'host' => env('MAIL_HOST', env('SMTP_HOST', 'mailhog')),
            'port' => (int) env('MAIL_PORT', env('SMTP_PORT', 1025)),
            'username' => env('MAIL_USERNAME', env('SMTP_USERNAME')) ?: null,
            'password' => env('MAIL_PASSWORD', env('SMTP_PASSWORD')) ?: null,
            'timeout' => 10,
            'local_domain' => env('MAIL_EHLO_DOMAIN', 'vokso.fr'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', env('MAIL_FROM', 'contact@vokso.fr')),
        'name' => env('MAIL_FROM_NAME', 'Vokso'),
    ],

];
