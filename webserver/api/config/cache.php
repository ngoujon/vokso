<?php

// Cache en mémoire du process uniquement : l'API n'écrit rien à l'exécution
// hors logs, uploads et fichiers générés (le dossier storage/ appartient à
// www-data en production). Les autres réglages sont ceux du framework.
return [
    'default' => env('CACHE_STORE', 'array'),
    'serializable_classes' => false,
];
