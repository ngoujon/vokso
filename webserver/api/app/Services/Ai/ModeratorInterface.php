<?php

namespace App\Services\Ai;

/**
 * Fournisseur capable de signaler un texte dangereux ou inapproprié avant
 * qu'il ne soit publié.
 */
interface ModeratorInterface
{
    /**
     * Catégories bloquantes détectées dans le texte (liste vide s'il est sain).
     * Lève une exception si la modération est indisponible.
     *
     * @return list<string>
     */
    public function flaggedCategories(string $text): array;
}
