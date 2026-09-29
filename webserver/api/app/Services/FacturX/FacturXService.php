<?php

namespace App\Services\FacturX;

use Tiime\FacturX\Writer;

/**
 * Assemble le XML CII (CiiInvoiceXmlBuilder) et le rendu visuel
 * (InvoicePdfRenderer) d'une facture en un unique PDF/A-3 Factur-X, via
 * Tiime\FacturX\Writer qui valide le XML contre le XSD officiel du profil
 * BASIC avant de le fusionner (voir Writer::generate, validateXsd: true par
 * défaut) — la génération échoue plutôt que produire une facture non conforme.
 */
class FacturXService
{
    private CiiInvoiceXmlBuilder $xmlBuilder;
    private InvoicePdfRenderer $pdfRenderer;
    private Writer $writer;

    public function __construct(
        ?CiiInvoiceXmlBuilder $xmlBuilder = null,
        ?InvoicePdfRenderer $pdfRenderer = null,
        ?Writer $writer = null,
    ) {
        $this->xmlBuilder = $xmlBuilder ?? new CiiInvoiceXmlBuilder();
        $this->pdfRenderer = $pdfRenderer ?? new InvoicePdfRenderer();
        $this->writer = $writer ?? new Writer();
    }

    /** @return array{pdf: string, xml: string} */
    public function generate(array $data): array
    {
        $xml = $this->xmlBuilder->build($data);
        $pdf = $this->pdfRenderer->render($data);
        $facturX = $this->writer->generate(pdfContent: $pdf, xmlContent: $xml, addLogo: true);

        return ['pdf' => $facturX, 'xml' => $xml];
    }
}
