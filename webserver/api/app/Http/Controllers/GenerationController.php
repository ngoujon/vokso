<?php

namespace App\Http\Controllers;

use App\Models\GenerationJob;
use App\Support\EpisodeText;
use App\Support\GenerationLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Met les générations en file d'attente et répond immédiatement : la chaîne
 * texte → image → audio → catégorie (plusieurs minutes) tourne dans un
 * processus détaché (commande artisan vokso:process-job).
 */
class GenerationController extends Controller
{
    protected const MAX_INPUT_LENGTH = 300;
    private const MAX_AUDIO_SIZE = 26214400; // 25 Mo
    private const ALLOWED_AUDIO_EXTENSIONS = ['mp3', 'wav', 'm4a', 'ogg', 'webm', 'mp4', 'mpeg', 'mpga'];

    public function generateText(Request $request): JsonResponse
    {
        $input = $request->json('input');
        if (! is_string($input) || trim($input) === '') {
            return $this->error('Valeur manquante', 400);
        }

        // Une seule valeur nettoyée sert à tous les appels IA et à la base.
        $userInput = $this->sanitizeInput($input);
        if (mb_strlen($userInput) > self::MAX_INPUT_LENGTH) {
            return $this->error('Le sujet ne doit pas dépasser '.self::MAX_INPUT_LENGTH.' caractères.', 400);
        }

        $refusal = $this->limitRefusal($request);
        if ($refusal !== null) {
            return $refusal;
        }

        $jobId = $this->createJob('text', $userInput, null, $request->user()?->id);
        $this->dispatch($jobId);

        return response()->json([
            'message' => 'Génération mise en file d\'attente',
            'job_id' => $jobId,
            'status' => 'pending',
        ], 202);
    }

    /** Variante qui part d'un message vocal, transcrit par le worker avant la même chaîne. */
    public function generateFromAudio(Request $request): JsonResponse
    {
        $file = $request->file('audio');
        if ($file === null) {
            return $this->error('Aucun fichier audio reçu.', 400);
        }
        if (! $file->isValid()) {
            return $this->error('Échec de l\'envoi du fichier audio.', 400);
        }
        if ($file->getSize() > self::MAX_AUDIO_SIZE) {
            return $this->error('Le fichier audio ne doit pas dépasser 25 Mo.', 400);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, self::ALLOWED_AUDIO_EXTENSIONS, true)) {
            return $this->error('Format audio non supporté.', 400);
        }

        // Vérifié avant de stocker le fichier : un refus ne doit pas laisser
        // d'upload orphelin.
        $refusal = $this->limitRefusal($request);
        if ($refusal !== null) {
            return $refusal;
        }

        $uploadDir = (string) config('vokso.upload_dir');
        if (! is_dir($uploadDir) && ! @mkdir($uploadDir, 0775, true) && ! is_dir($uploadDir)) {
            return $this->error('Impossible de préparer le stockage du fichier audio.', 500);
        }

        $storedName = 'upload_'.bin2hex(random_bytes(16)).'.'.$extension;
        try {
            $file->move($uploadDir, $storedName);
        } catch (\Throwable $e) {
            report($e);

            return $this->error('Impossible d\'enregistrer le fichier audio.', 500);
        }

        $jobId = $this->createJob('audio', null, $uploadDir.'/'.$storedName, $request->user()?->id);
        $this->dispatch($jobId);

        return response()->json([
            'message' => 'Transcription et génération mises en file d\'attente',
            'job_id' => $jobId,
            'status' => 'pending',
        ], 202);
    }

    /** Suivi de progression d'un job, interrogé par le front en polling. */
    public function status(Request $request): JsonResponse
    {
        $jobId = $request->query('id');
        if (! is_string($jobId) || $jobId === '') {
            return $this->error('Identifiant de suivi manquant', 400);
        }

        $row = DB::table('generation_jobs as j')
            ->leftJoin('generations as g', 'g.generation_id', '=', 'j.generation_id')
            ->where('j.job_id', $jobId)
            ->first(['j.status', 'j.step', 'j.progress', 'j.error_message', 'j.generation_id', 'g.slug', 'g.title', 'g.image_url', 'g.audio_url']);

        if (! $row) {
            return $this->error('Suivi introuvable', 404);
        }

        return response()->json([
            'status' => $row->status,
            'step' => $row->step,
            'progress' => (int) $row->progress,
            'error' => $row->error_message,
            'generation_id' => $row->generation_id,
            'slug' => $row->slug,
            'url' => $row->generation_id ? EpisodeText::episodePath($row->slug, $row->generation_id, (string) $row->title) : null,
            'title' => $row->title,
            'image' => $row->image_url,
            'audio' => $row->audio_url,
        ]);
    }

    /** Génération sans compte, plafonnée par IP et globalement (GenerationLimits). */
    private function limitRefusal(Request $request): ?JsonResponse
    {
        $message = GenerationLimits::fromConfig()->refusal($request->user(), (string) $request->ip());

        return $message === null ? null : $this->error($message, 429, ['code' => 'limit_reached']);
    }

    protected function createJob(string $sourceType, ?string $input, ?string $audioPath, ?int $userId): string
    {
        $jobId = 'job_'.bin2hex(random_bytes(16));

        GenerationJob::create([
            'job_id' => $jobId,
            'user_id' => $userId,
            'source_type' => $sourceType,
            'status' => 'pending',
            'step' => 'queued',
            'progress' => 0,
            'input' => $input,
            'audio_path' => $audioPath,
        ]);

        return $jobId;
    }

    /**
     * Lance le traitement dans un processus PHP CLI détaché (pas de worker de
     * file d'attente permanent à superviser sur le serveur).
     *
     * PHP_BINARY est vide sous mod_php (PHP chargé comme module d'Apache) :
     * PHP_BINDIR reste valide dans les deux cas.
     */
    protected function dispatch(string $jobId): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $php = escapeshellarg(PHP_BINARY !== '' && ! Str::contains(PHP_BINARY, ['apache', 'httpd']) ? PHP_BINARY : PHP_BINDIR.'/php');
        $artisan = escapeshellarg(base_path('artisan'));
        $arg = escapeshellarg($jobId);
        exec("$php $artisan vokso:process-job $arg > /dev/null 2>&1 &");
    }

    protected function sanitizeInput(string $input): string
    {
        return trim(htmlspecialchars(strip_tags($input)));
    }
}
