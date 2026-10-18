<?php

namespace App\Support;

use Throwable;

/**
 * Reconnaît un échec dû au crédit ou aux limites du compte Mistral (402
 * Payment Required, 429 Too Many Requests, solde épuisé) plutôt qu'à une
 * vraie panne : le visiteur n'y peut rien et le service reviendra seul, une
 * fois le crédit rechargé ou la limite levée.
 *
 * Les fournisseurs (ApiClient, agents Mistral) recopient le message Guzzle
 * (« resulted in a `402 Payment Required` response ») et le corps de la
 * réponse dans leurs exceptions : c'est ce texte qui est examiné.
 */
final class AiQuota
{
    private const PATTERN = '/`(402|429) |payment required|rate limit exceeded|insufficient (credit|fund|balance)|out of credits?|exceeded your/i';

    public static function isExhausted(Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if (preg_match(self::PATTERN, $current->getMessage()) === 1) {
                return true;
            }
        }

        return false;
    }
}
