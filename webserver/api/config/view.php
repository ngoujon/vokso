<?php

// Les vues sont des gabarits PHP simples (resources/views/podcast/*.php) :
// rien n'est compilé. Si une vue Blade devait l'être, elle le serait dans
// le dossier temporaire du système, storage/ n'étant pas versionné (il est
// créé et possédé par Apache sur le serveur, voir scripts/deploy.sh).
return [
    'paths' => [resource_path('views')],
    'compiled' => env('VIEW_COMPILED_PATH', sys_get_temp_dir()),
];
