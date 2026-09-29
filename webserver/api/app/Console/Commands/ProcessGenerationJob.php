<?php

namespace App\Console\Commands;

use App\Models\GenerationJob;
use App\Services\Ai\AiProviderFactory;
use App\Services\PodcastGenerator;
use Illuminate\Console\Command;
use Throwable;

/** Traite un job de génération (lancé en arrière-plan par GenerationController). */
class ProcessGenerationJob extends Command
{
    protected $signature = 'vokso:process-job {jobId}';

    protected $description = 'Génère le texte, l\'image, l\'audio et la catégorie d\'un podcast en attente';

    public function handle(AiProviderFactory $ai): int
    {
        $jobId = (string) $this->argument('jobId');

        try {
            (new PodcastGenerator($ai, (string) config('vokso.output_dir')))->process($jobId);
        } catch (Throwable $e) {
            // Erreur imprévue (base indisponible...) : le job ne doit pas
            // rester indéfiniment "en cours" côté front.
            GenerationJob::whereKey($jobId)->update(['status' => 'error', 'error_message' => 'Erreur interne lors de la génération']);
            report($e);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
