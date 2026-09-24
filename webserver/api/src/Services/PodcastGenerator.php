<?php

namespace App\Services;

use App\Utils\CostEstimator;
use App\Utils\Logger;
use Exception;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use PDO;

/**
 * Exécute la chaîne complète de génération (texte, image, audio, catégorie)
 * pour un job donné et journalise la progression en base au fil des étapes.
 * Extrait de GenerationController pour pouvoir tourner hors requête HTTP,
 * dans le processus détaché lancé par le worker CLI.
 */
class PodcastGenerator
{
    private const MAX_INPUT_LENGTH = 300;

    // DALL-E 3 refuse tout prompt au-delà de 4000 caractères, et un prompt
    // trop long (texte intégral du podcast) noie le sujet visuel dans le
    // récit : la vignette n'a besoin que d'un aperçu du texte, pas du script
    // complet.
    private const IMAGE_PROMPT_EXCERPT_LENGTH = 400;

    public function __construct(
        private PDO $db,
        private AiProviderFactory $ai,
        private string $outputDir
    ) {
    }

    public function process(string $jobId): void
    {
        $job = $this->fetchJob($jobId);
        if (!$job) {
            return;
        }

        $userInput = $job['input'];

        if ($job['source_type'] === 'audio') {
            $this->updateJob($jobId, 'processing', 'transcription', 5);
            try {
                $userInput = $this->transcribeAudio($job['audio_path']);
            } catch (Exception $e) {
                $this->failJob($jobId, 'Erreur lors de la transcription audio', $e);
                return;
            } finally {
                $this->deleteUploadedAudio($job['audio_path']);
            }

            $stmt = $this->db->prepare('UPDATE generation_jobs SET input = :input WHERE job_id = :job_id');
            $stmt->execute([':input' => $userInput, ':job_id' => $jobId]);
        }

        // Le texte, la catégorie et le titre ne dépendent que du sujet
        // d'entrée (pas l'un de l'autre) : les lancer en parallèle via des
        // promesses Guzzle économise des allers-retours réseau par rapport à
        // des appels séquentiels.
        $this->updateJob($jobId, 'processing', 'text', 10);
        try {
            [$generatedText, $categoryKeyword, $title] = $this->generateTextCategoryAndTitle($userInput);
            $this->storeOutput('responses', 'response_' . $this->getCurrentDateTime() . '.txt', $generatedText);
            $costText = CostEstimator::textCost(
                $this->textProvider(),
                $this->textModel(),
                $userInput,
                $generatedText
            );
        } catch (Exception $e) {
            $this->failJob($jobId, 'Erreur lors de la génération du texte', $e);
            return;
        }

        // Idem pour l'image et l'audio : toutes deux ne dépendent que du texte
        // généré, jamais l'une de l'autre, donc autant les lancer de front.
        $this->updateJob($jobId, 'processing', 'media', 45);
        try {
            [$imageContent, $audioContent, $audioExtension] = $this->generateImageAndAudio($generatedText);

            $imageFileName = $this->storeOutput('images', 'image_' . $this->getCurrentDateTime() . '.png', $imageContent);
            if ($this->convertImageToWebP($imageFileName)) {
                @unlink($this->outputDir . '/images/' . $imageFileName);
                $imageFileName = str_replace('.png', '.webp', $imageFileName);
            }
            $costImage = CostEstimator::imageCost($this->imageModel());

            $audioFileName = $this->storeOutput(
                'audios',
                'audio_' . $this->getCurrentDateTime() . '.' . $audioExtension,
                $audioContent
            );
            $costAudio = CostEstimator::speechCost($this->speechProvider(), $this->speechModel(), $generatedText);
        } catch (Exception $e) {
            $this->failJob($jobId, $e->getMessage(), $e);
            return;
        }

        $this->updateJob($jobId, 'processing', 'finalizing', 90);
        $idcategorie = $this->saveOrGetCategory($categoryKeyword);

        $generationId = $this->saveGeneration(
            $title,
            $generatedText,
            $imageFileName,
            $audioFileName,
            $idcategorie,
            $job['user_id'] ?? null,
            $costText,
            $costImage,
            $costAudio
        );

        $stmt = $this->db->prepare(
            'UPDATE generation_jobs SET status = "done", step = "done", progress = 100, generation_id = :gid WHERE job_id = :job_id'
        );
        $stmt->execute([':gid' => $generationId, ':job_id' => $jobId]);
    }

    private function fetchJob(string $jobId): ?array
    {
        $stmt = $this->db->prepare('SELECT input, source_type, audio_path, user_id FROM generation_jobs WHERE job_id = :job_id');
        $stmt->execute([':job_id' => $jobId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Transcrit le fichier audio déposé et le ramène au même format qu'un
     * sujet saisi au clavier : un texte assaini et borné à MAX_INPUT_LENGTH,
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

        return mb_substr($transcript, 0, self::MAX_INPUT_LENGTH);
    }

    private function deleteUploadedAudio(?string $audioPath): void
    {
        if ($audioPath !== null && is_file($audioPath)) {
            @unlink($audioPath);
        }
    }

    private function updateJob(string $jobId, string $status, string $step, int $progress): void
    {
        $stmt = $this->db->prepare(
            'UPDATE generation_jobs SET status = :status, step = :step, progress = :progress WHERE job_id = :job_id'
        );
        $stmt->execute([
            ':status' => $status,
            ':step' => $step,
            ':progress' => $progress,
            ':job_id' => $jobId,
        ]);
    }

    private function failJob(string $jobId, string $message, Exception $e): void
    {
        Logger::get()->error($message, [
            'service' => 'generation-worker',
            'job_id' => $jobId,
            'exception' => get_class($e),
            'reason' => $e->getMessage(),
        ]);
        $stmt = $this->db->prepare(
            'UPDATE generation_jobs SET status = "error", error_message = :error WHERE job_id = :job_id'
        );
        $stmt->execute([':error' => $message, ':job_id' => $jobId]);
    }

    /**
     * Lance en parallèle la génération du texte principal, celle de la
     * catégorie et celle du titre (trois appels indépendants au même modèle
     * de texte, tous fonction du seul sujet d'entrée) et attend les trois
     * résultats.
     *
     * @return array{0: string, 1: string, 2: string} [texte généré, mot-clé de catégorie, titre]
     */
    private function generateTextCategoryAndTitle(string $userInput): array
    {
        $textPrompt = $this->getPrompt('texte');
        if ($textPrompt === '') {
            throw new Exception('Le prompt de type "texte" est introuvable dans la base de données.');
        }

        $injectionPrompt = $this->getPrompt('injection');
        if ($injectionPrompt === '') {
            throw new Exception('Le prompt de protection contre les injections est introuvable dans la base de données.');
        }

        $keywordPrompt = $this->getPrompt('keyword');
        if ($keywordPrompt === '') {
            throw new Exception('Le prompt de type "keyword" est introuvable dans la base de données.');
        }

        $titlePrompt = $this->getPrompt('titre');
        if ($titlePrompt === '') {
            throw new Exception('Le prompt de type "titre" est introuvable dans la base de données.');
        }

        $generator = $this->ai->textGenerator();
        $results = PromiseUtils::settle([
            // La narration principale seule passe par le générateur "recherche"
            // (outil web_search côté Mistral) : titre et catégorie n'ont pas
            // besoin de vérifier des faits, autant garder ces deux appels
            // rapides et sans outil.
            'text' => $this->ai->researchTextGenerator()->generateTextAsync(
                $injectionPrompt,
                str_replace('###REPLACE###', $userInput, $textPrompt)
            ),
            'category' => $generator->generateTextAsync(
                'Répondez avec un seul mot décrivant la catégorie d\'activité ou le domaine correspondant au sujet donné.',
                str_replace('###REPLACE###', $userInput, $keywordPrompt)
            ),
            'title' => $generator->generateTextAsync(
                $injectionPrompt,
                str_replace('###REPLACE###', $userInput, $titlePrompt)
            ),
        ])->wait();

        if ($results['text']['state'] !== 'fulfilled') {
            throw $this->toException($results['text']['reason']);
        }
        if ($results['category']['state'] !== 'fulfilled') {
            throw $this->toException($results['category']['reason']);
        }
        if ($results['title']['state'] !== 'fulfilled') {
            throw $this->toException($results['title']['reason']);
        }

        return [
            $results['text']['value'],
            $this->sanitizeCategory($results['category']['value']),
            $this->sanitizeTitle($results['title']['value'], $userInput),
        ];
    }

    /**
     * Titre nettoyé (une ligne, sans guillemets, sans Markdown, borné) ;
     * retombe sur le sujet brut si le modèle ne renvoie rien d'exploitable.
     * Le prompt "titre" interdit déjà le Markdown, mais le titre est affiché
     * tel quel côté front (pas de rendu Markdown) : on le nettoie aussi ici,
     * en filet de sécurité, au cas où le modèle l'ignorerait.
     */
    private function sanitizeTitle(string $rawTitle, string $fallback): string
    {
        $title = trim($rawTitle, " \t\n\r\0\x0B\"'“”«»");
        // Titres/emphases Markdown ("# ", "**gras**", "_italique_", "`code`")
        // et tirets de liste en tête de ligne.
        $title = preg_replace('/^#{1,6}\s+/', '', $title);
        $title = preg_replace('/^[-*+]\s+/', '', $title);
        $title = preg_replace('/(\*\*|__)(.*?)\1/', '$2', $title);
        $title = preg_replace('/(\*|_|`)(.*?)\1/', '$2', $title);
        $title = str_replace(['*', '#', '`'], '', $title);
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim($title);

        if ($title === '') {
            $title = $fallback;
        }

        return mb_substr($title, 0, 255);
    }

    private function sanitizeCategory(string $rawCategory): string
    {
        $category = trim(preg_replace('/[^\p{L}\p{N}\- ]/u', '', $rawCategory));
        $category = explode(' ', trim($category))[0] ?? '';
        if ($category === '') {
            throw new Exception('Aucune catégorie exploitable n\'a été renvoyée par le modèle.');
        }

        return mb_substr($category, 0, 50);
    }

    /**
     * Lance en parallèle la génération de l'image et celle de l'audio (deux
     * appels indépendants, tous deux fonction du seul texte déjà généré) et
     * attend les deux résultats.
     *
     * @return array{0: string, 1: string, 2: string} [image binaire, audio binaire, extension audio]
     */
    private function generateImageAndAudio(string $generatedText): array
    {
        $imagePrompt = str_replace('###REPLACE###', $this->imagePromptExcerpt($generatedText), $this->getPrompt('image'));
        $synthesizer = $this->ai->speechSynthesizer();

        $results = PromiseUtils::settle([
            'image' => $this->ai->imageGenerator()->generateImageAsync($imagePrompt),
            'audio' => $synthesizer->synthesizeAsync($generatedText),
        ])->wait();

        if ($results['image']['state'] !== 'fulfilled') {
            throw new Exception('Erreur lors de la génération de l\'image : ' . $this->toException($results['image']['reason'])->getMessage());
        }
        if ($results['audio']['state'] !== 'fulfilled') {
            throw new Exception('Erreur lors de la génération de l\'audio : ' . $this->toException($results['audio']['reason'])->getMessage());
        }

        return [$results['image']['value'], $results['audio']['value'], $synthesizer->audioExtension()];
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

    /** Écrit un fichier de sortie et retourne son nom. */
    private function storeOutput(string $subDir, string $fileName, string $content): string
    {
        $directory = $this->outputDir . '/' . $subDir;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new Exception('Impossible de créer le dossier de sortie : ' . $subDir);
        }

        if (file_put_contents($directory . '/' . $fileName, $content) === false) {
            throw new Exception('Impossible d\'écrire le fichier : ' . $fileName);
        }

        return $fileName;
    }

    /**
     * Icônes Bootstrap Icons valides proposées au modèle pour habiller une
     * catégorie (voir le prompt "categorie_icone" en base) : liste fermée,
     * revalidée ici après réponse, pour ne jamais écrire en base une classe
     * CSS inventée par le modèle. "soundwave" sert de repli neutre (thème
     * podcast) si la réponse ne correspond à aucune entrée connue.
     */
    private const CATEGORY_ICONS = [
        'cpu', 'laptop', 'flask', 'heart-pulse', 'hourglass-split', 'bank',
        'music-note-beamed', 'trophy', 'egg-fried', 'graph-up-arrow', 'cash-coin',
        'airplane', 'tree', 'palette', 'mortarboard', 'people', 'camera-reels',
        'book', 'rocket', 'controller', 'briefcase', 'emoji-smile', 'lightbulb',
        'moon-stars', 'car-front', 'bag', 'globe', 'newspaper', 'film', 'gear',
        'house', 'cup-hot', 'joystick', 'umbrella', 'compass', 'map', 'calculator',
        'code-slash', 'star', 'building', 'flag', 'puzzle', 'chat-dots', 'soundwave',
    ];
    private const CATEGORY_DEFAULT_ICON = 'soundwave';

    /**
     * Retourne l'id de la catégorie, en la créant si besoin. Une catégorie
     * nouvellement créée reçoit en plus une icône et une image de couverture
     * générées par IA (une seule fois : les créations suivantes du même
     * podcast la réutilisent via le SELECT ci-dessus, sans nouvel appel IA).
     */
    private function saveOrGetCategory(string $categoryLabel)
    {
        $stmt = $this->db->prepare('SELECT idcategorie FROM categorie WHERE label = :label');
        $stmt->execute([':label' => $categoryLabel]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row['idcategorie'];
        }

        $stmt = $this->db->prepare('INSERT INTO categorie (label) VALUES (:label)');
        $stmt->execute([':label' => $categoryLabel]);
        $idcategorie = $this->db->lastInsertId();

        $this->ensureCategoryAssets($idcategorie, $categoryLabel);

        return $idcategorie;
    }

    /**
     * Choisit une icône et génère une image de couverture pour une catégorie
     * tout juste créée. Best-effort : un échec ici (icône/couverture
     * manquante) ne doit jamais faire échouer toute la génération du
     * podcast, la catégorie reste utilisable sans ces habillages visuels.
     */
    private function ensureCategoryAssets(string $idcategorie, string $categoryLabel): void
    {
        $icon = self::CATEGORY_DEFAULT_ICON;
        try {
            $iconPrompt = $this->getPrompt('categorie_icone');
            if ($iconPrompt !== '') {
                $rawIcon = $this->ai->textGenerator()->generateText(
                    'Tu réponds uniquement avec le nom d\'icône demandé, sans aucun autre texte.',
                    str_replace('###REPLACE###', $categoryLabel, $iconPrompt)
                );
                $icon = $this->sanitizeIcon($rawIcon);
            }
        } catch (Exception $e) {
            Logger::get()->error('Erreur lors du choix de l\'icône de catégorie', [
                'service' => 'generation-worker',
                'category' => $categoryLabel,
                'exception' => get_class($e),
                'reason' => $e->getMessage(),
            ]);
        }

        $coverFileName = null;
        try {
            $coverPrompt = $this->getPrompt('categorie_cover');
            if ($coverPrompt !== '') {
                $coverContent = $this->ai->imageGenerator()->generateImage(
                    str_replace('###REPLACE###', $categoryLabel, $coverPrompt)
                );
                $coverFileName = $this->storeOutput('images', 'category_' . $idcategorie . '_' . $this->getCurrentDateTime() . '.png', $coverContent);
                if ($this->convertImageToWebP($coverFileName)) {
                    @unlink($this->outputDir . '/images/' . $coverFileName);
                    $coverFileName = str_replace('.png', '.webp', $coverFileName);
                }
            }
        } catch (Exception $e) {
            Logger::get()->error('Erreur lors de la génération de la couverture de catégorie', [
                'service' => 'generation-worker',
                'category' => $categoryLabel,
                'exception' => get_class($e),
                'reason' => $e->getMessage(),
            ]);
        }

        // Comme les deux blocs précédents : best-effort. En particulier, si le
        // déploiement du code précède l'application de la migration SQL qui
        // ajoute ces colonnes (scripts/deploy.sh ne rejoue jamais SQL/, voir
        // son en-tête), cette requête échoue avec "colonne inconnue" — la
        // catégorie doit rester utilisable sans icône/couverture plutôt que
        // de faire échouer tout le job après coup (texte/image/audio déjà
        // payés à ce stade).
        try {
            $stmt = $this->db->prepare('UPDATE categorie SET icon = :icon, cover_image = :cover WHERE idcategorie = :id');
            $stmt->execute([
                ':icon' => $icon,
                ':cover' => $coverFileName,
                ':id' => $idcategorie,
            ]);
        } catch (Exception $e) {
            Logger::get()->error('Erreur lors de l\'enregistrement de l\'icône/couverture de catégorie', [
                'service' => 'generation-worker',
                'category' => $categoryLabel,
                'exception' => get_class($e),
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /** Ramène la réponse du modèle ("bi-cpu", "Cpu", "cpu.") à une entrée connue de CATEGORY_ICONS, ou au repli par défaut. */
    private function sanitizeIcon(string $rawIcon): string
    {
        $icon = strtolower(trim($rawIcon));
        $icon = preg_replace('/^bi-/', '', $icon);
        $icon = preg_replace('/[^a-z0-9-]/', '', $icon);

        return in_array($icon, self::CATEGORY_ICONS, true) ? $icon : self::CATEGORY_DEFAULT_ICON;
    }

    private function saveGeneration(
        $title,
        $textContent,
        $imageUrl,
        $audioUrl,
        $idcategorie,
        ?int $userId,
        float $costText,
        float $costImage,
        float $costAudio
    ): string {
        $generationId = 'gen_' . uniqid();
        $stmt = $this->db->prepare(
            'INSERT INTO generations
                (generation_id, title, text_content, image_url, audio_url, idcategorie, user_id, cost_text, cost_image, cost_audio, cost_total)
             VALUES
                (:generation_id, :title, :text_content, :image_url, :audio_url, :idcategorie, :user_id, :cost_text, :cost_image, :cost_audio, :cost_total)'
        );
        $stmt->execute([
            ':generation_id' => $generationId,
            ':title' => $title,
            ':text_content' => $textContent,
            ':image_url' => $imageUrl,
            ':audio_url' => $audioUrl,
            ':idcategorie' => $idcategorie,
            ':user_id' => $userId,
            ':cost_text' => $costText,
            ':cost_image' => $costImage,
            ':cost_audio' => $costAudio,
            ':cost_total' => $costText + $costImage + $costAudio,
        ]);
        return $generationId;
    }

    private function textProvider(): string
    {
        return strtolower(trim((string) ($_ENV['AI_TEXT_PROVIDER'] ?? 'mistral')));
    }

    private function textModel(): string
    {
        return match ($this->textProvider()) {
            'ollama' => (string) ($_ENV['OLLAMA_TEXT_MODEL'] ?? ''),
            default => (string) ($_ENV['MISTRAL_TEXT_MODEL'] ?? 'mistral-small-latest'),
        };
    }

    private function imageModel(): string
    {
        return (string) ($_ENV['MISTRAL_IMAGE_MODEL'] ?? 'mistral-medium-latest');
    }

    private function speechProvider(): string
    {
        return strtolower(trim((string) ($_ENV['AI_SPEECH_PROVIDER'] ?? 'mistral')));
    }

    private function speechModel(): string
    {
        return match ($this->speechProvider()) {
            'local' => (string) ($_ENV['TTS_MODEL'] ?? ''),
            default => (string) ($_ENV['MISTRAL_SPEECH_MODEL'] ?? 'voxtral-mini-tts-latest'),
        };
    }

    private function getPrompt(string $type): string
    {
        $stmt = $this->db->prepare('SELECT content FROM prompt WHERE type = :type');
        $stmt->execute([':type' => $type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['content'] : '';
    }

    private function getCurrentDateTime(): string
    {
        return date('Ymd_His');
    }

    /** Convertit le PNG en WebP à côté, et ne renvoie true que si le fichier WebP a bien été produit. */
    private function convertImageToWebP(string $pngFileName): bool
    {
        $pngPath = $this->outputDir . '/images/' . $pngFileName;
        if (!file_exists($pngPath)) {
            return false;
        }

        $webpPath = str_replace('.png', '.webp', $pngPath);
        if (extension_loaded('imagick')) {
            try {
                $image = new \Imagick($pngPath);
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality(80);
                $image->writeImage($webpPath);
                $image->clear();
            } catch (\Exception $e) {
                Logger::get()->error('Erreur conversion WebP via Imagick', ['exception' => get_class($e), 'reason' => $e->getMessage()]);
            }
        } elseif (extension_loaded('gd')) {
            try {
                $image = imagecreatefrompng($pngPath);
                if ($image !== false) {
                    imagewebp($image, $webpPath, 80);
                    imagedestroy($image);
                }
            } catch (\Exception $e) {
                Logger::get()->error('Erreur conversion WebP via GD', ['exception' => get_class($e), 'reason' => $e->getMessage()]);
            }
        }

        return file_exists($webpPath);
    }
}
