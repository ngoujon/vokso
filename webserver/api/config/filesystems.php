<?php

// Aucun disque n'est servi par Laravel : les fichiers générés sont servis
// directement par Apache sous /static (voir config/vokso.php, output_dir).
return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
        ],
    ],
    'links' => [],
];
