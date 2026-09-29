<?php

use Monolog\Formatter\JsonFormatter;

/*
 * Journal applicatif JSON dans webserver/logs/api.log (même emplacement
 * qu'avant la migration Laravel, déjà inscriptible par Apache en
 * production). Sentry (config/sentry.php) sert à l'alerting.
 */
return [

    'default' => env('LOG_CHANNEL', 'api'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    'channels' => [

        'api' => [
            'driver' => 'single',
            'path' => env('LOG_PATH', base_path('../logs/api.log')),
            'level' => env('LOG_LEVEL', env('APP_DEBUG', false) ? 'debug' : 'info'),
            'formatter' => JsonFormatter::class,
            'replace_placeholders' => true,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\StreamHandler::class,
            'with' => ['stream' => 'php://stderr'],
            'level' => 'debug',
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\NullHandler::class,
        ],

        'emergency' => [
            'path' => env('LOG_PATH', base_path('../logs/api.log')),
        ],

    ],

];
