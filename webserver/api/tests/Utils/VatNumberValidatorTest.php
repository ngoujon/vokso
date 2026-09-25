<?php

namespace Tests\Utils;

use App\Utils\VatNumberValidator;
use PHPUnit\Framework\TestCase;

class VatNumberValidatorTest extends TestCase
{
    /** Numéro de TVA FR valide pour un SIREN donné, calculé indépendamment de VatNumberValidator. */
    private function frenchVatFor(string $siren): string
    {
        $key = (12 + 3 * ((int) $siren % 97)) % 97;

        return sprintf('FR%02d%s', $key, $siren);
    }

    public function testValidFrenchVatNumberPasses(): void
    {
        $this->assertTrue(VatNumberValidator::isValid($this->frenchVatFor('732829320')));
    }

    public function testFrenchVatWithWrongKeyFails(): void
    {
        $vat = $this->frenchVatFor('732829320');
        $wrongKey = (((int) substr($vat, 2, 2)) + 1) % 97;
        $tampered = sprintf('FR%02d%s', $wrongKey, substr($vat, 4));

        $this->assertFalse(VatNumberValidator::isValid($tampered));
    }

    public function testAcceptsSpacesAndLowercase(): void
    {
        $vat = $this->frenchVatFor('732829320');
        $spaced = 'fr ' . substr($vat, 2, 2) . ' ' . substr($vat, 4);

        $this->assertTrue(VatNumberValidator::isValid($spaced));
    }

    public function testOtherEuCountryFormatIsAcceptedWithoutChecksum(): void
    {
        $this->assertTrue(VatNumberValidator::isValid('DE123456789'));
    }

    public function testInvalidFormatFails(): void
    {
        $this->assertFalse(VatNumberValidator::isValid('NOTAVATNUMBER'));
        $this->assertFalse(VatNumberValidator::isValid('F123456789'));
        $this->assertFalse(VatNumberValidator::isValid(''));
    }
}
