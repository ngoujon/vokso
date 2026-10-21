<?php

namespace App\Http\Controllers;

use App\Support\RateLimiter;
use App\Support\Stats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Écoutes et téléchargements MP3 signalés par la vitrine (site.js). Le PDF est
 * compté côté serveur, par PodcastPageController::pdf. Mesure anonyme : rien
 * ne relie un événement à un visiteur.
 */
class EventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $generationId = (string) $request->json('generation_id', '');
        $type = (string) $request->json('type', '');

        if (! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $generationId) || ! in_array($type, ['play', 'download_audio'], true)) {
            return $this->error('Événement invalide.', 400);
        }

        if ((new RateLimiter(300, 3600))->tooManyRequests((string) $request->ip(), 'podcast-event')) {
            return $this->error('Trop de requêtes.', 429);
        }

        $exists = DB::table('generations')->where('generation_id', $generationId)->where('statut', 'on')->exists();
        if ($exists) {
            Stats::recordEvent($generationId, $type);
        }

        return response()->json(['success' => true]);
    }
}
