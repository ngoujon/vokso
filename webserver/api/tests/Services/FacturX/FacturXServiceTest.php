<?php

namespace Tests\Services\FacturX;

use App\Services\FacturX\FacturXService;
use PHPUnit\Framework\TestCase;
use Tiime\FacturX\Reader;

/**
 * Test de bout en bout du pipeline réel (rendu PDF + XML CII + fusion PDF/A-3
 * via Tiime\FacturX\Writer) : produit un vrai fichier Factur-X et vérifie
 * qu'un lecteur indépendant (Tiime\FacturX\Reader, qui revalide le XML
 * contre le XSD officiel) peut en extraire les données. C'est la garantie
 * qu'un logiciel de comptabilité tiers pourra lire les factures émises.
 */
class FacturXServiceTest extends TestCase
{
    private function sampleData(): array
    {
        return [
            'number' => 'VOKSO-2026-000042',
            'issue_date' => new \DateTimeImmutable('2026-09-25'),
            'currency' => 'EUR',
            'seller' => [
                'name' => 'Vokso',
                'siren' => '123456789',
                'vat_number' => '',
                'address_line1' => '1 rue de la Paix',
                'postal_code' => '75002',
                'city' => 'Paris',
                'country_code' => 'FR',
            ],
            'buyer' => [
                'name' => 'Acme SARL',
                'siren' => '987654321',
                'vat_number' => 'FR12987654321',
                'is_business' => true,
                'address_line1' => '2 avenue des Champs',
                'address_line2' => null,
                'postal_code' => '75008',
                'city' => 'Paris',
                'country_code' => 'FR',
            ],
            'line_description' => 'Abonnement Vokso — formule Créateur (septembre 2026)',
            'amount_excl_tax' => '8.25',
            'vat_rate' => '20.00',
            'vat_exempt' => false,
            'vat_exemption_reason' => null,
            'amount_tax' => '1.65',
            'amount_total' => '9.90',
        ];
    }

    public function testGeneratesAWellFormedPdfDocument(): void
    {
        $result = (new FacturXService())->generate($this->sampleData());

        $this->assertStringStartsWith('%PDF-', $result['pdf']);
    }

    public function testEmbeddedXmlCanBeExtractedBackAndIsStillValid(): void
    {
        $result = (new FacturXService())->generate($this->sampleData());

        // validateXsd: true par défaut, revalide contre le XSD officiel BASIC.
        $extractedXml = (new Reader())->extractXML($result['pdf']);

        $this->assertStringContainsString('VOKSO-2026-000042', $extractedXml);
        $this->assertSame($result['xml'], $extractedXml);
    }
}
