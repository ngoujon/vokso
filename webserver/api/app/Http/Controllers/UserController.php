<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /** Les podcasts générés par l'utilisateur connecté, avec le détail des coûts. */
    public function podcasts(Request $request): JsonResponse
    {
        $podcasts = DB::table('generations as g')
            ->leftJoin('categorie as c', 'c.idcategorie', '=', 'g.idcategorie')
            ->where('g.user_id', $request->user()->id)
            ->where('g.statut', 'on')
            ->orderByDesc('g.created_at')
            ->get([
                'g.generation_id', 'g.title', 'g.text_content as description',
                'g.image_url', 'g.audio_url', 'g.created_at',
                'g.cost_text', 'g.cost_image', 'g.cost_audio', 'g.cost_total', 'c.label as category',
            ]);

        return response()->json(['data' => $podcasts]);
    }

}
