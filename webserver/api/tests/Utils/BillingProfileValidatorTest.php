<?php

namespace Tests\Utils;

use App\Utils\BillingProfileValidator;
use PHPUnit\Framework\TestCase;

class BillingProfileValidatorTest extends TestCase
{
    private function baseInput(array $overrides = []): array
    {
        return array_merge([
            'client_type' => 'particulier',
            'full_name' => 'Jean Dupont',
            'address_line1' => '1 rue de Paris',
            'postal_code' => '75001',
            'city' => 'Paris',
            'country_code' => 'FR',
        ], $overrides);
    }

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

    public function testValidParticulierPasses(): void
    {
        $this->assertNull(BillingProfileValidator::validate($this->baseInput()));
    }

    public function testUnknownClientTypeFails(): void
    {
        $this->assertNotNull(BillingProfileValidator::validate($this->baseInput(['client_type' => 'entreprise'])));
    }

    public function testMissingFullNameFails(): void
    {
        $this->assertNotNull(BillingProfileValidator::validate($this->baseInput(['full_name' => ''])));
    }

    public function testMissingAddressFails(): void
    {
        $this->assertNotNull(BillingProfileValidator::validate($this->baseInput(['address_line1' => ''])));
    }

    public function testProWithoutCompanyNameFails(): void
    {
        $siret = $this->validSiret('7328293200007');
        $input = $this->baseInput(['client_type' => 'pro', 'siret' => $siret]);

        $this->assertNotNull(BillingProfileValidator::validate($input));
    }

    public function testProWithoutSiretFails(): void
    {
        $input = $this->baseInput(['client_type' => 'pro', 'company_name' => 'Acme']);

        $this->assertNotNull(BillingProfileValidator::validate($input));
    }

    public function testProWithMalformedSiretFails(): void
    {
        $input = $this->baseInput(['client_type' => 'pro', 'company_name' => 'Acme', 'siret' => '123']);

        $this->assertNotNull(BillingProfileValidator::validate($input));
    }

    public function testProWithValidSiretAndNoVatNumberPasses(): void
    {
        $siret = $this->validSiret('7328293200007');
        $input = $this->baseInput(['client_type' => 'pro', 'company_name' => 'Acme', 'siret' => $siret]);

        $this->assertNull(BillingProfileValidator::validate($input));
    }

    public function testProWithInvalidVatNumberFails(): void
    {
        $siret = $this->validSiret('7328293200007');
        $input = $this->baseInput([
            'client_type' => 'pro',
            'company_name' => 'Acme',
            'siret' => $siret,
            'vat_number' => 'INVALID',
        ]);

        $this->assertNotNull(BillingProfileValidator::validate($input));
    }
}
