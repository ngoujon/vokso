<?php

namespace Tests\Unit;

use App\Support\AiQuota;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AiQuotaTest extends TestCase
{
    public static function exhaustedMessages(): array
    {
        return [
            ['Appel API en échec (/v1/chat/completions) : Client error: `POST https://api.mistral.ai/v1/chat/completions` resulted in a `402 Payment Required` response'],
            ['Échec de la génération d\'image Mistral : Client error: `POST https://api.mistral.ai/v1/conversations` resulted in a `429 Too Many Requests` response'],
            ['Erreur API : Requests rate limit exceeded'],
            ['Erreur API : Insufficient credit balance'],
        ];
    }

    #[DataProvider('exhaustedMessages')]
    public function test_credit_and_limit_errors_are_recognised(string $message): void
    {
        $this->assertTrue(AiQuota::isExhausted(new Exception($message)));
    }

    public function test_the_cause_is_looked_up_in_previous_exceptions(): void
    {
        $this->assertTrue(AiQuota::isExhausted(new Exception('Erreur lors de la génération du texte', 0, new Exception('402 Payment Required'))));
    }

    public function test_other_failures_are_not_mistaken_for_a_quota(): void
    {
        foreach ([
            'Appel API en échec (/v1/audio/speech) : Server error: `POST https://api.mistral.ai/v1/audio/speech` resulted in a `500 Internal Server Error` response',
            'Appel API en échec (/v1/chat/completions) : Client error: resulted in a `401 Unauthorized` response',
            'cURL error 28: Operation timed out after 180000 milliseconds',
            'Réponse Mistral inattendue : aucune image (tool_file) trouvée dans la conversation.',
        ] as $message) {
            $this->assertFalse(AiQuota::isExhausted(new Exception($message)), $message);
        }
    }
}
