<?php

namespace App\Services\FacturX;

use FPDF;

/**
 * Rendu visuel (lisible par un humain) de la facture, en PDF simple : c'est
 * ce contenu que Tiime\FacturX\Writer fusionne avec le XML structuré pour
 * produire le PDF/A-3 final. Les données affichées ici doivent rester
 * cohérentes avec celles du XML (CiiInvoiceXmlBuilder), les deux étant
 * construites à partir du même tableau par FacturXService.
 */
class InvoicePdfRenderer
{
    public function render(array $data): string
    {
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);

        $this->renderHeader($pdf, $data);
        $this->renderParties($pdf, $data);
        $this->renderLineItem($pdf, $data);
        $this->renderTotals($pdf, $data);
        $this->renderFooter($pdf, $data);

        return $pdf->Output('S');
    }

    private function renderHeader(FPDF $pdf, array $data): void
    {
        $seller = $data['seller'];

        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->Cell(0, 8, $this->clean($seller['name']), 0, 1);

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 5, $this->clean($seller['address_line1']), 0, 1);
        $pdf->Cell(0, 5, $this->clean($seller['postal_code'] . ' ' . $seller['city']), 0, 1);
        if (!empty($seller['siren'])) {
            $pdf->Cell(0, 5, 'SIREN : ' . $this->clean($seller['siren']), 0, 1);
        }
        if (!empty($seller['vat_number'])) {
            $pdf->Cell(0, 5, 'N° TVA intracommunautaire : ' . $this->clean($seller['vat_number']), 0, 1);
        }

        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'Facture ' . $this->clean($data['number']), 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 5, 'Date d\'émission : ' . $data['issue_date']->format('d/m/Y'), 0, 1);
        $pdf->Ln(4);
    }

    private function renderParties(FPDF $pdf, array $data): void
    {
        $buyer = $data['buyer'];

        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 5, 'Facturé à :', 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 5, $this->clean($buyer['name']), 0, 1);
        $pdf->Cell(0, 5, $this->clean($buyer['address_line1']), 0, 1);
        if (!empty($buyer['address_line2'])) {
            $pdf->Cell(0, 5, $this->clean($buyer['address_line2']), 0, 1);
        }
        $pdf->Cell(0, 5, $this->clean($buyer['postal_code'] . ' ' . $buyer['city']), 0, 1);
        if (!empty($buyer['siren'])) {
            $pdf->Cell(0, 5, 'SIREN : ' . $this->clean($buyer['siren']), 0, 1);
        }
        if (!empty($buyer['vat_number'])) {
            $pdf->Cell(0, 5, 'N° TVA intracommunautaire : ' . $this->clean($buyer['vat_number']), 0, 1);
        }
        $pdf->Ln(6);
    }

    private function renderLineItem(FPDF $pdf, array $data): void
    {
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(120, 7, 'Désignation', 1, 0, 'L', true);
        $pdf->Cell(30, 7, 'Montant HT', 1, 0, 'R', true);
        $pdf->Cell(20, 7, 'TVA', 1, 1, 'R', true);

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(120, 7, $this->clean($data['line_description']), 1);
        $pdf->Cell(30, 7, $this->formatAmount($data['amount_excl_tax'], $data['currency']), 1, 0, 'R');
        $pdf->Cell(20, 7, $data['vat_exempt'] ? 'N/A' : $data['vat_rate'] . ' %', 1, 1, 'R');
        $pdf->Ln(4);
    }

    private function renderTotals(FPDF $pdf, array $data): void
    {
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(150, 6, 'Total HT', 0, 0, 'R');
        $pdf->Cell(30, 6, $this->formatAmount($data['amount_excl_tax'], $data['currency']), 0, 1, 'R');

        $pdf->Cell(150, 6, 'TVA', 0, 0, 'R');
        $pdf->Cell(30, 6, $this->formatAmount($data['amount_tax'], $data['currency']), 0, 1, 'R');

        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(150, 7, 'Total TTC', 0, 0, 'R');
        $pdf->Cell(30, 7, $this->formatAmount($data['amount_total'], $data['currency']), 0, 1, 'R');
        $pdf->Ln(4);

        if ($data['vat_exempt'] && $data['vat_exemption_reason']) {
            $pdf->SetFont('Helvetica', 'I', 9);
            $pdf->MultiCell(0, 5, $this->clean($data['vat_exemption_reason']));
        }
    }

    private function renderFooter(FPDF $pdf, array $data): void
    {
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->MultiCell(0, 4, $this->clean(
            'Payé par carte bancaire via Stripe. Facture émise électroniquement au format Factur-X (PDF/A-3 + XML CII, profil BASIC).'
        ));

        if ($data['buyer']['is_business']) {
            $pdf->Ln(2);
            $pdf->MultiCell(0, 4, $this->clean(
                'Pas d\'escompte pour paiement anticipé. En cas de retard de paiement, application de pénalités '
                . 'au taux d\'intérêt légal majoré de 10 points et d\'une indemnité forfaitaire de recouvrement de 40 €.'
            ));
        }
    }

    private function formatAmount(string $amount, string $currency): string
    {
        $symbol = $currency === 'EUR' ? 'EUR' : $currency;

        return number_format((float) $amount, 2, ',', ' ') . ' ' . $symbol;
    }

    /** FPDF (Helvetica core font) n'est pas en UTF-8 : on retombe en ASCII pour éviter les caractères corrompus. */
    private function clean(string $text): string
    {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);

        return $converted !== false ? $converted : $text;
    }
}
