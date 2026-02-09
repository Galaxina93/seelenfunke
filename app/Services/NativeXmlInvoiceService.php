<?php

namespace App\Services;

use App\Models\Invoice;
use DOMDocument;
use Illuminate\Support\Facades\Storage;

class NativeXmlInvoiceService
{
    private $dom;
    private $currency = 'EUR';

    public function generate(Invoice $invoice)
    {
        // 1. DOM Initialisieren
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;

        // 2. Root Element mit Namespaces (WICHTIG für Validierung!)
        $root = $this->dom->createElement('rsm:CrossIndustryInvoice');
        $root->setAttribute('xmlns:rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $root->setAttribute('xmlns:ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $root->setAttribute('xmlns:udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
        $root->setAttribute('xmlns:qdt', 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100');
        $this->dom->appendChild($root);

        // 3. Context (Profil: EN16931)
        $context = $this->addElement($root, 'rsm:ExchangedDocumentContext');
        $guideline = $this->addElement($context, 'ram:GuidelineSpecifiedDocumentContextParameter');
        $this->addElement($guideline, 'ram:ID', 'urn:cen.eu:en16931:2017');

        // 4. Header (Rechnungsnummer, Datum)
        $header = $this->addElement($root, 'rsm:ExchangedDocument');
        $this->addElement($header, 'ram:ID', $invoice->invoice_number);
        $this->addElement($header, 'ram:TypeCode', $invoice->isCreditNote() ? '381' : '380'); // 380 = Rechnung
        $dateObj = $this->addElement($header, 'ram:IssueDateTime');
        $this->addElement($dateObj, 'udt:DateTimeString', $invoice->invoice_date->format('Ymd'), ['format' => '102']);

        // 5. Transaktion (Der eigentliche Inhalt)
        $transaction = $this->addElement($root, 'rsm:SupplyChainTradeTransaction');

        // A) Positionen (Line Items)
        $this->addItems($transaction, $invoice);

        // B) Header Trade Agreement (Verkäufer & Käufer)
        $agreement = $this->addElement($transaction, 'ram:ApplicableHeaderTradeAgreement');
        $this->addSeller($agreement);
        $this->addBuyer($agreement, $invoice);

        // C) Lieferung
        $delivery = $this->addElement($transaction, 'ram:ApplicableHeaderTradeDelivery');
        $event = $this->addElement($delivery, 'ram:ActualDeliverySupplyChainEvent');
        $occurrence = $this->addElement($event, 'ram:OccurrenceDateTime');
        $deliveryDate = $invoice->delivery_date ?? $invoice->invoice_date;
        $this->addElement($occurrence, 'udt:DateTimeString', $deliveryDate->format('Ymd'), ['format' => '102']);

        // D) Summen & Steuern (Settlement)
        $this->addSettlement($transaction, $invoice);

        // Speichern
        $xmlContent = $this->dom->saveXML();

        if(!Storage::disk('local')->exists('invoices/xml')) {
            Storage::disk('local')->makeDirectory('invoices/xml');
        }

        $filename = 'invoices/xml/' . $invoice->invoice_number . '.xml';
        Storage::disk('local')->put($filename, $xmlContent);

        return $filename;
    }

    private function addItems($transaction, Invoice $invoice)
    {
        $isSmallBusiness = (bool)shop_setting('is_small_business', false);

        // Positionen + Versandkosten zusammenführen
        $allItems = $invoice->custom_items ?? [];

        if ($invoice->shipping_cost > 0) {
            $allItems[] = [
                'product_name' => 'Versandkosten',
                'quantity' => 1,
                'unit_price' => $invoice->shipping_cost, // in Cent
                'tax_rate' => $isSmallBusiness ? 0 : 19
            ];
        }

        foreach ($allItems as $index => $item) {
            $line = $this->addElement($transaction, 'ram:IncludedSupplyChainTradeLineItem');

            // Document Line Document
            $lineDoc = $this->addElement($line, 'ram:AssociatedDocumentLineDocument');
            $this->addElement($lineDoc, 'ram:LineID', $index + 1);

            // Product Name
            $tradeProduct = $this->addElement($line, 'ram:SpecifiedTradeProduct');
            $this->addElement($tradeProduct, 'ram:Name', $item['product_name']);

            // Agreement (Preise)
            $agreement = $this->addElement($line, 'ram:SpecifiedLineTradeAgreement');

            // Preisberechnung
            $grossCents = (float)$item['unit_price'];
            $taxRate = (float)($item['tax_rate'] ?? 19);
            $qty = (float)$item['quantity'];

            // Netto berechnen
            if ($isSmallBusiness) {
                $netPrice = $grossCents / 100;
                $taxCode = 'E'; // Exempt
            } elseif ($taxRate == 0) {
                $netPrice = $grossCents / 100;
                $taxCode = 'Z'; // Zero
            } else {
                $netPrice = ($grossCents / 100) / (1 + ($taxRate / 100));
                $taxCode = 'S'; // Standard
            }

            // Net Price
            $grossPriceProduct = $this->addElement($agreement, 'ram:GrossPriceProductTradePrice');
            $this->addElement($grossPriceProduct, 'ram:ChargeAmount', number_format($netPrice, 4, '.', ''));

            $netPriceProduct = $this->addElement($agreement, 'ram:NetPriceProductTradePrice');
            $this->addElement($netPriceProduct, 'ram:ChargeAmount', number_format($netPrice, 4, '.', ''));

            // Delivery (Menge)
            $delivery = $this->addElement($line, 'ram:SpecifiedLineTradeDelivery');
            $this->addElement($delivery, 'ram:BilledQuantity', $qty, ['unitCode' => 'H87']); // H87 = Stück

            // Settlement (Steuer pro Zeile)
            $settlement = $this->addElement($line, 'ram:SpecifiedLineTradeSettlement');
            $tax = $this->addElement($settlement, 'ram:ApplicableTradeTax');
            $this->addElement($tax, 'ram:TypeCode', 'VAT');
            $this->addElement($tax, 'ram:CategoryCode', $taxCode);
            $this->addElement($tax, 'ram:RateApplicablePercent', number_format($taxRate, 2, '.', ''));

            // Zeilensumme (Netto * Menge)
            $lineSum = $this->addElement($settlement, 'ram:SpecifiedTradeSettlementLineMonetarySummation');
            $this->addElement($lineSum, 'ram:LineTotalAmount', number_format($netPrice * $qty, 2, '.', ''));
        }
    }

    private function addSeller($agreement)
    {
        $seller = $this->addElement($agreement, 'ram:SellerTradeParty');
        $this->addElement($seller, 'ram:Name', shop_setting('owner_name', 'Mein Seelenfunke'));

        // Addresse
        $address = $this->addElement($seller, 'ram:PostalTradeAddress');
        $this->addElement($address, 'ram:PostcodeCode', shop_setting('owner_zip', '38518'));
        $this->addElement($address, 'ram:LineOne', shop_setting('owner_street', 'Carl-Goerdeler-Ring 26'));
        $this->addElement($address, 'ram:CityName', shop_setting('owner_city', 'Gifhorn'));
        $this->addElement($address, 'ram:CountryID', 'DE');

        // Steuernummer
        if ($ustId = shop_setting('owner_ust_id')) {
            $tax = $this->addElement($seller, 'ram:SpecifiedTaxRegistration');
            $this->addElement($tax, 'ram:ID', $ustId, ['schemeID' => 'VA']);
        }
    }

    private function addBuyer($agreement, Invoice $invoice)
    {
        $buyer = $this->addElement($agreement, 'ram:BuyerTradeParty');
        $name = !empty($invoice->billing_address['company'])
            ? $invoice->billing_address['company']
            : ($invoice->billing_address['first_name'] ?? '') . ' ' . ($invoice->billing_address['last_name'] ?? '');

        $this->addElement($buyer, 'ram:Name', $name);

        $address = $this->addElement($buyer, 'ram:PostalTradeAddress');
        $this->addElement($address, 'ram:PostcodeCode', $invoice->billing_address['postal_code'] ?? '');
        $this->addElement($address, 'ram:LineOne', $invoice->billing_address['address'] ?? '');
        $this->addElement($address, 'ram:CityName', $invoice->billing_address['city'] ?? '');
        $this->addElement($address, 'ram:CountryID', $invoice->billing_address['country'] ?? 'DE');
    }

    private function addSettlement($transaction, Invoice $invoice)
    {
        $settlement = $this->addElement($transaction, 'ram:ApplicableHeaderTradeSettlement');
        $this->addElement($settlement, 'ram:InvoiceCurrencyCode', $this->currency);

        // IBAN
        if ($iban = shop_setting('owner_iban')) {
            $payment = $this->addElement($settlement, 'ram:SpecifiedTradeSettlementPaymentMeans');
            $this->addElement($payment, 'ram:TypeCode', '58'); // 58 = Überweisung
            $account = $this->addElement($payment, 'ram:PayeePartyCreditorFinancialAccount');
            $this->addElement($account, 'ram:IBANID', $iban);
        }

        // Steuern aufschlüsseln (Vereinfacht: Wir nehmen an, dass alles den gleichen Steuersatz hat oder Kleinunternehmer)
        // Für 100% Korrektheit müsste man nach Steuersätzen gruppieren.
        // Hier nehmen wir die Gesamtwerte aus der Invoice-DB.

        $isSmallBusiness = (bool)shop_setting('is_small_business', false);
        $taxTotalAmount = $invoice->tax_amount / 100;
        $grandTotal = $invoice->total / 100;
        $netTotal = ($invoice->total - $invoice->tax_amount) / 100;

        $tax = $this->addElement($settlement, 'ram:ApplicableTradeTax');
        $this->addElement($tax, 'ram:CalculatedAmount', number_format($taxTotalAmount, 2, '.', ''));
        $this->addElement($tax, 'ram:TypeCode', 'VAT');
        $this->addElement($tax, 'ram:BasisAmount', number_format($netTotal, 2, '.', ''));

        // Kategorie (Hauptsteuersatz der Rechnung raten)
        // Wenn Kleinunternehmer -> E, sonst S (Standard)
        $catCode = $isSmallBusiness ? 'E' : 'S';
        if ($taxTotalAmount == 0 && !$isSmallBusiness) $catCode = 'Z';

        $this->addElement($tax, 'ram:CategoryCode', $catCode);
        $this->addElement($tax, 'ram:RateApplicablePercent', $isSmallBusiness ? '0.00' : '19.00'); // TODO: Dynamisch machen wenn 7% genutzt wird

        // Gesamtsummen
        $summation = $this->addElement($settlement, 'ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $this->addElement($summation, 'ram:LineTotalAmount', number_format($netTotal, 2, '.', '')); // Summe aller Positionen Netto
        $this->addElement($summation, 'ram:TaxBasisTotalAmount', number_format($netTotal, 2, '.', ''));
        $this->addElement($summation, 'ram:TaxTotalAmount', number_format($taxTotalAmount, 2, '.', ''), ['currencyID' => $this->currency]);
        $this->addElement($summation, 'ram:GrandTotalAmount', number_format($grandTotal, 2, '.', ''));
        $this->addElement($summation, 'ram:DuePayableAmount', number_format($grandTotal, 2, '.', ''));
    }

    /**
     * Helper um XML Elemente schneller zu erstellen
     */
    private function addElement($parent, $name, $value = null, $attributes = [])
    {
        $element = $this->dom->createElement($name);
        if ($value !== null) {
            $element->nodeValue = htmlspecialchars($value);
        }

        foreach ($attributes as $key => $val) {
            $element->setAttribute($key, $val);
        }

        $parent->appendChild($element);
        return $element;
    }
}
