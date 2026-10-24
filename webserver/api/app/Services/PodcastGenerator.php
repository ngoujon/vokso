<?php

namespace App\Services;

use App\Exceptions\BlockedContentException;
use App\Models\Generation;
use App\Models\GenerationJob;
use App\Models\Prompt;
use App\Services\Ai\AiProviderFactory;
use App\Support\AiQuota;
use App\Support\CostEstimator;
use App\Support\EpisodeFormat;
use App\Support\EpisodeSlug;
use App\Support\EpisodeText;
use App\Support\PromptGuard;
use App\Support\SpeechAudio;
use Exception;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use Illuminate\Support\Facades\Log;

/**
 * Exécute la chaîne complète de génération (texte, image, audio, catégorie)
 * pour un job donné et journalise la progression en base au fil des étapes.
 * Tourne hors requête HTTP, dans le processus détaché lancé par
 * GenerationController (commande artisan vokso:process-job).
 *
 * Étapes : transcription éventuelle → contrôle du sujet → texte, titre et
 * catégorie en parallèle → modération → image et voix en parallèle →
 * enregistrement (publié, ou gardé hors ligne si la modération était
 * indisponible).
 */
class PodcastGenerator
{
    // Messages affichés au visiteur quand les protections arrêtent la génération.
    private const MESSAGE_REFUSED = 'Ce sujet ne peut pas être traité par Vokso.';
    private const MESSAGE_BLOCKED = 'La génération a été bloquée par nos protections. Reformulez le sujet et réessayez.';
    private const MESSAGE_HELD = 'Votre épisode est prêt, mais il doit être vérifié avant sa publication.';

    // Crédit Mistral épuisé ou limite atteinte : pas une erreur du visiteur,
    // le Studio l'affiche comme une simple information (voir site/index.html).
    public const MESSAGE_UNAVAILABLE = 'Le Studio fait une petite pause : la création d\'épisodes sera de nouveau disponible très prochainement. En attendant, toute la discothèque reste à votre écoute !';

    // Un prompt trop long (texte intégral du podcast) noie le sujet visuel
    // dans le récit : la vignette n'a besoin que d'un aperçu du texte, pas du
    // script complet.
    private const IMAGE_PROMPT_EXCERPT_LENGTH = 400;

    private OutputStorage $storage;

    /** Rubriques et habillage des catégories (aussi utilisé par vokso:seed-podcasts). */
    public readonly CategoryCatalog $categories;

    public function __construct(private AiProviderFactory $ai, string $outputDir)
    {
        $this->storage = new OutputStorage($outputDir);
        $this->categories = new CategoryCatalog($ai, $this->storage);
    }

    public function process(string $jobId): void
    {
        $job = GenerationJob::find($jobId);
        if (!$job) {
            return;
        }

        $userInput = (string) $job->input;
        $minutes = EpisodeFormat::minutes($job->duration_minutes ?? null) ?? EpisodeFormat::DEFAULT_MINUTES;
        $level = EpisodeFormat::level($job->level ?? null) ?? EpisodeFormat::DEFAULT_LEVEL;

        if ($job->source_type === 'audio') {
            $this->updateJob($jobId, 'processing', 'transcription', 5);
            try {
                $userInput = $this->transcribeAudio($job->audio_path);
            } catch (Exception $e) {
                $this->failJob($jobId, 'Erreur lors de la transcription audio', $e);
                return;
            } finally {
                $this->deleteUploadedAudio($job->audio_path);
            }

            GenerationJob::whereKey($jobId)->update(['input' => $userInput, 'audio_path' => null]);
        }

        // Contrôle du sujet avant tout appel payant. Si la modération est
        // indisponible, la génération continue mais l'épisode n'est pas
        // publié sans vérification.
        $this->updateJob($jobId, 'processing', 'check', 8);
        $needsReview = false;
        $userInput = PromptGuard::normalize($userInput);
        try {
            if (PromptGuard::looksLikeInjection($userInput)) {
                throw new BlockedContentException(PromptGuard::INJECTION_MESSAGE, 'sujet : consigne adressée à l\'IA');
            }
            $needsReview = ! $this->passesModeration($userInput, self::MESSAGE_REFUSED, 'sujet');
        } catch (BlockedContentException $e) {
            $this->blockJob($jobId, $e);
            return;
        }

        // Le texte, la catégorie et le titre ne dépendent que du sujet
        // d'entrée (pas l'un de l'autre) : les lancer en parallèle via des
        // promesses Guzzle économise des allers-retours réseau par rapport à
        // des appels séquentiels.
        $this->updateJob($jobId, 'processing', 'text', 12);
        try {
            [$generatedText, $categoryKeyword, $title] = $this->generateTextCategoryAndTitle($jobId, $userInput, $minutes, $level);
            // Avant l'image et la voix : un épisode refusé ne coûte que le texte.
            $this->updateJob($jobId, 'processing', 'review', 42);
            if (! $this->passesModeration($title."\n\n".$generatedText, self::MESSAGE_BLOCKED, 'épisode')) {
                $needsReview = true;
            }
            $this->storage->store('responses', 'response_'.OutputStorage::timestamp().'.txt', $generatedText);
            $costText = CostEstimator::textCost(
                $this->ai->textProvider(),
                $this->ai->textModel(),
                $userInput,
                $generatedText
            );
        } catch (BlockedContentException $e) {
            $this->blockJob($jobId, $e);
            return;
        } catch (Exception $e) {
            $this->failJob($jobId, 'Erreur lors de la génération du texte', $e);
            return;
        }

        // Idem pour l'image et l'audio : toutes deux ne dépendent que du texte
        // généré, jamais l'une de l'autre, donc autant les lancer de front.
        $this->updateJob($jobId, 'processing', 'media', 46);
        try {
            [$imageContent, $audioContent, $audioExtension, $imageError] = $this->generateImageAndAudio($jobId, $generatedText);

            if ($imageContent !== null) {
                $imageFileName = $this->storage->storeImage('image', $imageContent);
                $costImage = CostEstimator::imageCost();
            } else {
                // Texte et audio sont déjà payés : plutôt que de tout perdre
                // pour une vignette, l'épisode reprend la couverture de sa
                // catégorie (copiée, pour garder un fichier par épisode).
                $imageFileName = $this->categories->coverCopy($categoryKeyword);
                if ($imageFileName === null) {
                    throw $imageError;
                }
                $this->log('warning', 'Image de l\'épisode remplacée par la couverture de sa catégorie', [
                    'job_id' => $jobId,
                    'category' => $categoryKeyword,
                    'reason' => $imageError->getMessage(),
                ]);
                $costImage = 0.0;
            }

            $audioFileName = $this->storage->store('audios', 'audio_'.OutputStorage::timestamp().'.'.$audioExtension, $audioContent);
            $costAudio = CostEstimator::speechCost($generatedText);
        } catch (Exception $e) {
            // Message affiché au visiteur : la réponse brute du fournisseur
            // (détail technique) reste dans le journal.
            $this->failJob($jobId, 'La pochette ou la voix n\'a pas pu être créée (image ou audio). Réessayez dans un instant.', $e);
            return;
        }

        $this->updateJob($jobId, 'processing', 'finalizing', 94);
        $generationId = 'gen_'.uniqid();
        Generation::create([
            'generation_id' => $generationId,
            'slug' => EpisodeSlug::forTitle($title),
            'title' => $title,
            'text_content' => $generatedText,
            'image_url' => $imageFileName,
            'audio_url' => $audioFileName,
            'idcategorie' => $this->categories->idFor($categoryKeyword),
            'user_id' => $job->user_id,
            'cost_text' => $costText,
            'cost_image' => $costImage,
            'cost_audio' => $costAudio,
            'cost_total' => $costText + $costImage + $costAudio,
            'duration_minutes' => $minutes,
            'level' => $level,
            'statut' => $needsReview ? 'off' : 'on',
        ]);

        GenerationJob::whereKey($jobId)->update([
            'status' => $needsReview ? 'error' : 'done',
            'step' => 'done',
            'progress' => 100,
            'generation_id' => $generationId,
            'error_message' => $needsReview ? self::MESSAGE_HELD : null,
        ]);
        if ($needsReview) {
            $this->log('warning', 'Épisode gardé hors ligne : modération indisponible', [
                'job_id' => $jobId,
                'generation_id' => $generationId,
            ]);
        }
    }

    /**
     * Modération Mistral : lève BlockedContentException si le texte est
     * signalé, renvoie false si la modération est indisponible (épisode à
     * vérifier avant publication), true s'il est sain ou si elle est désactivée.
     */
    private function passesModeration(string $text, string $userMessage, string $what): bool
    {
        $moderator = $this->ai->moderator();
        if ($moderator === null) {
            return true;
        }

        try {
            $flagged = $moderator->flaggedCategories($text);
        } catch (Exception $e) {
            $this->log('error', 'Modération indisponible', [
                'exception' => get_class($e),
                'reason' => $e->getMessage(),
            ]);

            return false;
        }

        if ($flagged !== []) {
            throw new BlockedContentException($userMessage, $what.' signalé par la modération : '.implode(', ', $flagged));
        }

        return true;
    }

    private function blockJob(string $jobId, BlockedContentException $e): void
    {
        $this->log('warning', 'Génération bloquée par les protections', [
            'job_id' => $jobId,
            'reason' => $e->reason,
        ]);
        GenerationJob::whereKey($jobId)->update(['status' => 'error', 'error_message' => $e->getMessage()]);
    }

    /**
     * Transcrit le fichier audio déposé et le ramène au même format qu'un
     * sujet saisi au clavier : un texte assaini et borné à PromptGuard::MAX_SUBJECT_LENGTH,
     * pour pouvoir alimenter la même chaîne de prompts.
     */
    private function transcribeAudio(?string $audioPath): string
    {
        if ($audioPath === null || $audioPath === '') {
            throw new Exception('Aucun fichier audio associé à ce job.');
        }

        $transcript = $this->ai->transcriber()->transcribe($audioPath);
        $transcript = trim(htmlspecialchars(strip_tags($transcript)));
        if ($transcript === '') {
            throw new Exception('La transcription audio est vide.');
        }

        return mb_substr($transcript, 0, PromptGuard::MAX_SUBJECT_LENGTH);
    }

    private function deleteUploadedAudio(?string $audioPath): void
    {
        if ($audioPath !== null && is_file($audioPath)) {
            @unlink($audioPath);
        }
    }

    private function updateJob(string $jobId, string $status, string $step, int $progress): void
    {
        GenerationJob::whereKey($jobId)->update(['status' => $status, 'step' => $step, 'progress' => $progress]);
    }

    private function failJob(string $jobId, string $message, Exception $e): void
    {
        if (AiQuota::isExhausted($e)) {
            $this->log('warning', 'Génération interrompue : crédit ou limite Mistral atteint', [
                'job_id' => $jobId,
                'step' => $message,
                'reason' => $e->getMessage(),
            ]);
            GenerationJob::whereKey($jobId)->update(['status' => 'error', 'error_message' => self::MESSAGE_UNAVAILABLE]);

            return;
        }

        $this->log('error', $message, [
            'job_id' => $jobId,
            'exception' => get_class($e),
            'reason' => $e->getMessage(),
        ]);
        GenerationJob::whereKey($jobId)->update(['status' => 'error', 'error_message' => $message]);
    }

    /**
     * La consigne de narration (durée, niveau, structure libre) est construite
     * par EpisodeFormat, plus lue en base : seuls titre, catégorie et
     * protection contre les injections restent des prompts éditables.
     *
     * Lance en parallèle la génération du texte principal, celle de la
     * catégorie et celle du titre (trois appels indépendants au même modèle
     * de texte, tous fonction du seul sujet d'entrée) et attend les trois
     * résultats.
     *
     * @return array{0: string, 1: string, 2: string} [texte généré, mot-clé de catégorie, titre]
     */
    private function generateTextCategoryAndTitle(string $jobId, string $userInput, int $minutes, int $level): array
    {
        $injectionPrompt = $this->requirePrompt('injection');
        // Jeton témoin propre à ce job : s'il ressort dans un texte, les
        // consignes ont fuité.
        $canary = PromptGuard::canary();
        $systemPrompt = PromptGuard::systemPrompt($injectionPrompt, $canary);
        $subject = PromptGuard::wrapSubject($userInput);

        $keywordPrompt = $this->requirePrompt('keyword');
        $titlePrompt = $this->requirePrompt('titre');

        $generator = $this->ai->textGenerator();
        $results = PromiseUtils::settle([
            // La narration principale seule passe par le générateur "recherche"
            // (outil web_search côté Mistral) : titre et catégorie n'ont pas
            // besoin de vérifier des faits, autant garder ces deux appels
            // rapides et sans outil.
            'text' => $this->ai->researchTextGenerator()->generateTextAsync(
                $systemPrompt,
                EpisodeFormat::narrationPrompt($userInput, $minutes, $level)
            ),
            'category' => $generator->generateTextAsync(
                'Répondez avec un seul mot décrivant la catégorie d\'activité ou le domaine correspondant au sujet donné.',
                str_replace('###REPLACE###', $subject, $keywordPrompt)
            ),
            'title' => $generator->generateTextAsync(
                $systemPrompt,
                str_replace('###REPLACE###', $subject, $titlePrompt)
            ),
        ])->wait();

        foreach ($results as $result) {
            if ($result['state'] !== 'fulfilled') {
                throw $this->toException($result['reason']);
            }
        }

        $text = $results['text']['value'];
        if (PromptGuard::isRefusal($text)) {
            // Inutile de demander une version plus longue d'un refus.
            throw new BlockedContentException(self::MESSAGE_REFUSED, 'narration : refus');
        }
        if (EpisodeFormat::isTooShort($text, $minutes)) {
            // Une seule relance : au pire, l'épisode est un peu plus court que prévu.
            $this->updateJob($jobId, 'processing', 'lengthen', 30);
            try {
                $longer = $this->ai->researchTextGenerator()->generateText(
                    $systemPrompt,
                    EpisodeFormat::expansionPrompt($text, $userInput, $minutes, $level)
                );
                if (EpisodeFormat::wordCount($longer) > EpisodeFormat::wordCount($text)) {
                    $text = $longer;
                }
            } catch (Exception $e) {
                $this->log('warning', 'Allongement de la narration impossible, texte initial conservé', [
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        // Le prompt « titre » interdit déjà le Markdown, mais le titre est
        // affiché tel quel : nettoyé ici en filet de sécurité, avec le sujet
        // brut en repli si le modèle ne renvoie rien d'exploitable.
        $title = mb_substr(EpisodeText::cleanTitle($results['title']['value']) ?: $userInput, 0, 255);
        foreach (['narration' => $text, 'titre' => $title] as $what => $output) {
            $problem = PromptGuard::outputProblem($output, $canary, $systemPrompt);
            if ($problem !== null) {
                throw new BlockedContentException(
                    $problem === 'refus' ? self::MESSAGE_REFUSED : self::MESSAGE_BLOCKED,
                    $what.' : '.$problem
                );
            }
        }

        return [$text, CategoryCatalog::rubriqueFor($results['category']['value']), $title];
    }

    /**
     * Lance en parallèle la génération de l'image et celle de l'audio (deux
     * appels indépendants, tous deux fonction du seul texte déjà généré) et
     * attend les deux résultats.
     *
     * L'échec de l'image n'est pas levé (l'appelant peut s'en passer) mais
     * renvoyé avec une image nulle ; celui de l'audio, si.
     *
     * @return array{0: ?string, 1: string, 2: string, 3: ?Exception} [image binaire, audio binaire, extension audio, erreur image]
     */
    private function generateImageAndAudio(string $jobId, string $generatedText): array
    {
        $imagePrompt = str_replace('###REPLACE###', $this->imagePromptExcerpt($generatedText), Prompt::content('image'));
        $synthesizer = $this->ai->speechSynthesizer();

        $promises = ['image' => $this->ai->imageGenerator()->generateImageAsync($imagePrompt)];
        foreach (SpeechAudio::chunks($generatedText) as $i => $chunk) {
            $promises['audio' . $i] = $synthesizer->synthesizeAsync($chunk);
        }

        // Chaque pièce terminée (pochette ou morceau de voix) fait avancer le
        // suivi de 46 à 90 % : le visiteur voit l'étape progresser au lieu
        // d'attendre la dernière réponse. Un échec d'écriture du suivi ne doit
        // pas faire échouer la génération.
        $total = count($promises);
        $finished = 0;
        foreach ($promises as $key => $promise) {
            $promises[$key] = $promise->then(function ($value) use ($jobId, $total, &$finished) {
                $finished++;
                try {
                    $this->updateJob($jobId, 'processing', 'media', 46 + intdiv(44 * $finished, $total));
                } catch (Exception) {
                }

                return $value;
            });
        }
        $results = PromiseUtils::settle($promises)->wait();

        $image = null;
        $imageError = null;
        if ($results['image']['state'] === 'fulfilled') {
            $image = $results['image']['value'];
        } else {
            $imageError = new Exception('Erreur lors de la génération de l\'image : ' . $this->toException($results['image']['reason'])->getMessage());
        }

        // Clés relues dans l'ordre des morceaux (settle() trie les clés comme
        // des chaînes : "audio10" passerait avant "audio2").
        $parts = [];
        for ($i = 0; isset($results['audio' . $i]); $i++) {
            $result = $results['audio' . $i];
            if ($result['state'] !== 'fulfilled') {
                throw new Exception('Erreur lors de la génération de l\'audio : ' . $this->toException($result['reason'])->getMessage());
            }
            $parts[] = $result['value'];
        }

        return [$image, SpeechAudio::joinMp3($parts), $synthesizer->audioExtension(), $imageError];
    }

    /** Aperçu du texte généré, coupé sur un mot entier, pour servir de base au prompt visuel. */
    private function imagePromptExcerpt(string $generatedText): string
    {
        $excerpt = trim($generatedText);
        if (mb_strlen($excerpt) <= self::IMAGE_PROMPT_EXCERPT_LENGTH) {
            return $excerpt;
        }

        $truncated = mb_substr($excerpt, 0, self::IMAGE_PROMPT_EXCERPT_LENGTH);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return trim($truncated);
    }

    private function toException(mixed $reason): Exception
    {
        return $reason instanceof Exception ? $reason : new Exception((string) $reason);
    }

    /** Prompt éditable en base, indispensable à la génération. */
    private function requirePrompt(string $type): string
    {
        $prompt = Prompt::content($type);
        if ($prompt === '') {
            throw new Exception('Le prompt de type "'.$type.'" est introuvable dans la base de données.');
        }

        return $prompt;
    }

    /** Journal du worker de génération (webserver/logs/api.log). */
    private function log(string $level, string $message, array $context = []): void
    {
        Log::log($level, $message, ['service' => 'generation-worker'] + $context);
    }
}
