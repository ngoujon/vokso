<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Generation;
use App\Models\GenerationJob;
use App\Models\Prompt;
use App\Services\Ai\AiProviderFactory;
use App\Support\CostEstimator;
use App\Support\EpisodeFormat;
use App\Support\EpisodeSlug;
use App\Support\EpisodeText;
use Exception;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use Illuminate\Support\Facades\Log;

/**
 * Exécute la chaîne complète de génération (texte, image, audio, catégorie)
 * pour un job donné et journalise la progression en base au fil des étapes.
 * Tourne hors requête HTTP, dans le processus détaché lancé par
 * GenerationController (commande artisan vokso:process-job).
 */
class PodcastGenerator
{
    /** Rubriques de la discothèque (liste fermée, une page thématique chacune). */
    public const RUBRIQUES = [
        'Sciences', 'Espace', 'Nature et environnement', 'Histoire', 'Société',
        'Culture et arts', 'Technologie', 'Santé', 'Gastronomie', 'Sport',
    ];

    private const MAX_INPUT_LENGTH = 300;

    // Un prompt trop long (texte intégral du podcast) noie le sujet visuel
    // dans le récit : la vignette n'a besoin que d'un aperçu du texte, pas du
    // script complet.
    private const IMAGE_PROMPT_EXCERPT_LENGTH = 400;

    // Un épisode de 15 minutes dépasse 13 000 caractères : la synthèse vocale
    // est faite par morceaux (coupés entre paragraphes ou phrases), en
    // parallèle, puis les MP3 sont mis bout à bout. Un épisode de 5 minutes
    // (≈ 4 500 caractères) reste en un seul appel, comme avant.
    private const SPEECH_CHUNK_LENGTH = 5000;

    public function __construct(
        private AiProviderFactory $ai,
        private string $outputDir
    ) {
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

        // Le texte, la catégorie et le titre ne dépendent que du sujet
        // d'entrée (pas l'un de l'autre) : les lancer en parallèle via des
        // promesses Guzzle économise des allers-retours réseau par rapport à
        // des appels séquentiels.
        $this->updateJob($jobId, 'processing', 'text', 10);
        try {
            [$generatedText, $categoryKeyword, $title] = $this->generateTextCategoryAndTitle($userInput, $minutes, $level);
            $this->storeOutput('responses', 'response_' . $this->getCurrentDateTime() . '.txt', $generatedText);
            $costText = CostEstimator::textCost(
                $this->ai->textProvider(),
                $this->ai->textModel(),
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
            [$imageContent, $audioContent, $audioExtension, $imageError] = $this->generateImageAndAudio($generatedText);

            if ($imageContent !== null) {
                $imageFileName = $this->storeOutput('images', 'image_' . $this->getCurrentDateTime() . '.png', $imageContent);
                if ($this->convertImageToWebP($imageFileName)) {
                    @unlink($this->outputDir . '/images/' . $imageFileName);
                    $imageFileName = str_replace('.png', '.webp', $imageFileName);
                }
                $costImage = CostEstimator::imageCost();
            } else {
                // Texte et audio sont déjà payés : plutôt que de tout perdre
                // pour une vignette, l'épisode reprend la couverture de sa
                // catégorie (copiée, pour garder un fichier par épisode).
                $imageFileName = $this->categoryCoverCopy($categoryKeyword);
                if ($imageFileName === null) {
                    throw $imageError;
                }
                Log::warning('Image de l\'épisode remplacée par la couverture de sa catégorie', [
                    'service' => 'generation-worker',
                    'job_id' => $jobId,
                    'category' => $categoryKeyword,
                    'reason' => $imageError->getMessage(),
                ]);
                $costImage = 0.0;
            }

            $audioFileName = $this->storeOutput(
                'audios',
                'audio_' . $this->getCurrentDateTime() . '.' . $audioExtension,
                $audioContent
            );
            $costAudio = CostEstimator::speechCost($generatedText);
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
            $job->user_id,
            $costText,
            $costImage,
            $costAudio,
            $minutes,
            $level
        );

        GenerationJob::whereKey($jobId)->update([
            'status' => 'done',
            'step' => 'done',
            'progress' => 100,
            'generation_id' => $generationId,
        ]);
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
        GenerationJob::whereKey($jobId)->update(['status' => $status, 'step' => $step, 'progress' => $progress]);
    }

    private function failJob(string $jobId, string $message, Exception $e): void
    {
        Log::error($message, [
            'service' => 'generation-worker',
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
    private function generateTextCategoryAndTitle(string $userInput, int $minutes, int $level): array
    {
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
                EpisodeFormat::narrationPrompt($userInput, $minutes, $level)
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

        $text = $results['text']['value'];
        if (EpisodeFormat::isTooShort($text, $minutes)) {
            // Une seule relance : au pire, l'épisode est un peu plus court que prévu.
            try {
                $longer = $this->ai->researchTextGenerator()->generateText(
                    $injectionPrompt,
                    EpisodeFormat::expansionPrompt($text, $userInput, $minutes, $level)
                );
                if (EpisodeFormat::wordCount($longer) > EpisodeFormat::wordCount($text)) {
                    $text = $longer;
                }
            } catch (Exception $e) {
                Log::warning('Allongement de la narration impossible, texte initial conservé', [
                    'service' => 'generation-worker',
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        return [
            $text,
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
        // Rubrique de la liste fermée (prompt « keyword », voir SQL/20261004000000.sql),
        // reconnue même si le modèle change la casse, les accents ou ajoute du texte.
        $normalized = EpisodeText::slugify($rawCategory);
        foreach (self::RUBRIQUES as $rubrique) {
            $slug = EpisodeText::slugify($rubrique);
            if ($normalized === $slug || str_starts_with($normalized, $slug.'-')) {
                return $rubrique;
            }
        }

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
     * L'échec de l'image n'est pas levé (l'appelant peut s'en passer) mais
     * renvoyé avec une image nulle ; celui de l'audio, si.
     *
     * @return array{0: ?string, 1: string, 2: string, 3: ?Exception} [image binaire, audio binaire, extension audio, erreur image]
     */
    private function generateImageAndAudio(string $generatedText): array
    {
        $imagePrompt = str_replace('###REPLACE###', $this->imagePromptExcerpt($generatedText), $this->getPrompt('image'));
        $synthesizer = $this->ai->speechSynthesizer();

        $promises = ['image' => $this->ai->imageGenerator()->generateImageAsync($imagePrompt)];
        foreach ($this->speechChunks($generatedText) as $i => $chunk) {
            $promises['audio' . $i] = $synthesizer->synthesizeAsync($chunk);
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

        return [$image, $this->joinMp3($parts), $synthesizer->audioExtension(), $imageError];
    }

    /**
     * Découpe le texte en morceaux d'au plus SPEECH_CHUNK_LENGTH caractères,
     * entre paragraphes de préférence, sinon entre phrases.
     *
     * @return list<string>
     */
    private function speechChunks(string $text): array
    {
        $text = trim($text);
        if (mb_strlen($text) <= self::SPEECH_CHUNK_LENGTH) {
            return [$text];
        }

        $pieces = [];
        foreach (preg_split('/\R+/u', $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            if (mb_strlen($paragraph) <= self::SPEECH_CHUNK_LENGTH) {
                $pieces[] = $paragraph;
                continue;
            }
            // Paragraphe trop long : coupé entre phrases.
            foreach (preg_split('/(?<=[.!?…])\s+/u', $paragraph) ?: [] as $sentence) {
                $pieces[] = $sentence;
            }
        }

        $chunks = [];
        $current = '';
        foreach ($pieces as $piece) {
            $separator = $current === '' ? '' : "\n\n";
            if ($current !== '' && mb_strlen($current . $separator . $piece) > self::SPEECH_CHUNK_LENGTH) {
                $chunks[] = $current;
                $current = $piece;
            } else {
                $current .= $separator . $piece;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Met bout à bout des MP3 en débit constant (sortie Voxtral : en-tête ID3v2
     * puis trames brutes, sans en-tête Xing) : on retire les étiquettes ID3
     * des morceaux suivants pour obtenir un flux continu dont la durée se
     * déduit correctement de la taille.
     *
     * @param list<string> $parts
     */
    private function joinMp3(array $parts): string
    {
        if (count($parts) === 1) {
            return $parts[0];
        }

        $joined = '';
        foreach ($parts as $i => $part) {
            if (strlen($part) > 128 && substr($part, -128, 3) === 'TAG') {
                $part = substr($part, 0, -128);
            }
            if ($i > 0 && strlen($part) >= 10 && substr($part, 0, 3) === 'ID3') {
                $size = ((ord($part[6]) & 0x7F) << 21) | ((ord($part[7]) & 0x7F) << 14) | ((ord($part[8]) & 0x7F) << 7) | (ord($part[9]) & 0x7F);
                $footer = (ord($part[5]) & 0x10) ? 10 : 0;
                $part = substr($part, 10 + $size + $footer);
            }
            $joined .= $part;
        }

        return $joined;
    }

    /** Copie de la couverture de la catégorie (si elle existe déjà), ou null. */
    private function categoryCoverCopy(string $categoryLabel): ?string
    {
        $cover = Category::where('label', $categoryLabel)->value('cover_image');
        $source = $this->outputDir . '/images/' . $cover;
        if (empty($cover) || !is_file($source)) {
            return null;
        }

        $fileName = 'image_' . $this->getCurrentDateTime() . '.' . pathinfo((string) $cover, PATHINFO_EXTENSION);

        return copy($source, $this->outputDir . '/images/' . $fileName) ? $fileName : null;
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
    /**
     * Catégorie imposée (commande vokso:seed-podcasts --categorie) : créée au
     * besoin, et habillée (icône, couverture) si elle ne l'a jamais été — cas
     * des catégories antérieures à la génération automatique de couvertures.
     */
    public function ensureCategory(string $categoryLabel): int
    {
        $category = Category::where('label', $categoryLabel)->first();
        if ($category === null) {
            return $this->saveOrGetCategory($categoryLabel);
        }

        if (empty($category->cover_image)) {
            $this->ensureCategoryAssets((int) $category->idcategorie, $categoryLabel);
        }

        return (int) $category->idcategorie;
    }

    private function saveOrGetCategory(string $categoryLabel): int
    {
        $existing = Category::where('label', $categoryLabel)->value('idcategorie');
        if ($existing !== null) {
            return (int) $existing;
        }

        $idcategorie = (int) Category::create(['label' => $categoryLabel])->idcategorie;

        $this->ensureCategoryAssets($idcategorie, $categoryLabel);

        return $idcategorie;
    }

    /**
     * Choisit une icône et génère une image de couverture pour une catégorie
     * tout juste créée. Best-effort : un échec ici (icône/couverture
     * manquante) ne doit jamais faire échouer toute la génération du
     * podcast, la catégorie reste utilisable sans ces habillages visuels.
     */
    private function ensureCategoryAssets(int $idcategorie, string $categoryLabel): void
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
            Log::error('Erreur lors du choix de l\'icône de catégorie', [
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
            Log::error('Erreur lors de la génération de la couverture de catégorie', [
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
            Category::whereKey($idcategorie)->update(['icon' => $icon, 'cover_image' => $coverFileName]);
        } catch (Exception $e) {
            Log::error('Erreur lors de l\'enregistrement de l\'icône/couverture de catégorie', [
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
        float $costAudio,
        int $minutes,
        int $level
    ): string {
        $generationId = 'gen_' . uniqid();
        $format = EpisodeFormat::columnsReady() ? ['duration_minutes' => $minutes, 'level' => $level] : [];
        Generation::create($format + [
            'generation_id' => $generationId,
            'slug' => EpisodeSlug::forTitle((string) $title),
            'title' => $title,
            'text_content' => $textContent,
            'image_url' => $imageUrl,
            'audio_url' => $audioUrl,
            'idcategorie' => $idcategorie,
            'user_id' => $userId,
            'cost_text' => $costText,
            'cost_image' => $costImage,
            'cost_audio' => $costAudio,
            'cost_total' => $costText + $costImage + $costAudio,
        ]);

        return $generationId;
    }

    private function getPrompt(string $type): string
    {
        return Prompt::content($type);
    }

    private function getCurrentDateTime(): string
    {
        return date('Ymd_His');
    }

    /**
     * Convertit l'image générée en WebP à côté, et ne renvoie true que si le
     * fichier WebP a bien été produit. Le format réel dépend du fournisseur
     * (Mistral renvoie du JPEG malgré l'extension .png) : il est détecté
     * d'après le contenu, pas d'après le nom.
     */
    private function convertImageToWebP(string $pngFileName): bool
    {
        $sourcePath = $this->outputDir . '/images/' . $pngFileName;
        if (!file_exists($sourcePath)) {
            return false;
        }

        $webpPath = preg_replace('/\.png$/', '.webp', $sourcePath);
        try {
            if (extension_loaded('imagick')) {
                $image = new \Imagick($sourcePath);
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality(80);
                $image->writeImage($webpPath);
                $image->clear();
            } elseif (function_exists('imagewebp')) {
                $image = @imagecreatefromstring((string) file_get_contents($sourcePath));
                if ($image !== false) {
                    imagewebp($image, $webpPath, 80);
                    imagedestroy($image);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Erreur de conversion WebP', ['exception' => get_class($e), 'reason' => $e->getMessage()]);
        }

        return file_exists($webpPath);
    }
}
