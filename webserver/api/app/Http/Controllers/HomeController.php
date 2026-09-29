<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return response('api url ok');
    }

    /** Test de connectivité utilisé par le monitoring : POST {"value": "ping"} -> {"message": "pong"}. */
    public function ping(Request $request): JsonResponse
    {
        $value = $request->json('value');

        if ($value === null) {
            return $this->error('Valeur manquante', 400);
        }
        if ($value !== 'ping') {
            return $this->error('Mauvais choix', 400);
        }

        return response()->json(['message' => 'pong']);
    }
}
