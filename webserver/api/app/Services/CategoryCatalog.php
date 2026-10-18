<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Prompt;
use App\Services\Ai\AiProviderFactory;
use App\Support\EpisodeText;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Rubriques de la discothèque et habillage de leurs catégories : une
 * catégorie créée reçoit une icône et une couverture générées par IA, une
 * seule fois (les épisodes suivants la réutilisent sans nouvel appel).
 */
class CategoryCatalog
{
    /** Rubriques de la discothèque (liste fermée, une page thématique chacune). */
    public const RUBRIQUES = [
        'Sciences', 'Espace', 'Nature et environnement', 'Histoire', 'Société',
        'Culture et arts', 'Technologie', 'Santé', 'Gastronomie', 'Sport',
    ];

    /**
     * Rubrique retenue quand le modèle n'en renvoie aucune de la liste : un
     * sujet ne crée jamais de catégorie publique (page, couverture IA) à
     * partir d'un mot choisi par le modèle, donc influençable par le sujet.
     */
    public const DEFAULT_RUBRIQUE = 'Société';

    /**
     * Icônes Bootstrap Icons proposées au modèle (prompt « categorie_icone ») :
     * liste fermée, revalidée après réponse pour ne jamais écrire en base une
     * classe CSS inventée. « soundwave » (thème podcast) sert de repli.
     */
    private const ICONS = [
        'cpu', 'laptop', 'flask', 'heart-pulse', 'hourglass-split', 'bank',
        'music-note-beamed', 'trophy', 'egg-fried', 'graph-up-arrow', 'cash-coin',
        'airplane', 'tree', 'palette', 'mortarboard', 'people', 'camera-reels',
        'book', 'rocket', 'controller', 'briefcase', 'emoji-smile', 'lightbulb',
        'moon-stars', 'car-front', 'bag', 'globe', 'newspaper', 'film', 'gear',
        'house', 'cup-hot', 'joystick', 'umbrella', 'compass', 'map', 'calculator',
        'code-slash', 'star', 'building', 'flag', 'puzzle', 'chat-dots', 'soundwave',
    ];

    private const DEFAULT_ICON = 'soundwave';

    public function __construct(private AiProviderFactory $ai, private OutputStorage $storage)
    {
    }

    /**
     * Rubrique de la liste fermée correspondant à la réponse du modèle (prompt
     * « keyword »), reconnue même si la casse, les accents ou du texte en plus
     * diffèrent.
     */
    public static function rubriqueFor(string $modelAnswer): string
    {
        $normalized = EpisodeText::slugify($modelAnswer);
        foreach (self::RUBRIQUES as $rubrique) {
            $slug = EpisodeText::slugify($rubrique);
            if ($normalized === $slug || str_starts_with($normalized, $slug.'-')) {
                return $rubrique;
            }
        }

        return self::DEFAULT_RUBRIQUE;
    }

    /** Id de la catégorie, créée et habillée si elle n'existe pas encore. */
    public function idFor(string $label): int
    {
        $existing = Category::where('label', $label)->value('idcategorie');
        if ($existing !== null) {
            return (int) $existing;
        }

        $id = (int) Category::create(['label' => $label])->idcategorie;
        $this->dress($id, $label);

        return $id;
    }

    /**
     * Comme idFor(), mais habille aussi une catégorie existante restée sans
     * couverture (commande vokso:seed-podcasts --categorie).
     */
    public function ensure(string $label): int
    {
        $category = Category::where('label', $label)->first();
        if ($category === null) {
            return $this->idFor($label);
        }
        if (empty($category->cover_image)) {
            $this->dress((int) $category->idcategorie, $label);
        }

        return (int) $category->idcategorie;
    }

    /** Copie de la couverture de la catégorie, pour servir de vignette à un épisode ; null si elle n'en a pas. */
    public function coverCopy(string $label): ?string
    {
        return $this->storage->copyImage(Category::where('label', $label)->value('cover_image'), 'image');
    }

    /**
     * Icône et couverture générées par IA. Best-effort : un échec ne fait
     * jamais échouer l'épisode (texte, image et voix déjà payés), la
     * catégorie reste utilisable sans habillage.
     */
    private function dress(int $id, string $label): void
    {
        $icon = self::DEFAULT_ICON;
        $iconPrompt = Prompt::content('categorie_icone');
        if ($iconPrompt !== '') {
            try {
                $icon = self::sanitizeIcon($this->ai->textGenerator()->generateText(
                    'Tu réponds uniquement avec le nom d\'icône demandé, sans aucun autre texte.',
                    str_replace('###REPLACE###', $label, $iconPrompt)
                ));
            } catch (Exception $e) {
                $this->logFailure('Erreur lors du choix de l\'icône de catégorie', $label, $e);
            }
        }

        $cover = null;
        $coverPrompt = Prompt::content('categorie_cover');
        if ($coverPrompt !== '') {
            try {
                $cover = $this->storage->storeImage(
                    'category_'.$id,
                    $this->ai->imageGenerator()->generateImage(str_replace('###REPLACE###', $label, $coverPrompt))
                );
            } catch (Exception $e) {
                $this->logFailure('Erreur lors de la génération de la couverture de catégorie', $label, $e);
            }
        }

        try {
            Category::whereKey($id)->update(['icon' => $icon, 'cover_image' => $cover]);
        } catch (Exception $e) {
            $this->logFailure('Erreur lors de l\'enregistrement de l\'icône/couverture de catégorie', $label, $e);
        }
    }

    /** Ramène la réponse du modèle (« bi-cpu », « Cpu », « cpu. ») à une icône connue, ou au repli. */
    private static function sanitizeIcon(string $rawIcon): string
    {
        $icon = preg_replace(['/^bi-/', '/[^a-z0-9-]/'], '', strtolower(trim($rawIcon)));

        return in_array($icon, self::ICONS, true) ? $icon : self::DEFAULT_ICON;
    }

    private function logFailure(string $message, string $label, Exception $e): void
    {
        Log::error($message, [
            'service' => 'generation-worker',
            'category' => $label,
            'exception' => get_class($e),
            'reason' => $e->getMessage(),
        ]);
    }
}
