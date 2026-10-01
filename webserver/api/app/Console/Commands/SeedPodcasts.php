<?php

namespace App\Console\Commands;

use App\Models\GenerationJob;
use App\Services\Ai\AiProviderFactory;
use App\Services\PodcastGenerator;
use Illuminate\Console\Command;
use Throwable;

/**
 * Génère une série d'épisodes variés pour alimenter le catalogue public
 * (sans passer par les plafonds anti-abus de l'API). Les sujets déjà générés
 * avec succès sont ignorés : la commande peut être relancée sans doublon
 * après une interruption.
 *
 * Long (plusieurs minutes par épisode) : à lancer en arrière-plan sur le
 * serveur, sous l'utilisateur d'Apache pour que les fichiers produits lui
 * appartiennent.
 */
class SeedPodcasts extends Command
{
    protected $signature = 'vokso:seed-podcasts {sujets?* : Sujets à générer (par défaut, une sélection variée)}';

    protected $description = 'Génère une série d\'épisodes variés pour alimenter le catalogue';

    private const DEFAULT_TOPICS = [
        "L'histoire secrète du croissant, de Vienne à Paris",
        'Pourquoi les chats ronronnent-ils ?',
        'Les trous noirs expliqués simplement',
        'La bataille de Marignan en 1515',
        'Comment fonctionne une intelligence artificielle générative',
        'Les bienfaits de la sieste sur le cerveau',
        "L'incroyable migration des papillons monarques",
        'Les secrets de fabrication du champagne',
        "Marie Curie, une vie au service de la science",
        'Le jazz est-il né à La Nouvelle-Orléans ?',
        'Pourquoi le ciel est bleu',
        'Les Jeux olympiques de l\'Antiquité',
        'Le mystère des statues de l\'île de Pâques',
        'Comment bien dormir : la science du sommeil',
        'Les origines du football',
        'La révolution du vélo électrique en ville',
        "L'art de la négociation au quotidien",
        'Les abeilles, sentinelles de la biodiversité',
        'Le Petit Prince de Saint-Exupéry, un conte pour adultes',
        'La blockchain sans jargon',
    ];

    public function handle(AiProviderFactory $ai): int
    {
        $topics = $this->argument('sujets') ?: self::DEFAULT_TOPICS;
        $generator = new PodcastGenerator($ai, (string) config('vokso.output_dir'));
        $failures = 0;

        foreach ($topics as $index => $topic) {
            $topic = trim((string) $topic);
            $label = sprintf('[%d/%d] %s', $index + 1, count($topics), $topic);

            if (GenerationJob::where('input', $topic)->where('status', 'done')->exists()) {
                $this->line("$label : déjà généré, ignoré.");
                continue;
            }

            $jobId = 'job_'.bin2hex(random_bytes(16));
            GenerationJob::create([
                'job_id' => $jobId,
                'source_type' => 'text',
                'status' => 'pending',
                'step' => 'queued',
                'progress' => 0,
                'input' => mb_substr($topic, 0, 300),
            ]);

            $this->line("$label : génération...");
            try {
                $generator->process($jobId);
            } catch (Throwable $e) {
                GenerationJob::whereKey($jobId)->update(['status' => 'error', 'error_message' => 'Erreur interne lors de la génération']);
                report($e);
            }

            $job = GenerationJob::find($jobId);
            if ($job->status === 'done') {
                $this->info("$label : terminé ({$job->generation_id}).");
            } else {
                $failures++;
                $this->error("$label : échec ({$job->error_message}).");
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
