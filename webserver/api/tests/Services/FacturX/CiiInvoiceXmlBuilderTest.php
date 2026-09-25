<?php

namespace Tests\Services\FacturX;

use App\Services\FacturX\CiiInvoiceXmlBuilder;
use PHPUnit\Framework\TestCase;
use Tiime\FacturX\Profile;
use Tiime\FacturX\XsdProcessor;

/**
 * Valide le XML produit contre le XSD officiel du profil BASIC de Factur-X
 * (embarqué dans vendor/tiime/factur-x/xsd/basic/) : c'est la même
 * validation que Tiime\FacturX\Writer applique avant de fusionner le XML
 * dans le PDF, donc le test réel de conformité du format.
 */
class CiiInvoiceXmlBuilderTest extends TestCase
{
    private function sampleData(bool $isBusiness, bool $vatExempt): array
    {
        return [
            'number' => 'VOKSO-2026-000001',
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
                'name' => $isBusiness ? 'Acme SARL' : 'Jean Dupont',
                'siren' => $isBusiness ? '987654321' : null,
                'vat_number' => $isBusiness ? 'FR12987654321' : null,
                'is_business' => $isBusiness,
                'address_line1' => '2 avenue des Champs',
                'address_line2' => null,
                'postal_code' => '75008',
                'city' => 'Paris',
                'country_code' => 'FR',
            ],
            'line_description' => 'Abonnement Vokso — formule Créateur (septembre 2026)',
            'amount_excl_tax' => $vatExempt ? '9.90' : '8.25',
            'vat_rate' => $vatExempt ? '0.00' : '20.00',
            'vat_exempt' => $vatExempt,
            'vat_exemption_reason' => $vatExempt ? 'TVA non applicable, art. 293 B du CGI' : null,
            'amount_tax' => $vatExempt ? '0.00' : '1.65',
            'amount_total' => '9.90',
        ];
    }

    public function testGeneratedXmlIsValidForAParticulierExemptOfVat(): void
    {
        $xml = (new CiiInvoiceXmlBuilder())->build($this->sampleData(isBusiness: false, vatExempt: true));

        $this->assertTrue((new XsdProcessor(Profile::BASIC))->validate($xml));
    }

    public function testGeneratedXmlIsValidForAProWithVat(): void
    {
        $xml = (new CiiInvoiceXmlBuilder())->build($this->sampleData(isBusiness: true, vatExempt: false));

        $this->assertTrue((new XsdProcessor(Profile::BASIC))->validate($xml));
    }

    public function testXmlDeclaresTheBasicProfileGuideline(): void
    {
        $xml = (new CiiInvoiceXmlBuilder())->build($this->sampleData(isBusiness: false, vatExempt: true));

        $this->assertStringContainsString(
            'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic',
            $xml
        );
    }

    public function testXmlContainsInvoiceNumberAndTotals(): void
    {
        $xml = (new CiiInvoiceXmlBuilder())->build($this->sampleData(isBusiness: false, vatExempt: true));

        $document = new \DOMDocument();
        $document->loadXML($xml);
        $xpath = new \DOMXPath($document);

        $this->assertSame(
            'VOKSO-2026-000001',
            $xpath->query('//rsm:ExchangedDocument/ram:ID')->item(0)?->nodeValue
        );
        $this->assertSame(
            '9.90',
            $xpath->query('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount')->item(0)?->nodeValue
        );
    }

    public function testB2bInvoiceIncludesThePenaltyMentionRequiredByFrenchLaw(): void
    {
        $xml = (new CiiInvoiceXmlBuilder())->build($this->sampleData(isBusiness: true, vatExempt: false));

        $this->assertStringContainsString('indemnité forfaitaire de recouvrement', $xml);
    }

    public function testB2cInvoiceDoesNotIncludeThePenaltyMention(): void
    {
        $xml = (new CiiInvoiceXmlBuilder())->build($this->sampleData(isBusiness: false, vatExempt: true));

        $this->assertStringNotContainsString('indemnité forfaitaire de recouvrement', $xml);
    }
}
