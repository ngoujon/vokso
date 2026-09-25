<?php

namespace App\Services\FacturX;

/**
 * Construit le XML CII (Cross Industry Invoice) profil BASIC de Factur-X à
 * partir de données simples (tableaux). L'ordre des éléments suit
 * strictement les xs:sequence du schéma officiel embarqué dans
 * vendor/tiime/factur-x/xsd/basic/ : Writer::generate() valide le XML produit
 * contre ce même XSD avant de le fusionner dans le PDF, un ordre incorrect
 * fait donc échouer la génération plutôt que produire une facture invalide.
 *
 * Le profil BASIC (et non MINIMUM) est utilisé car il embarque la ventilation
 * de TVA obligatoire pour qu'une facture soit exploitable comptablement.
 */
class CiiInvoiceXmlBuilder
{
    private const NS_RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';
    private const NS_RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';
    private const NS_UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';
    private const NS_QDT = 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100';

    /** Scheme ID ISO 6523 (ICD) pour un identifiant SIRENE, utilisé pour les organisations françaises. */
    private const SCHEME_ID_SIRENE = '0002';

    private \DOMDocument $document;

    /**
     * @param array{
     *   number: string,
     *   issue_date: \DateTimeImmutable,
     *   currency: string,
     *   seller: array{name: string, siren: string, vat_number: string, address_line1: string, postal_code: string, city: string, country_code: string},
     *   buyer: array{name: string, siren: ?string, vat_number: ?string, is_business: bool, address_line1: string, address_line2: ?string, postal_code: string, city: string, country_code: string},
     *   line_description: string,
     *   amount_excl_tax: string,
     *   vat_rate: string,
     *   vat_exempt: bool,
     *   vat_exemption_reason: ?string,
     *   amount_tax: string,
     *   amount_total: string,
     * } $data
     */
    public function build(array $data): string
    {
        $this->document = new \DOMDocument('1.0', 'UTF-8');

        $root = $this->document->createElementNS(self::NS_RSM, 'rsm:CrossIndustryInvoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ram', self::NS_RAM);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:udt', self::NS_UDT);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:qdt', self::NS_QDT);
        $this->document->appendChild($root);

        $root->appendChild($this->buildDocumentContext());
        $root->appendChild($this->buildExchangedDocument($data));
        $root->appendChild($this->buildSupplyChainTradeTransaction($data));

        return $this->document->saveXML();
    }

    private function buildDocumentContext(): \DOMElement
    {
        // ExchangedDocumentContext est un enfant direct de CrossIndustryInvoice
        // (schéma rsm, elementFormDefault="qualified") : il est donc qualifié
        // rsm:, malgré son type ram:ExchangedDocumentContextType. Seuls ses
        // descendants (GuidelineSpecifiedDocumentContextParameter, ID...) sont
        // ram:, définis dans le schéma ReusableAggregateBusinessInformationEntity.
        $context = $this->rsm('ExchangedDocumentContext');
        $guideline = $this->ram('GuidelineSpecifiedDocumentContextParameter');
        $guideline->appendChild($this->ram('ID', 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic'));
        $context->appendChild($guideline);

        return $context;
    }

    private function buildExchangedDocument(array $data): \DOMElement
    {
        $exchangedDocument = $this->rsm('ExchangedDocument');
        $exchangedDocument->appendChild($this->ram('ID', $data['number']));
        $exchangedDocument->appendChild($this->ram('TypeCode', '380')); // 380 = facture commerciale

        $exchangedDocument->appendChild($this->udtDateTime($data['issue_date']));

        // Mentions légales obligatoires entre professionnels (art. L441-10 du
        // code de commerce) : pénalités de retard et indemnité forfaitaire de
        // recouvrement. Non requises en B2C.
        if ($data['buyer']['is_business']) {
            $note = $this->ram('IncludedNote');
            $note->appendChild($this->ram(
                'Content',
                'Pas d\'escompte pour paiement anticipé. En cas de retard de paiement, application de pénalités '
                . 'au taux d\'intérêt légal majoré de 10 points et d\'une indemnité forfaitaire de recouvrement de 40 €.'
            ));
            $exchangedDocument->appendChild($note);
        }

        return $exchangedDocument;
    }

    private function buildSupplyChainTradeTransaction(array $data): \DOMElement
    {
        $transaction = $this->rsm('SupplyChainTradeTransaction');
        $transaction->appendChild($this->buildLineItem($data));
        $transaction->appendChild($this->buildHeaderTradeAgreement($data));
        $transaction->appendChild($this->buildHeaderTradeDelivery($data));
        $transaction->appendChild($this->buildHeaderTradeSettlement($data));

        return $transaction;
    }

    private function buildLineItem(array $data): \DOMElement
    {
        $line = $this->ram('IncludedSupplyChainTradeLineItem');

        $associatedDocument = $this->ram('AssociatedDocumentLineDocument');
        $associatedDocument->appendChild($this->ram('LineID', '1'));
        $line->appendChild($associatedDocument);

        $product = $this->ram('SpecifiedTradeProduct');
        $product->appendChild($this->ram('Name', $data['line_description']));
        $line->appendChild($product);

        $lineAgreement = $this->ram('SpecifiedLineTradeAgreement');
        $netPrice = $this->ram('NetPriceProductTradePrice');
        $netPrice->appendChild($this->ram('ChargeAmount', $data['amount_excl_tax']));
        $lineAgreement->appendChild($netPrice);
        $line->appendChild($lineAgreement);

        $lineDelivery = $this->ram('SpecifiedLineTradeDelivery');
        $lineDelivery->appendChild($this->ram('BilledQuantity', '1', ['unitCode' => 'C62']));
        $line->appendChild($lineDelivery);

        $lineSettlement = $this->ram('SpecifiedLineTradeSettlement');
        $lineSettlement->appendChild($this->buildTradeTax($data));
        $lineMonetarySummation = $this->ram('SpecifiedTradeSettlementLineMonetarySummation');
        $lineMonetarySummation->appendChild($this->ram('LineTotalAmount', $data['amount_excl_tax']));
        $lineSettlement->appendChild($lineMonetarySummation);
        $line->appendChild($lineSettlement);

        return $line;
    }

    private function buildHeaderTradeAgreement(array $data): \DOMElement
    {
        $agreement = $this->ram('ApplicableHeaderTradeAgreement');
        $agreement->appendChild($this->buildTradeParty($data['seller'], isSeller: true));
        $agreement->appendChild($this->buildTradeParty($data['buyer'], isSeller: false));

        return $agreement;
    }

    private function buildHeaderTradeDelivery(array $data): \DOMElement
    {
        $delivery = $this->ram('ApplicableHeaderTradeDelivery');
        $event = $this->ram('ActualDeliverySupplyChainEvent');
        $event->appendChild($this->udtDateTime($data['issue_date'], 'OccurrenceDateTime'));
        $delivery->appendChild($event);

        return $delivery;
    }

    private function buildHeaderTradeSettlement(array $data): \DOMElement
    {
        $settlement = $this->ram('ApplicableHeaderTradeSettlement');
        $settlement->appendChild($this->ram('InvoiceCurrencyCode', $data['currency']));

        $paymentMeans = $this->ram('SpecifiedTradeSettlementPaymentMeans');
        $paymentMeans->appendChild($this->ram('TypeCode', '48')); // 48 = carte bancaire (Stripe Checkout)
        $settlement->appendChild($paymentMeans);

        $settlement->appendChild($this->buildTradeTax($data));

        $paymentTerms = $this->ram('SpecifiedTradePaymentTerms');
        $paymentTerms->appendChild($this->ram('Description', 'Payé par carte bancaire via Stripe.'));
        $settlement->appendChild($paymentTerms);

        $summation = $this->ram('SpecifiedTradeSettlementHeaderMonetarySummation');
        $summation->appendChild($this->ram('LineTotalAmount', $data['amount_excl_tax']));
        $summation->appendChild($this->ram('TaxBasisTotalAmount', $data['amount_excl_tax']));
        $summation->appendChild($this->ram('TaxTotalAmount', $data['amount_tax'], ['currencyID' => $data['currency']]));
        $summation->appendChild($this->ram('GrandTotalAmount', $data['amount_total']));
        $summation->appendChild($this->ram('DuePayableAmount', $data['amount_total']));
        $settlement->appendChild($summation);

        return $settlement;
    }

    private function buildTradeTax(array $data): \DOMElement
    {
        $tax = $this->ram('ApplicableTradeTax');
        $tax->appendChild($this->ram('CalculatedAmount', $data['amount_tax']));
        $tax->appendChild($this->ram('TypeCode', 'VAT'));
        if ($data['vat_exempt'] && $data['vat_exemption_reason']) {
            $tax->appendChild($this->ram('ExemptionReason', $data['vat_exemption_reason']));
        }
        $tax->appendChild($this->ram('BasisAmount', $data['amount_excl_tax']));
        $tax->appendChild($this->ram('CategoryCode', $data['vat_exempt'] ? 'E' : 'S'));
        $tax->appendChild($this->ram('RateApplicablePercent', $data['vat_rate']));

        return $tax;
    }

    private function buildTradeParty(array $party, bool $isSeller): \DOMElement
    {
        $tradeParty = $this->ram($isSeller ? 'SellerTradeParty' : 'BuyerTradeParty');
        $tradeParty->appendChild($this->ram('Name', $party['name']));

        if (!empty($party['siren'])) {
            $legalOrganization = $this->ram('SpecifiedLegalOrganization');
            $legalOrganization->appendChild($this->ram('ID', $party['siren'], ['schemeID' => self::SCHEME_ID_SIRENE]));
            $tradeParty->appendChild($legalOrganization);
        }

        $address = $this->ram('PostalTradeAddress');
        $address->appendChild($this->ram('PostcodeCode', $party['postal_code']));
        $address->appendChild($this->ram('LineOne', $party['address_line1']));
        if (!empty($party['address_line2'])) {
            $address->appendChild($this->ram('LineTwo', $party['address_line2']));
        }
        $address->appendChild($this->ram('CityName', $party['city']));
        $address->appendChild($this->ram('CountryID', $party['country_code']));
        $tradeParty->appendChild($address);

        if (!empty($party['vat_number'])) {
            $taxRegistration = $this->ram('SpecifiedTaxRegistration');
            $taxRegistration->appendChild($this->ram('ID', $party['vat_number'], ['schemeID' => 'VA']));
            $tradeParty->appendChild($taxRegistration);
        }

        return $tradeParty;
    }

    private function udtDateTime(\DateTimeImmutable $date, string $elementName = 'IssueDateTime'): \DOMElement
    {
        $wrapper = $this->ram($elementName);
        $dateTimeString = $this->document->createElementNS(self::NS_UDT, 'udt:DateTimeString', $date->format('Ymd'));
        $dateTimeString->setAttribute('format', '102');
        $wrapper->appendChild($dateTimeString);

        return $wrapper;
    }

    private function ram(string $name, ?string $value = null, array $attributes = []): \DOMElement
    {
        $element = $value !== null
            ? $this->document->createElementNS(self::NS_RAM, 'ram:' . $name, htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'))
            : $this->document->createElementNS(self::NS_RAM, 'ram:' . $name);
        foreach ($attributes as $attribute => $attributeValue) {
            $element->setAttribute($attribute, $attributeValue);
        }

        return $element;
    }

    private function rsm(string $name): \DOMElement
    {
        return $this->document->createElementNS(self::NS_RSM, 'rsm:' . $name);
    }
}
