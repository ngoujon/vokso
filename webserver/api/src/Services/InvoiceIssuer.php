<?php

namespace App\Services;

use PDO;

/**
 * Enregistre une facture à partir d'un paiement Stripe confirmé (évènement
 * webhook "invoice.paid", voir BillingController::applyEvent). Ne génère pas
 * le PDF/A-3 Factur-X immédiatement : seules les données nécessaires sont
 * figées ici (numéro, montants, copie des coordonnées de facturation), le
 * document est reconstruit à la demande au téléchargement
 * (InvoiceController::download) via FacturXService, à partir de ces mêmes
 * données — inutile de stocker un fichier binaire en plus.
 */
class InvoiceIssuer
{
    private const PLAN_LABELS = [
        'decouverte' => 'Découverte',
        'createur' => 'Créateur',
        'studio' => 'Studio',
    ];

    public function __construct(private PDO $db, private InvoiceNumberGenerator $numberGenerator)
    {
    }

    /**
     * @return string|null Le numéro de facture créé, ou null si le vendeur
     *                      n'est pas encore configuré (voir .env.example).
     */
    public function issueForSubscriptionPayment(
        int $userId,
        string $plan,
        int $amountTotalCents,
        string $currency,
        ?string $stripeInvoiceId
    ): ?string {
        $seller = InvoiceSellerConfig::fromEnv();
        if ($seller === null) {
            return null;
        }

        if ($stripeInvoiceId !== null) {
            $stmt = $this->db->prepare('SELECT number FROM invoices WHERE stripe_invoice_id = :id');
            $stmt->execute([':id' => $stripeInvoiceId]);
            $existing = $stmt->fetchColumn();
            if ($existing !== false) {
                // Déjà facturé (retry webhook Stripe) : pas de doublon.
                return (string) $existing;
            }
        }

        $buyer = $this->buyerSnapshot($userId);

        $vatRate = (float) ($_ENV['INVOICE_VAT_RATE'] ?? '0');
        $vatExempt = $vatRate <= 0.0;
        $vatExemptionReason = $vatExempt
            ? ($_ENV['INVOICE_VAT_EXEMPTION_REASON'] ?? 'TVA non applicable, art. 293 B du CGI')
            : null;

        $amountTotal = $amountTotalCents / 100;
        $amountExclTax = $vatExempt ? $amountTotal : round($amountTotal / (1 + $vatRate / 100), 2);
        $amountTax = round($amountTotal - $amountExclTax, 2);

        $issueDate = new \DateTimeImmutable('now');
        $number = $this->numberGenerator->next((int) $issueDate->format('Y'));
        $currency = strtoupper($currency);

        $stmt = $this->db->prepare(
            'INSERT INTO invoices
                (user_id, number, stripe_invoice_id, issued_at, currency, plan, description,
                 amount_excl_tax, vat_rate, vat_exemption_reason, amount_tax, amount_total, client_snapshot)
             VALUES
                (:user_id, :number, :stripe_invoice_id, :issued_at, :currency, :plan, :description,
                 :amount_excl_tax, :vat_rate, :vat_exemption_reason, :amount_tax, :amount_total, :client_snapshot)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':number' => $number,
            ':stripe_invoice_id' => $stripeInvoiceId,
            ':issued_at' => $issueDate->format('Y-m-d H:i:s'),
            ':currency' => $currency,
            ':plan' => $plan,
            ':description' => $this->lineDescription($plan, $issueDate),
            ':amount_excl_tax' => number_format($amountExclTax, 2, '.', ''),
            ':vat_rate' => number_format($vatExempt ? 0 : $vatRate, 2, '.', ''),
            ':vat_exemption_reason' => $vatExemptionReason,
            ':amount_tax' => number_format($amountTax, 2, '.', ''),
            ':amount_total' => number_format($amountTotal, 2, '.', ''),
            ':client_snapshot' => json_encode($buyer, JSON_THROW_ON_ERROR),
        ]);

        return $number;
    }

    private function lineDescription(string $plan, \DateTimeImmutable $issueDate): string
    {
        $label = self::PLAN_LABELS[$plan] ?? $plan;

        return sprintf('Abonnement Vokso — formule %s (%s)', $label, $this->frenchMonthYear($issueDate));
    }

    private function frenchMonthYear(\DateTimeImmutable $date): string
    {
        $months = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
            7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];

        return $months[(int) $date->format('n')] . ' ' . $date->format('Y');
    }

    /** @return array{name: string, siren: ?string, vat_number: ?string, is_business: bool, address_line1: string, address_line2: ?string, postal_code: string, city: string, country_code: string} */
    private function buyerSnapshot(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT bp.client_type, bp.full_name, bp.company_name, bp.siret, bp.vat_number,
                    bp.address_line1, bp.address_line2, bp.postal_code, bp.city, bp.country_code, u.email
             FROM users u
             LEFT JOIN billing_profiles bp ON bp.user_id = u.id
             WHERE u.id = :user_id'
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $isBusiness = ($row['client_type'] ?? 'particulier') === 'pro';

        return [
            'name' => $isBusiness
                ? (string) ($row['company_name'] ?? '')
                : ((string) ($row['full_name'] ?? '') !== '' ? (string) $row['full_name'] : (string) ($row['email'] ?? '')),
            'siren' => !empty($row['siret']) ? \App\Utils\SiretValidator::siren((string) $row['siret']) : null,
            'vat_number' => $row['vat_number'] ?? null,
            'is_business' => $isBusiness,
            'address_line1' => (string) ($row['address_line1'] ?? ''),
            'address_line2' => $row['address_line2'] ?? null,
            'postal_code' => (string) ($row['postal_code'] ?? ''),
            'city' => (string) ($row['city'] ?? ''),
            'country_code' => strtoupper((string) ($row['country_code'] ?? 'FR')),
        ];
    }

}
