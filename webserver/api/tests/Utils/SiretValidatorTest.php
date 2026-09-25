<?php

namespace Tests\Utils;

use App\Utils\SiretValidator;
use PHPUnit\Framework\TestCase;

class SiretValidatorTest extends TestCase
{
    /** Construit un SIRET valide (clé de Luhn) sans passer par SiretValidator, pour ne pas tester l'algorithme contre lui-même. */
    private function validSiret(string $first13Digits): string
    {
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $digit = (int) $first13Digits[$i];
            if ($i % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }
        $checkDigit = (10 - ($sum % 10)) % 10;

        return $first13Digits . $checkDigit;
    }

    public function testValidSiretPassesChecksum(): void
    {
        $this->assertTrue(SiretValidator::isValid($this->validSiret('7328293200007')));
    }

    public function testAlteredCheckDigitFails(): void
    {
        $siret = $this->validSiret('7328293200007');
        $tamperedCheckDigit = ((int) $siret[13] + 1) % 10;
        $tampered = substr($siret, 0, 13) . $tamperedCheckDigit;

        $this->assertFalse(SiretValidator::isValid($tampered));
    }

    public function testWrongLengthFails(): void
    {
        $this->assertFalse(SiretValidator::isValid('123456789'));
    }

    public function testNonNumericFails(): void
    {
        $this->assertFalse(SiretValidator::isValid('1234567890123A'));
    }

    public function testAcceptsSpacesInInput(): void
    {
        $siret = $this->validSiret('7328293200007');
        $spaced = substr($siret, 0, 3) . ' ' . substr($siret, 3, 3) . ' ' . substr($siret, 6, 5) . ' ' . substr($siret, 11);

        $this->assertTrue(SiretValidator::isValid($spaced));
    }

    public function testSirenExtractsFirstNineDigits(): void
    {
        $siret = $this->validSiret('7328293200007');

        $this->assertSame(substr($siret, 0, 9), SiretValidator::siren($siret));
    }
}
