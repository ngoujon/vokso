<?php

namespace App\Services\FacturX;

/**
 * Identité légale du vendeur à faire figurer sur les factures, lue depuis la
 * configuration plutôt que codée en dur : inventer une adresse/SIREN
 * produirait des factures non conformes. Plus aucune facture n'est émise
 * (service gratuit) : sert uniquement à régénérer les factures déjà émises,
 * conservées 10 ans (art. L123-22 du code de commerce).
 */
class InvoiceSellerConfig
{
    /** @return array{name: string, siren: string, vat_number: string, address_line1: string, postal_code: string, city: string, country_code: string}|null */
    public static function fromConfig(): ?array
    {
        $seller = config('vokso.invoice_seller');

        foreach (['name', 'address_line1', 'postal_code', 'city', 'siren'] as $required) {
            if (($seller[$required] ?? '') === '') {
                return null;
            }
        }

        return $seller;
    }
}
