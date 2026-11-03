<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;

class AccountingEInvoiceParserService
{
    /**
     * Liest eine E-Rechnung aus einer Datei (.xml oder ZUGFeRD-.pdf) ein.
     */
    public function parseFile(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException('Die angegebene Datei konnte nicht gelesen werden.');
        }

        $raw = (string)file_get_contents($filePath);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'pdf' || str_starts_with($raw, '%PDF-')) {
            $extractedXml = $this->extractXmlFromPdf($raw);
            if ($extractedXml === null) {
                throw new InvalidArgumentException('In dieser PDF-Datei wurde kein eingebettetes ZUGFeRD / Factur-X XML gefunden.');
            }
            return $this->parseXmlString($extractedXml);
        }

        return $this->parseXmlString($raw);
    }

    /**
     * Parst einen XML-String (UN/CEFACT CII oder UBL 2.1) deterministisch und sicher (XXE-geschützt).
     */
    public function parseXmlString(string $xmlContent): array
    {
        $xmlContent = trim($xmlContent);
        // UTF-8 BOM entfernen falls vorhanden
        if (str_starts_with($xmlContent, "\xEF\xBB\xBF")) {
            $xmlContent = substr($xmlContent, 3);
        }

        if ($xmlContent === '') {
            throw new InvalidArgumentException('Die XML-Datei ist leer.');
        }

        // XXE-Schutz: Keine DOCTYPE oder ENTITY Deklarationen erlauben
        if (preg_match('/<!\s*(DOCTYPE|ENTITY)\b/i', $xmlContent)) {
            throw new InvalidArgumentException('Sicherheitsverstoß: XML-Dokumente mit DOCTYPE/ENTITY (XXE) werden abgelehnt.');
        }

        $dom = new DOMDocument();
        $prevUseErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xmlContent, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($prevUseErrors);

        if (!$loaded || !$dom->documentElement) {
            throw new InvalidArgumentException('Ungültiges XML-Format: Die E-Rechnung konnte nicht geparst werden.');
        }

        $rootLocalName = $dom->documentElement->localName ?: $dom->documentElement->nodeName;

        if (stripos($rootLocalName, 'CrossIndustryInvoice') !== false) {
            $parsed = $this->parseCii($dom);
        } elseif (stripos($rootLocalName, 'Invoice') !== false || stripos($rootLocalName, 'CreditNote') !== false) {
            $parsed = $this->parseUbl($dom, stripos($rootLocalName, 'CreditNote') !== false);
        } else {
            throw new InvalidArgumentException("Nicht unterstütztes E-Rechnungs-Root-Element: {$rootLocalName}. Unterstützt werden UN/CEFACT CII (ZUGFeRD/XRechnung) und UBL 2.1.");
        }

        $parsed['validation_checks'] = $this->runValidationChecks($parsed);
        $parsed['is_fully_compliant'] = collect($parsed['validation_checks'])->every(fn ($c) => $c['passed'] === true);

        return $parsed;
    }

    /**
     * Parst UN/CEFACT CrossIndustryInvoice (ZUGFeRD 2.x / Factur-X / XRechnung CII).
     */
    private function parseCii(DOMDocument $dom): array
    {
        $xpath = new DOMXPath($dom);

        $profile = $this->xpVal($xpath, '//*[local-name()="ExchangedDocumentContext"]/*[local-name()="GuidelineSpecifiedDocumentContextParameter"]/*[local-name()="ID"]');
        $businessProcess = $this->xpVal($xpath, '//*[local-name()="ExchangedDocumentContext"]/*[local-name()="BusinessProcessSpecifiedDocumentContextParameter"]/*[local-name()="ID"]');

        $invoiceNumber = $this->xpVal($xpath, '//*[local-name()="ExchangedDocument"]/*[local-name()="ID"]');
        $typeCode = $this->xpVal($xpath, '//*[local-name()="ExchangedDocument"]/*[local-name()="TypeCode"]', '380');
        $rawIssueDate = $this->xpVal($xpath, '//*[local-name()="ExchangedDocument"]/*[local-name()="IssueDateTime"]/*[local-name()="DateTimeString"]');
        $issueDate = $this->normalizeDate($rawIssueDate);

        $notes = [];
        foreach ($xpath->query('//*[local-name()="ExchangedDocument"]/*[local-name()="IncludedNote"]/*[local-name()="Content"]') as $n) {
            $val = trim($n->textContent);
            if ($val !== '') {
                $notes[] = $val;
            }
        }

        $buyerReference = $this->xpVal($xpath, '//*[local-name()="ApplicableHeaderTradeAgreement"]/*[local-name()="BuyerReference"]');

        // Verkäufer (SellerTradeParty)
        $sellerName = $this->xpVal($xpath, '//*[local-name()="SellerTradeParty"]/*[local-name()="Name"]');
        $sellerContactName = $this->xpVal($xpath, '//*[local-name()="SellerTradeParty"]/*[local-name()="DefinedTradeContact"]/*[local-name()="PersonName"]');
        $sellerPhone = $this->xpVal($xpath, '//*[local-name()="SellerTradeParty"]/*[local-name()="DefinedTradeContact"]/*[local-name()="TelephoneUniversalCommunication"]/*[local-name()="CompleteNumber"]');
        $sellerEmail = $this->xpVal($xpath, '//*[local-name()="SellerTradeParty"]/*[local-name()="URIUniversalCommunication"]/*[local-name()="URIID"]')
            ?: $this->xpVal($xpath, '//*[local-name()="SellerTradeParty"]/*[local-name()="DefinedTradeContact"]/*[local-name()="EmailURIUniversalCommunication"]/*[local-name()="URIID"]');
        $sellerZip = $this->xpVal($xpath, '//*[local-name()="SellerTradeParty"]/*[local-name()="PostalTradeAddress"]/*[local-name()="PostcodeCode"]');
        $sellerStreet = $this->xpVal($xpath, '//*[local-name()="SellerTradeParty"]/*[local-name()="PostalTradeAddress"]/*[local-name()="LineOne"]');
        $sellerCity = $this->xpVal($xpath, '//*[local-name()="SellerTradeParty"]/*[local-name()="PostalTradeAddress"]/*[local-name()="CityName"]');
        $sellerCountry = $this->xpVal($xpath, '//*[local-name()="SellerTradeParty"]/*[local-name()="PostalTradeAddress"]/*[local-name()="CountryID"]', 'DE');

        $sellerVatId = '';
        $sellerTaxId = '';
        foreach ($xpath->query('//*[local-name()="SellerTradeParty"]/*[local-name()="SpecifiedTaxRegistration"]/*[local-name()="ID"]') as $taxNode) {
            $scheme = strtoupper((string)$taxNode->attributes?->getNamedItem('schemeID')?->nodeValue);
            if ($scheme === 'VA') {
                $sellerVatId = trim($taxNode->textContent);
            } elseif ($scheme === 'FC') {
                $sellerTaxId = trim($taxNode->textContent);
            } elseif ($sellerVatId === '') {
                $sellerVatId = trim($taxNode->textContent);
            }
        }

        // Käufer (BuyerTradeParty)
        $buyerName = $this->xpVal($xpath, '//*[local-name()="BuyerTradeParty"]/*[local-name()="Name"]');
        $buyerZip = $this->xpVal($xpath, '//*[local-name()="BuyerTradeParty"]/*[local-name()="PostalTradeAddress"]/*[local-name()="PostcodeCode"]');
        $buyerStreet = $this->xpVal($xpath, '//*[local-name()="BuyerTradeParty"]/*[local-name()="PostalTradeAddress"]/*[local-name()="LineOne"]');
        $buyerCity = $this->xpVal($xpath, '//*[local-name()="BuyerTradeParty"]/*[local-name()="PostalTradeAddress"]/*[local-name()="CityName"]');
        $buyerCountry = $this->xpVal($xpath, '//*[local-name()="BuyerTradeParty"]/*[local-name()="PostalTradeAddress"]/*[local-name()="CountryID"]', 'DE');
        $buyerEmail = $this->xpVal($xpath, '//*[local-name()="BuyerTradeParty"]/*[local-name()="URIUniversalCommunication"]/*[local-name()="URIID"]');

        // Lieferdatum
        $rawDeliveryDate = $this->xpVal($xpath, '//*[local-name()="ApplicableHeaderTradeDelivery"]/*[local-name()="ActualDeliverySupplyChainEvent"]/*[local-name()="OccurrenceDateTime"]/*[local-name()="DateTimeString"]');
        $deliveryDate = $this->normalizeDate($rawDeliveryDate) ?: $issueDate;

        // Zahlungsdaten & Settlement
        $currency = $this->xpVal($xpath, '//*[local-name()="ApplicableHeaderTradeSettlement"]/*[local-name()="InvoiceCurrencyCode"]', 'EUR');
        $iban = $this->xpVal($xpath, '//*[local-name()="ApplicableHeaderTradeSettlement"]//*[local-name()="PayeePartyCreditorFinancialAccount"]/*[local-name()="IBANID"]');
        $bic = $this->xpVal($xpath, '//*[local-name()="ApplicableHeaderTradeSettlement"]//*[local-name()="PayeeSpecifiedCreditorFinancialInstitution"]/*[local-name()="BICID"]');
        $paymentTerms = $this->xpVal($xpath, '//*[local-name()="ApplicableHeaderTradeSettlement"]/*[local-name()="SpecifiedTradePaymentTerms"]/*[local-name()="Description"]');
        $rawDueDate = $this->xpVal($xpath, '//*[local-name()="ApplicableHeaderTradeSettlement"]/*[local-name()="SpecifiedTradePaymentTerms"]/*[local-name()="DueDateDateTime"]/*[local-name()="DateTimeString"]');
        $dueDate = $this->normalizeDate($rawDueDate);

        // Hauptsteuersatz
        $mainTaxRateStr = $this->xpVal($xpath, '//*[local-name()="ApplicableHeaderTradeSettlement"]/*[local-name()="ApplicableTradeTax"]/*[local-name()="RateApplicablePercent"]', '0');
        $mainTaxRate = (float)$mainTaxRateStr;

        // Dokumenten-Zu-/Abschläge (SpecifiedTradeAllowanceCharge)
        $allowancesCharges = [];
        foreach ($xpath->query('//*[local-name()="ApplicableHeaderTradeSettlement"]/*[local-name()="SpecifiedTradeAllowanceCharge"]') as $acNode) {
            $indStr = strtolower($this->xpVal($xpath, './*[local-name()="ChargeIndicator"]/*[local-name()="Indicator"]', 'false', $acNode));
            $isCharge = ($indStr === 'true' || $indStr === '1');
            $amount = (float)$this->xpVal($xpath, './*[local-name()="ActualAmount"]', '0', $acNode);
            $reason = $this->xpVal($xpath, './*[local-name()="Reason"]', $isCharge ? 'Zuschlag' : 'Rabatt', $acNode);
            $acTaxRate = (float)$this->xpVal($xpath, './*[local-name()="CategoryTradeTax"]/*[local-name()="RateApplicablePercent"]', (string)$mainTaxRate, $acNode);

            $allowancesCharges[] = [
                'is_charge' => $isCharge,
                'amount' => round($amount, 2),
                'reason' => $reason,
                'tax_rate' => $acTaxRate,
            ];
        }

        // Summenblock (SpecifiedTradeSettlementHeaderMonetarySummation)
        $sumNode = $xpath->query('//*[local-name()="ApplicableHeaderTradeSettlement"]/*[local-name()="SpecifiedTradeSettlementHeaderMonetarySummation"]')->item(0);
        $lineTotalNet = (float)$this->xpVal($xpath, './*[local-name()="LineTotalAmount"]', '0', $sumNode);
        $chargeTotalNet = (float)$this->xpVal($xpath, './*[local-name()="ChargeTotalAmount"]', '0', $sumNode);
        $allowanceTotalNet = (float)$this->xpVal($xpath, './*[local-name()="AllowanceTotalAmount"]', '0', $sumNode);
        $taxBasisNet = (float)$this->xpVal($xpath, './*[local-name()="TaxBasisTotalAmount"]', '0', $sumNode);
        $taxTotal = (float)$this->xpVal($xpath, './*[local-name()="TaxTotalAmount"]', '0', $sumNode);
        $grandTotalGross = (float)$this->xpVal($xpath, './*[local-name()="GrandTotalAmount"]', '0', $sumNode);
        $prepaidAmount = (float)$this->xpVal($xpath, './*[local-name()="TotalPrepaidAmount"]', '0', $sumNode);
        $duePayableAmount = (float)$this->xpVal($xpath, './*[local-name()="DuePayableAmount"]', (string)$grandTotalGross, $sumNode);

        // Einzelpositionen (IncludedSupplyChainTradeLineItem)
        $items = [];
        foreach ($xpath->query('//*[local-name()="IncludedSupplyChainTradeLineItem"]') as $idx => $lineNode) {
            $lineId = $this->xpVal($xpath, './*[local-name()="AssociatedDocumentLineDocument"]/*[local-name()="LineID"]', (string)($idx + 1), $lineNode);
            $sku = $this->xpVal($xpath, './*[local-name()="SpecifiedTradeProduct"]/*[local-name()="SellerAssignedID"]', '', $lineNode);
            $name = $this->xpVal($xpath, './*[local-name()="SpecifiedTradeProduct"]/*[local-name()="Name"]', 'Position ' . ($idx + 1), $lineNode);
            $qtyNode = $xpath->query('./*[local-name()="SpecifiedLineTradeDelivery"]/*[local-name()="BilledQuantity"]', $lineNode)->item(0);
            $qty = $qtyNode ? (float)trim($qtyNode->textContent) : 1.0;
            $unitCode = $qtyNode?->attributes?->getNamedItem('unitCode')?->nodeValue ?: 'H87';

            $netUnitPrice = (float)$this->xpVal($xpath, './*[local-name()="SpecifiedLineTradeAgreement"]/*[local-name()="NetPriceProductTradePrice"]/*[local-name()="ChargeAmount"]', '0', $lineNode);
            $itemTaxRate = (float)$this->xpVal($xpath, './*[local-name()="SpecifiedLineTradeSettlement"]/*[local-name()="ApplicableTradeTax"]/*[local-name()="RateApplicablePercent"]', (string)$mainTaxRate, $lineNode);
            $itemLineTotal = (float)$this->xpVal($xpath, './*[local-name()="SpecifiedLineTradeSettlement"]/*[local-name()="SpecifiedTradeSettlementLineMonetarySummation"]/*[local-name()="LineTotalAmount"]', (string)round($netUnitPrice * $qty, 2), $lineNode);

            $items[] = [
                'line_id' => $lineId,
                'sku' => $sku,
                'name' => $name,
                'quantity' => $qty,
                'unit_code' => $unitCode,
                'net_unit_price' => round($netUnitPrice, 4),
                'tax_rate' => $itemTaxRate,
                'line_total_net' => round($itemLineTotal, 2),
            ];
        }

        return [
            'valid' => true,
            'format' => 'UN/CEFACT CII (ZUGFeRD / XRechnung)',
            'profile' => $profile ?: 'urn:cen.eu:en16931:2017',
            'business_process' => $businessProcess,
            'type_code' => $typeCode,
            'type_label' => in_array($typeCode, ['381', '384', '81'], true) ? 'Gutschrift / Korrektur' : 'Rechnung',
            'invoice_number' => $invoiceNumber,
            'issue_date' => $issueDate,
            'delivery_date' => $deliveryDate,
            'due_date' => $dueDate,
            'buyer_reference' => $buyerReference,
            'currency' => $currency,
            'seller' => [
                'name' => $sellerName,
                'contact_name' => $sellerContactName,
                'street' => $sellerStreet,
                'zip' => $sellerZip,
                'city' => $sellerCity,
                'country' => $sellerCountry,
                'email' => $sellerEmail,
                'phone' => $sellerPhone,
                'vat_id' => $sellerVatId,
                'tax_id' => $sellerTaxId,
                'iban' => $iban,
                'bic' => $bic,
            ],
            'buyer' => [
                'name' => $buyerName,
                'street' => $buyerStreet,
                'zip' => $buyerZip,
                'city' => $buyerCity,
                'country' => $buyerCountry,
                'email' => $buyerEmail,
            ],
            'totals' => [
                'line_total_net' => round($lineTotalNet, 2),
                'allowance_total_net' => round($allowanceTotalNet, 2),
                'charge_total_net' => round($chargeTotalNet, 2),
                'tax_basis_net' => round($taxBasisNet, 2),
                'tax_total' => round($taxTotal, 2),
                'grand_total_gross' => round($grandTotalGross, 2),
                'prepaid_amount' => round($prepaidAmount, 2),
                'due_payable_amount' => round($duePayableAmount, 2),
                'tax_rate' => $mainTaxRate,
            ],
            'items' => $items,
            'allowances_charges' => $allowancesCharges,
            'notes' => $notes,
            'payment_terms' => $paymentTerms,
        ];
    }

    /**
     * Parst OASIS UBL 2.1 Invoice / CreditNote (XRechnung UBL / Peppol BIS 3.0).
     */
    private function parseUbl(DOMDocument $dom, bool $isCreditNote = false): array
    {
        $xpath = new DOMXPath($dom);

        $profile = $this->xpVal($xpath, '/*/*[local-name()="CustomizationID"]');
        $businessProcess = $this->xpVal($xpath, '/*/*[local-name()="ProfileID"]');
        $invoiceNumber = $this->xpVal($xpath, '/*/*[local-name()="ID"]');
        $typeCode = $this->xpVal($xpath, '/*/*[local-name()="InvoiceTypeCode"] | /*/*[local-name()="CreditNoteTypeCode"]', $isCreditNote ? '381' : '380');
        $issueDate = $this->normalizeDate($this->xpVal($xpath, '/*/*[local-name()="IssueDate"]'));
        $dueDate = $this->normalizeDate($this->xpVal($xpath, '/*/*[local-name()="DueDate"]'));
        $deliveryDate = $this->normalizeDate($this->xpVal($xpath, '//*[local-name()="Delivery"]/*[local-name()="ActualDeliveryDate"]')) ?: $issueDate;
        $currency = $this->xpVal($xpath, '/*/*[local-name()="DocumentCurrencyCode"]', 'EUR');
        $buyerReference = $this->xpVal($xpath, '/*/*[local-name()="BuyerReference"]');

        $sellerParty = $xpath->query('//*[local-name()="AccountingSupplierParty"]/*[local-name()="Party"]')->item(0);
        $sellerName = $this->xpVal($xpath, './*[local-name()="PartyLegalEntity"]/*[local-name()="RegistrationName"] | ./*[local-name()="PartyName"]/*[local-name()="Name"]', '', $sellerParty);
        $sellerStreet = $this->xpVal($xpath, './*[local-name()="PostalAddress"]/*[local-name()="StreetName"]', '', $sellerParty);
        $sellerZip = $this->xpVal($xpath, './*[local-name()="PostalAddress"]/*[local-name()="PostalZone"]', '', $sellerParty);
        $sellerCity = $this->xpVal($xpath, './*[local-name()="PostalAddress"]/*[local-name()="CityName"]', '', $sellerParty);
        $sellerCountry = $this->xpVal($xpath, './*[local-name()="PostalAddress"]/*[local-name()="Country"]/*[local-name()="IdentificationCode"]', 'DE', $sellerParty);
        $sellerEmail = $this->xpVal($xpath, './*[local-name()="EndpointID"] | ./*[local-name()="Contact"]/*[local-name()="ElectronicMail"]', '', $sellerParty);
        $sellerPhone = $this->xpVal($xpath, './*[local-name()="Contact"]/*[local-name()="Telephone"]', '', $sellerParty);
        $sellerContactName = $this->xpVal($xpath, './*[local-name()="Contact"]/*[local-name()="Name"]', '', $sellerParty);
        $sellerVatId = $this->xpVal($xpath, './*[local-name()="PartyTaxScheme"]/*[local-name()="CompanyID"]', '', $sellerParty);

        $buyerParty = $xpath->query('//*[local-name()="AccountingCustomerParty"]/*[local-name()="Party"]')->item(0);
        $buyerName = $this->xpVal($xpath, './*[local-name()="PartyLegalEntity"]/*[local-name()="RegistrationName"] | ./*[local-name()="PartyName"]/*[local-name()="Name"]', '', $buyerParty);
        $buyerStreet = $this->xpVal($xpath, './*[local-name()="PostalAddress"]/*[local-name()="StreetName"]', '', $buyerParty);
        $buyerZip = $this->xpVal($xpath, './*[local-name()="PostalAddress"]/*[local-name()="PostalZone"]', '', $buyerParty);
        $buyerCity = $this->xpVal($xpath, './*[local-name()="PostalAddress"]/*[local-name()="CityName"]', '', $buyerParty);
        $buyerCountry = $this->xpVal($xpath, './*[local-name()="PostalAddress"]/*[local-name()="Country"]/*[local-name()="IdentificationCode"]', 'DE', $buyerParty);
        $buyerEmail = $this->xpVal($xpath, './*[local-name()="EndpointID"] | ./*[local-name()="Contact"]/*[local-name()="ElectronicMail"]', '', $buyerParty);

        $iban = $this->xpVal($xpath, '//*[local-name()="PaymentMeans"]/*[local-name()="PayeeFinancialAccount"]/*[local-name()="ID"]');
        $bic = $this->xpVal($xpath, '//*[local-name()="PaymentMeans"]/*[local-name()="PayeeFinancialAccount"]/*[local-name()="FinancialInstitutionBranch"]/*[local-name()="ID"]');
        $paymentTerms = $this->xpVal($xpath, '//*[local-name()="PaymentTerms"]/*[local-name()="Note"]');

        $sumNode = $xpath->query('//*[local-name()="LegalMonetaryTotal"]')->item(0);
        $lineTotalNet = (float)$this->xpVal($xpath, './*[local-name()="LineExtensionAmount"]', '0', $sumNode);
        $allowanceTotalNet = (float)$this->xpVal($xpath, './*[local-name()="AllowanceTotalAmount"]', '0', $sumNode);
        $chargeTotalNet = (float)$this->xpVal($xpath, './*[local-name()="ChargeTotalAmount"]', '0', $sumNode);
        $taxBasisNet = (float)$this->xpVal($xpath, './*[local-name()="TaxExclusiveAmount"]', (string)$lineTotalNet, $sumNode);
        $grandTotalGross = (float)$this->xpVal($xpath, './*[local-name()="TaxInclusiveAmount"]', '0', $sumNode);
        $prepaidAmount = (float)$this->xpVal($xpath, './*[local-name()="PrepaidAmount"]', '0', $sumNode);
        $duePayableAmount = (float)$this->xpVal($xpath, './*[local-name()="PayableAmount"]', (string)$grandTotalGross, $sumNode);
        $taxTotal = (float)$this->xpVal($xpath, '//*[local-name()="TaxTotal"]/*[local-name()="TaxAmount"]', (string)max(0, $grandTotalGross - $taxBasisNet));
        $mainTaxRate = (float)$this->xpVal($xpath, '//*[local-name()="TaxTotal"]/*[local-name()="TaxSubtotal"]/*[local-name()="TaxCategory"]/*[local-name()="Percent"]', (string) shop_setting('default_tax_rate', 19));

        $items = [];
        foreach ($xpath->query('//*[local-name()="InvoiceLine"] | //*[local-name()="CreditNoteLine"]') as $idx => $lineNode) {
            $lineId = $this->xpVal($xpath, './*[local-name()="ID"]', (string)($idx + 1), $lineNode);
            $name = $this->xpVal($xpath, './*[local-name()="Item"]/*[local-name()="Name"]', 'Position ' . ($idx + 1), $lineNode);
            $sku = $this->xpVal($xpath, './*[local-name()="Item"]/*[local-name()="SellersItemIdentification"]/*[local-name()="ID"]', '', $lineNode);
            $qty = (float)$this->xpVal($xpath, './*[local-name()="InvoicedQuantity"] | ./*[local-name()="CreditedQuantity"]', '1', $lineNode);
            $netUnitPrice = (float)$this->xpVal($xpath, './*[local-name()="Price"]/*[local-name()="PriceAmount"]', '0', $lineNode);
            $itemLineTotal = (float)$this->xpVal($xpath, './*[local-name()="LineExtensionAmount"]', (string)round($netUnitPrice * $qty, 2), $lineNode);
            $itemTaxRate = (float)$this->xpVal($xpath, './*[local-name()="Item"]/*[local-name()="ClassifiedTaxCategory"]/*[local-name()="Percent"]', (string)$mainTaxRate, $lineNode);

            $items[] = [
                'line_id' => $lineId,
                'sku' => $sku,
                'name' => $name,
                'quantity' => $qty,
                'unit_code' => 'H87',
                'net_unit_price' => round($netUnitPrice, 4),
                'tax_rate' => $itemTaxRate,
                'line_total_net' => round($itemLineTotal, 2),
            ];
        }

        return [
            'valid' => true,
            'format' => 'OASIS UBL 2.1 (XRechnung / Peppol)',
            'profile' => $profile ?: 'urn:cen.eu:en16931:2017',
            'business_process' => $businessProcess,
            'type_code' => $typeCode,
            'type_label' => ($isCreditNote || in_array($typeCode, ['381', '384'], true)) ? 'Gutschrift / Korrektur' : 'Rechnung',
            'invoice_number' => $invoiceNumber,
            'issue_date' => $issueDate,
            'delivery_date' => $deliveryDate,
            'due_date' => $dueDate,
            'buyer_reference' => $buyerReference,
            'currency' => $currency,
            'seller' => [
                'name' => $sellerName,
                'contact_name' => $sellerContactName,
                'street' => $sellerStreet,
                'zip' => $sellerZip,
                'city' => $sellerCity,
                'country' => $sellerCountry,
                'email' => $sellerEmail,
                'phone' => $sellerPhone,
                'vat_id' => $sellerVatId,
                'tax_id' => '',
                'iban' => $iban,
                'bic' => $bic,
            ],
            'buyer' => [
                'name' => $buyerName,
                'street' => $buyerStreet,
                'zip' => $buyerZip,
                'city' => $buyerCity,
                'country' => $buyerCountry,
                'email' => $buyerEmail,
            ],
            'totals' => [
                'line_total_net' => round($lineTotalNet, 2),
                'allowance_total_net' => round($allowanceTotalNet, 2),
                'charge_total_net' => round($chargeTotalNet, 2),
                'tax_basis_net' => round($taxBasisNet, 2),
                'tax_total' => round($taxTotal, 2),
                'grand_total_gross' => round($grandTotalGross, 2),
                'prepaid_amount' => round($prepaidAmount, 2),
                'due_payable_amount' => round($duePayableAmount, 2),
                'tax_rate' => $mainTaxRate,
            ],
            'items' => $items,
            'allowances_charges' => [],
            'notes' => [],
            'payment_terms' => $paymentTerms,
        ];
    }

    /**
     * Führt offizielle EN16931 / XRechnung 3.0 Plausibilitäts- & Pflichtfeld-Prüfungen durch.
     */
    private function runValidationChecks(array $parsed): array
    {
        $totals = $parsed['totals'] ?? [];
        $expectedBasis = round(($totals['line_total_net'] ?? 0) - ($totals['allowance_total_net'] ?? 0) + ($totals['charge_total_net'] ?? 0), 2);
        $actualBasis = round($totals['tax_basis_net'] ?? 0, 2);
        $expectedGross = round($actualBasis + ($totals['tax_total'] ?? 0), 2);
        $actualGross = round($totals['grand_total_gross'] ?? 0, 2);

        return [
            [
                'rule' => 'BR-1 / BR-2 (Belegnummer & Profil)',
                'passed' => !empty($parsed['invoice_number']) && !empty($parsed['profile']),
                'message' => !empty($parsed['invoice_number']) ? "Beleg #{$parsed['invoice_number']} ({$parsed['format']})" : 'Rechnungsnummer fehlt',
            ],
            [
                'rule' => 'BR-DE-15 (BuyerReference / Leitweg-ID)',
                'passed' => !empty($parsed['buyer_reference']),
                'message' => !empty($parsed['buyer_reference']) ? "Referenz: {$parsed['buyer_reference']}" : 'Keine Käuferreferenz (BT-10) hinterlegt',
            ],
            [
                'rule' => 'BR-DE-2 / BT-34 (Verkäufer-Kontakt & E-Adresse)',
                'passed' => !empty($parsed['seller']['name']) && (!empty($parsed['seller']['email']) || !empty($parsed['seller']['vat_id'])),
                'message' => ($parsed['seller']['name'] ?: 'Unbekannt') . ($parsed['seller']['vat_id'] ? " ({$parsed['seller']['vat_id']})" : ''),
            ],
            [
                'rule' => 'BR-CO-13 (Netto-Summenformel EN16931)',
                'passed' => abs($expectedBasis - $actualBasis) <= 0.05,
                'message' => number_format($expectedBasis, 2, ',', '.') . ' € berechnet vs. ' . number_format($actualBasis, 2, ',', '.') . ' € Steuerbasis',
            ],
            [
                'rule' => 'BR-CO-15 (Brutto-Summenformel EN16931)',
                'passed' => abs($expectedGross - $actualGross) <= 0.05,
                'message' => number_format($actualBasis, 2, ',', '.') . ' € Netto + ' . number_format($totals['tax_total'] ?? 0, 2, ',', '.') . ' € USt = ' . number_format($actualGross, 2, ',', '.') . ' € Brutto',
            ],
        ];
    }

    /**
     * Extrahiert eingebettetes ZUGFeRD / Factur-X XML aus einem PDF-Datenstrom.
     */
    private function extractXmlFromPdf(string $pdfRaw): ?string
    {
        // 1. Direkte Suche im unkomprimierten Stream
        if (preg_match('/(<\?xml[^>]*>.*?<\/(?:[a-zA-Z0-9]+:)?(?:CrossIndustryInvoice|Invoice|CreditNote)>)/s', $pdfRaw, $m)) {
            return $m[1];
        }

        // 2. Dekomprimierung aller PDF-Streams (FlateDecode)
        if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $pdfRaw, $streams)) {
            foreach ($streams[1] as $streamData) {
                $inflated = @gzuncompress($streamData);
                if ($inflated === false) {
                    $inflated = @gzinflate($streamData);
                }
                if (is_string($inflated) && (str_contains($inflated, 'CrossIndustryInvoice') || str_contains($inflated, ':Invoice') || str_contains($inflated, '<Invoice'))) {
                    if (preg_match('/(<\?xml[^>]*>.*?<\/(?:[a-zA-Z0-9]+:)?(?:CrossIndustryInvoice|Invoice|CreditNote)>)/s', $inflated, $m)) {
                        return $m[1];
                    }
                    if (preg_match('/(<(?:[a-zA-Z0-9]+:)?(?:CrossIndustryInvoice|Invoice|CreditNote)\b.*?<\/(?:[a-zA-Z0-9]+:)?(?:CrossIndustryInvoice|Invoice|CreditNote)>)/s', $inflated, $m)) {
                        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $m[1];
                    }
                }
            }
        }

        return null;
    }

    private function xpVal(DOMXPath $xpath, string $query, string $default = '', $contextNode = null): string
    {
        $nodes = $contextNode ? $xpath->query($query, $contextNode) : $xpath->query($query);
        if ($nodes && $nodes->length > 0) {
            return trim((string)$nodes->item(0)->textContent);
        }
        return $default;
    }

    private function normalizeDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $raw, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
            return $m[1];
        }
        return $raw;
    }
}
