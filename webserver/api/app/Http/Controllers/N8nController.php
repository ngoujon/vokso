<?php

namespace App\Http\Controllers;

use App\Support\EpisodeText;
use App\Support\GenerationLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API du workflow n8n (protégée par EnsureN8nCaller) : création d'un épisode
 * à partir d'un sujet, puis suivi jusqu'à sa publication pour que le workflow
 * puisse envoyer le lien (Telegram...).
 *
 * Même chaîne que la création publique, sans les plafonds par IP (l'appelant
 * est de confiance) mais avec le plafond global quotidien, qui protège la
 * facture IA quoi qu'il arrive.
 */
class N8nController extends GenerationController
{
    public function store(Request $request): JsonResponse
    {
        $input = $request->json('sujet', $request->json('input'));
        if (! is_string($input) || trim($input) === '') {
            return $this->error('Champ « sujet » manquant', 422);
        }

        $subject = $this->sanitizeInput($input);
        if (mb_strlen($subject) > self::MAX_INPUT_LENGTH) {
            return $this->error('Le sujet ne doit pas dépasser '.self::MAX_INPUT_LENGTH.' caractères.', 422);
        }

        [$minutes, $level, $formatError] = $this->format($request->json('duree', $request->json('duration')), $request->json('niveau', $request->json('level')), 422);
        if ($formatError !== null) {
            return $formatError;
        }

        $limits = GenerationLimits::fromConfig();
        if ($limits->globalUsage() >= (int) config('vokso.generation_limits.global_daily')) {
            return $this->error('Plafond quotidien de générations atteint, réessayez demain.', 429, ['code' => 'limit_reached']);
        }

        $jobId = $this->createJob('text', $subject, null, null, $minutes, $level);
        $this->dispatch($jobId);

        return response()->json([
            'job_id' => $jobId,
            'status' => 'pending',
            'sujet' => $subject,
            'status_url' => rtrim((string) config('vokso.public_url'), '/').'/api/n8n/podcasts/'.$jobId,
        ], 202);
    }

    public function show(string $jobId): JsonResponse
    {
        $row = DB::table('generation_jobs as j')
            ->leftJoin('generations as g', 'g.generation_id', '=', 'j.generation_id')
            ->leftJoin('categorie as c', 'c.idcategorie', '=', 'g.idcategorie')
            ->where('j.job_id', $jobId)
            ->first(['j.status', 'j.step', 'j.progress', 'j.error_message', 'j.input', 'j.generation_id', 'g.slug', 'g.title', 'g.image_url', 'g.audio_url', 'c.label as category']);

        if (! $row) {
            return $this->error('Suivi introuvable', 404);
        }

        $base = rtrim((string) config('vokso.public_url'), '/');
        $episode = null;
        if ($row->status === 'done' && $row->generation_id) {
            $title = EpisodeText::cleanTitle((string) $row->title) ?: 'Épisode Vokso';
            $episode = [
                'id' => $row->generation_id,
                'title' => $title,
                'category' => $row->category,
                'url' => EpisodeText::episodeUrl($base, $row->slug, $row->generation_id, $title),
                'image_url' => $row->image_url ? $base.'/static/images/'.rawurlencode((string) $row->image_url) : null,
                'audio_url' => $row->audio_url ? $base.'/static/audios/'.rawurlencode((string) $row->audio_url) : null,
            ];
        }

        return response()->json([
            'job_id' => $jobId,
            'sujet' => $row->input,
            'status' => $row->status,
            'step' => $row->step,
            'progress' => (int) $row->progress,
            'done' => $row->status === 'done',
            'error' => $row->status === 'error' ? $row->error_message : null,
            'episode' => $episode,
        ]);
    }
}
