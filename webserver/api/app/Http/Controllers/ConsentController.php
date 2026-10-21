<?php

namespace App\Http\Controllers;

use App\Support\RateLimiter;
use App\Support\Stats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Registre des choix du bandeau cookies : preuve du consentement (article 7.1
 * du RGPD) et export pour un contrôle de la CNIL. L'IP n'est jamais stockée en
 * clair (empreinte HMAC salée par APP_KEY) ; les entrées de plus de 5 ans sont
 * purgées.
 */
class ConsentController extends Controller
{
    private const RETENTION_YEARS = 5;

    public function store(Request $request): JsonResponse
    {
        $consentId = strtolower((string) $request->json('consent_id', ''));
        $choice = (string) $request->json('choice', '');
        $version = (string) $request->json('version', '');
        $page = mb_substr((string) $request->json('page', ''), 0, 255);

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $consentId)
            || ! in_array($choice, ['accepted', 'refused'], true)
            || ! preg_match('/^[A-Za-z0-9._-]{1,20}$/', $version)) {
            return $this->error('Choix invalide.', 400);
        }

        if ((new RateLimiter(30, 3600))->tooManyRequests((string) $request->ip(), 'consent')) {
            return $this->error('Trop de requêtes.', 429);
        }

        if (! Stats::ready('cookie_consents')) {
            return response()->json(['success' => true, 'recorded' => false]);
        }

        try {
            DB::table('cookie_consents')->insert([
                'consent_id' => $consentId,
                'choice' => $choice,
                'banner_version' => $version,
                'page' => $page !== '' ? $page : null,
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
                'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]);
            if (random_int(1, 100) === 1) {
                DB::table('cookie_consents')->where('created_at', '<', now()->subYears(self::RETENTION_YEARS))->delete();
            }
        } catch (Throwable $e) {
            report($e);

            return response()->json(['success' => true, 'recorded' => false]);
        }

        return response()->json(['success' => true, 'recorded' => true]);
    }

    /** Synthèse et derniers choix, pour l'onglet « Cookies » de l'administration. */
    public function summary(Request $request): JsonResponse
    {
        $days = $this->days($request);
        if (! Stats::ready('cookie_consents')) {
            return response()->json(['ready' => false, 'days' => $days, 'accepted' => 0, 'refused' => 0, 'total' => 0, 'latest' => []]);
        }

        $counts = DB::table('cookie_consents')
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->select('choice', DB::raw('COUNT(*) AS total'))
            ->groupBy('choice')
            ->pluck('total', 'choice');

        $latest = DB::table('cookie_consents')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['created_at', 'consent_id', 'choice', 'banner_version', 'page']);

        return response()->json([
            'ready' => true,
            'days' => $days,
            'accepted' => (int) ($counts['accepted'] ?? 0),
            'refused' => (int) ($counts['refused'] ?? 0),
            'total' => DB::table('cookie_consents')->count(),
            'latest' => $latest,
        ]);
    }

    /** Export CSV (séparateur « ; », UTF-8 avec BOM pour Excel) sur une période. */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $from = $this->date($request->query('from')) ?? now()->subYears(self::RETENTION_YEARS)->format('Y-m-d');
        $to = $this->date($request->query('to')) ?? now()->format('Y-m-d');

        if (! Stats::ready('cookie_consents')) {
            return $this->error('Le registre des consentements n\'est pas encore créé (migration SQL/20261021000000.sql).', 409);
        }

        $query = DB::table('cookie_consents')
            ->where('created_at', '>=', $from.' 00:00:00')
            ->where('created_at', '<=', $to.' 23:59:59')
            ->orderBy('id');

        return response()->streamDownload(function () use ($query, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['# Registre des choix du bandeau cookies — vokso.fr'], ';');
            fputcsv($out, ['# Période : '.$from.' au '.$to.' ; export du '.now()->toIso8601String()], ';');
            fputcsv($out, ['# Finalité soumise à consentement : mesure d\'audience avec identifiant persistant (SEO Monitor, auto-hébergé, stockage local sm_vid). Durée de validité du choix : 6 mois. Conservation des preuves : '.self::RETENTION_YEARS.' ans.'], ';');
            fputcsv($out, ['horodatage_utc', 'identifiant_consentement', 'choix', 'finalite', 'version_bandeau', 'page', 'navigateur', 'empreinte_ip_hmac_sha256'], ';');
            foreach ($query->cursor() as $row) {
                fputcsv($out, [
                    $row->created_at,
                    $row->consent_id,
                    $row->choice === 'accepted' ? 'accepte' : 'refuse',
                    'mesure_audience',
                    $row->banner_version,
                    $row->page,
                    $row->user_agent,
                    $row->ip_hash,
                ], ';');
            }
            fclose($out);
        }, 'vokso-consentements-'.$from.'_'.$to.'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    private function days(Request $request): int
    {
        return min(365, max(1, (int) $request->query('days', 30)));
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
