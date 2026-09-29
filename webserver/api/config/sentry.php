<?php

// Suivi d'erreurs : inactif tant que SENTRY_DSN_API est vide.
return [
    'dsn' => env('SENTRY_DSN_API', env('SENTRY_LARAVEL_DSN')),
    'environment' => env('APP_ENV', 'production'),
    'send_default_pii' => false,
    'traces_sample_rate' => null,
    'breadcrumbs' => [
        'logs' => true,
        'sql_queries' => false,
        'sql_bindings' => false,
    ],
];
