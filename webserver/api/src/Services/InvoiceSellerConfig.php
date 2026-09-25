<?php

namespace App\Services;

/**
 * Identité légale du vendeur (Vokso) à faire figurer sur les factures,
 * lue depuis l'environnement plutôt que codée en dur : inventer une fausse
 * adresse/SIREN produirait des factures non conformes. Tant que ces
 * variables ne sont pas renseignées (voir .env.example), aucune facture
 * n'est émise ni régénérée (voir InvoiceIssuer et InvoiceController).
 */
class InvoiceSellerConfig
{
    /** @return array{name: string, siren: string, vat_number: string, address_line1: string, postal_code: string, city: string, country_code: string}|null */
    public static function fromEnv(): ?array
    {
        $name = trim((string) ($_ENV['INVOICE_SELLER_NAME'] ?? ''));
        $address = trim((string) ($_ENV['INVOICE_SELLER_ADDRESS'] ?? ''));
        $postalCode = trim((string) ($_ENV['INVOICE_SELLER_POSTAL_CODE'] ?? ''));
        $city = trim((string) ($_ENV['INVOICE_SELLER_CITY'] ?? ''));
        $siren = trim((string) ($_ENV['INVOICE_SELLER_SIREN'] ?? ''));

        if ($name === '' || $address === '' || $postalCode === '' || $city === '' || $siren === '') {
            return null;
        }

        return [
            'name' => $name,
            'siren' => $siren,
            'vat_number' => trim((string) ($_ENV['INVOICE_SELLER_VAT_NUMBER'] ?? '')),
            'address_line1' => $address,
            'postal_code' => $postalCode,
            'city' => $city,
            'country_code' => strtoupper((string) ($_ENV['INVOICE_SELLER_COUNTRY'] ?? 'FR')),
        ];
    }
}
