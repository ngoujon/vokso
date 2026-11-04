<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sonde de supervision publique (GET/HEAD /health, réécrit vers /api/health
 * par site/.htaccess). Ne lit aucun paramètre, n'écrit rien et ne divulgue
 * rien : seulement {"status":"ok"} ou {"status":"error"}.
 */
class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        try {
            DB::select('select 1');
            $ok = true;
        } catch (Throwable) {
            $ok = false;
        }

        return response()
            ->json(['status' => $ok ? 'ok' : 'error'], $ok ? 200 : 503)
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
