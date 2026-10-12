<?php

namespace App\Exceptions;

use Exception;

/**
 * Génération arrêtée par les protections (voir PromptGuard et la modération) :
 * le message est montré tel quel au visiteur, la raison technique ne va
 * qu'aux logs.
 */
class BlockedContentException extends Exception
{
    public function __construct(string $userMessage, public readonly string $reason)
    {
        parent::__construct($userMessage);
    }
}
