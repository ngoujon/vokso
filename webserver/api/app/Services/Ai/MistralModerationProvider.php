<?php

namespace App\Services\Ai;

use Exception;

/** Modération de texte Mistral (sujet saisi, puis titre et narration avant publication). */
class MistralModerationProvider implements ModeratorInterface
{
    /**
     * Catégories qui empêchent la publication. Santé, finance, droit et
     * données personnelles sont des thèmes d'épisode légitimes : ignorés.
     */
    public const BLOCKING_CATEGORIES = [
        'sexual', 'hate_and_discrimination', 'violence_and_threats', 'dangerous_and_criminal_content', 'selfharm',
    ];

    // Un épisode de 15 minutes dépasse 13 000 caractères : modéré par morceaux,
    // envoyés dans un seul appel.
    private const CHUNK_LENGTH = 6000;

    private ApiClient $api;
    private string $model;

    public function __construct(array $config)
    {
        $this->api = ApiClient::mistral($config + ['timeout' => 60]);
        $this->model = (string) (($config['moderation_model'] ?? '') ?: 'mistral-moderation-latest');
    }

    public function flaggedCategories(string $text): array
    {
        $chunks = mb_str_split(trim($text), self::CHUNK_LENGTH) ?: [''];
        $data = $this->api->postJson('/moderations', ['model' => $this->model, 'input' => $chunks]);
        if (! isset($data['results']) || ! is_array($data['results']) || count($data['results']) !== count($chunks)) {
            throw new Exception('Réponse de l\'API inattendue pour la modération.');
        }

        $flagged = [];
        foreach ($data['results'] as $result) {
            foreach (self::BLOCKING_CATEGORIES as $category) {
                if (! empty($result['categories'][$category])) {
                    $flagged[$category] = true;
                }
            }
        }

        return array_keys($flagged);
    }
}
