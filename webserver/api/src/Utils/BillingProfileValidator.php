<?php

namespace App\Utils;

/**
 * Valide les données du formulaire de profil de facturation. Le numéro de
 * TVA intracommunautaire reste facultatif même pour un compte "pro" : les
 * micro-entreprises en franchise en base de TVA (art. 293 B du CGI) n'en ont
 * pas. Le SIRET, en revanche, est une obligation légale pour toute facture
 * émise à destination d'une entreprise française.
 */
class BillingProfileValidator
{
    public static function validate(array $input): ?string
    {
        $clientType = $input['client_type'] ?? '';
        if (!in_array($clientType, ['particulier', 'pro'], true)) {
            return 'Type de client invalide.';
        }

        if (trim((string) ($input['full_name'] ?? '')) === '') {
            return 'Le nom complet est requis.';
        }
        if (trim((string) ($input['address_line1'] ?? '')) === '') {
            return 'L\'adresse est requise.';
        }
        if (trim((string) ($input['postal_code'] ?? '')) === '') {
            return 'Le code postal est requis.';
        }
        if (trim((string) ($input['city'] ?? '')) === '') {
            return 'La ville est requise.';
        }
        if (!preg_match('/^[A-Za-z]{2}$/', (string) ($input['country_code'] ?? ''))) {
            return 'Le pays est requis (code ISO à 2 lettres).';
        }

        if ($clientType === 'pro') {
            if (trim((string) ($input['company_name'] ?? '')) === '') {
                return 'La raison sociale est requise pour un compte professionnel.';
            }

            $siret = trim((string) ($input['siret'] ?? ''));
            if ($siret === '' || !SiretValidator::isValid($siret)) {
                return 'Le numéro de SIRET est requis et doit être valide pour un compte professionnel.';
            }

            $vatNumber = trim((string) ($input['vat_number'] ?? ''));
            if ($vatNumber !== '' && !VatNumberValidator::isValid($vatNumber)) {
                return 'Le numéro de TVA intracommunautaire est invalide.';
            }
        }

        return null;
    }
}
