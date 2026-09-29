<?php

use App\Models\User;

/*
 * Authentification par jeton opaque (table auth_tokens), sans cookie ni
 * session : le front React envoie "Authorization: Bearer <token>". Le garde
 * "token" est enregistré dans AppServiceProvider (Auth::viaRequest).
 */
return [

    'defaults' => [
        'guard' => 'api',
        'passwords' => 'users',
    ],

    'guards' => [
        'api' => [
            'driver' => 'vokso-token',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    'passwords' => [],

    // Durée de vie d'un jeton de connexion (secondes) : 30 jours.
    'token_ttl' => 60 * 60 * 24 * 30,

];
