<?php

namespace App\Controllers;

use App\Models\DatabaseModel;

class SearchController {
    private $db_model;

    public function __construct($db_config) {
        $this->db_model = new DatabaseModel($db_config);
    }

    public function search($query) {
        $results = $this->db_model->searchGenerations($query);

        // Vérifier si aucun résultat n'est trouvé
        if (empty($results)) {
            $results = ['message' => 'Aucun résultat'];
        }

        // Retourner les résultats au format JSON
        header('Content-Type: application/json');
        echo json_encode(['results' => $results]);
        exit;
    }
}
