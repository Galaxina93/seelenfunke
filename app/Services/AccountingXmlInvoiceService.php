<?php

namespace App\Services;

use App\Models\Accounting\AccountingInvoice;
use DOMDocument;
use Illuminate\Support\Facades\Storage;

class AccountingXmlInvoiceService
{
    private $dom;
    private $currency = 'EUR';

    public function generate(AccountingInvoice $invoice)
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

        // 3. Context (Profil: EN16931 / Peppol Billing 3.0)
        $context = $this->addElement($root, 'rsm:ExchangedDocumentContext');
        $businessProcess = $this->addElement($context, 'ram:BusinessProcessSpecifiedDocumentContextParameter');
        $this->addElement($businessProcess, 'ram:ID', 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0');
        $guideline = $this->addElement($context, 'ram:GuidelineSpecifiedDocumentContextParameter');
        $this->addElement($guideline, 'ram:ID', 'urn:cen.eu:en16931:2017');

        // 4. Header (Rechnungsnummer, Datum)
        $header = $this->addElement($root, 'rsm:ExchangedDocument');
        $this->addElement($header, 'ram:ID', $invoice->invoice_number);
        $this->addElement($header, 'ram:TypeCode', $invoice->isCreditNote() ? '381' : '380'); // 380 = Rechnung, 381 = Gutschrift
        $dateObj = $this->addElement($header, 'ram:IssueDateTime');
        $invoiceDate = $invoice->invoice_date ?? now();
        $this->addElement($dateObj, 'udt:DateTimeString', $invoiceDate->format('Ymd'), ['format' => '102']);
        if (!empty($invoice->subject)) {
            $note = $this->addElement($header, 'ram:IncludedNote');
            $this->addElement($note, 'ram:Content', $invoice->subject);
        }

        // 5. Transaktion (Der eigentliche Inhalt)
        $transaction = $this->addElement($root, 'rsm:SupplyChainTradeTransaction');

        // A) Positionen (Line Items)
        $this->addItems($transaction, $invoice);

        // B) Header Trade Agreement (Leitweg-ID/BuyerReference, Verkäufer & Käufer)
        $agreement = $this->addElement($transaction, 'ram:ApplicableHeaderTradeAgreement');
        $buyerRef = ($invoice->order && !empty($invoice->order->order_number))
            ? (string)$invoice->order->order_number
            : (string)$invoice->invoice_number;
        $this->addElement($agreement, 'ram:BuyerReference', $buyerRef);
        $this->addSeller($agreement);
        $this->addBuyer($agreement, $invoice);

        // C) Lieferung
        $delivery = $this->addElement($transaction, 'ram:ApplicableHeaderTradeDelivery');
        $event = $this->addElement($delivery, 'ram:ActualDeliverySupplyChainEvent');
        $occurrence = $this->addElement($event, 'ram:OccurrenceDateTime');
        $deliveryDate = $invoice->delivery_date ?? $invoice->invoice_date ?? now();
        $this->addElement($occurrence, 'udt:DateTimeString', $deliveryDate->format('Ymd'), ['format' => '102']);

        // D) Summen & Steuern (Settlement)
        $this->addSettlement($transaction, $invoice);

        // Speichern
        $xmlContent = $this->dom->saveXML();

        if(!Storage::disk('local')->exists('buchhaltung/invoices/xml')) {
            Storage::disk('local')->makeDirectory('buchhaltung/invoices/xml');
        }

        $safeInvoiceNum = preg_replace('/[^A-Za-z0-9_\-]/', '_', $invoice->invoice_number ?: (string)$invoice->id);
        $filename = 'buchhaltung/invoices/xml/' . $safeInvoiceNum . '.xml';
        Storage::disk('local')->put($filename, $xmlContent);

        return $filename;
    }

    private function addItems($transaction, AccountingInvoice $invoice)
    {
        $isSmallBusiness = $invoice->is_small_business !== null
            ? (bool)$invoice->is_small_business
            : app(\App\Services\Tax\TaxSettingService::class)->isSmallBusinessForYear($invoice->invoice_date?->year ?? now()->year);

        // Positionen + Versandkosten zusammenführen
        $allItems = $invoice->custom_items ?? [];

        if (empty($allItems)) {
            $invoice->loadMissing('order.items');
            if ($invoice->order && $invoice->order->items) {
                foreach ($invoice->order->items as $orderItem) {
                    $cfg = is_array($orderItem->configuration) ? $orderItem->configuration : [];
                    $itemName = (string) $orderItem->product_name;
                    $detailParts = [];
                    if (!empty($cfg['variant_name'])) {
                        $detailParts[] = 'Ausführung: ' . $cfg['variant_name'];
                    }
                    if (!empty($cfg['addons_summary']) && is_array($cfg['addons_summary'])) {
                        $addonNames = array_values(array_filter(array_map(fn($a) => is_array($a) ? ($a['name'] ?? '') : '', $cfg['addons_summary'])));
                        if (!empty($addonNames)) {
                            $detailParts[] = 'Verpackung & Extras: ' . implode(', ', $addonNames);
                        }
                    }
                    if (!empty($detailParts)) {
                        $itemName .= ' (' . implode(' | ', $detailParts) . ')';
                    }

                    $allItems[] = [
                        'product_name' => $itemName,
                        'quantity' => $orderItem->quantity,
                        'unit_price' => $orderItem->unit_price,
                        'tax_rate' => $isSmallBusiness ? 0 : ($orderItem->tax_rate ?? shop_setting('default_tax_rate', 19.0)),
                    ];
                }
            }
        }

        $shippingCostCents = abs((int) ($invoice->shipping_cost ?? 0));
        if ($shippingCostCents > 0) {
            $hasPostalVoucher = false;
            if ($invoice->order && $invoice->order->items) {
                foreach ($invoice->order->items as $orderItem) {
                    $cfg = is_array($orderItem->configuration) ? $orderItem->configuration : [];
                    if (!empty($cfg['is_gift_voucher']) && ($cfg['delivery_method'] ?? 'email') === 'post') {
                        $hasPostalVoucher = true;
                        break;
                    }
                }
            }
            $allItems[] = [
                'product_name' => $hasPostalVoucher ? 'Versandkosten (Gutschein per Post im Umschlag)' : 'Versandkosten',
                'quantity' => 1,
                'unit_price' => $shippingCostCents, // in Cent
                'tax_rate' => $isSmallBusiness ? 0 : shop_setting('default_tax_rate', 19.0)
            ];
        }

        // Express-Zuschlag als eigene Position anhängen (falls vorhanden)
        $expressPrice = abs((int) ($invoice->express_price ?? ($invoice->order->express_price ?? 0)));
        if ($expressPrice > 0) {
            $allItems[] = [
                'product_name' => 'Priorisierte Fertigung (Express-Service)',
                'quantity' => 1,
                'unit_price' => $expressPrice, // in Cent
                'tax_rate' => $isSmallBusiness ? 0 : shop_setting('default_tax_rate', 19.0)
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
            $grossCents = abs((float)$item['unit_price']);
            $taxRate = (float)($item['tax_rate'] ?? shop_setting('default_tax_rate', 19.0));
            $qty = (float)$item['quantity'];

            // Netto berechnen
            if ($isSmallBusiness) {
                $netPrice = $grossCents / 100;
                $taxCode = 'E'; // Exempt
                $itemTaxRate = 0.0;
            } elseif ($taxRate == 0) {
                $netPrice = $grossCents / 100;
                $taxCode = 'Z'; // Zero
                $itemTaxRate = 0.0;
            } else {
                $netPrice = ($grossCents / 100) / (1 + ($taxRate / 100));
                $taxCode = 'S'; // Standard
                $itemTaxRate = $taxRate;
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
            $this->addElement($tax, 'ram:RateApplicablePercent', number_format($itemTaxRate, 2, '.', ''));

            // Zeilensumme (Netto * Menge)
            $lineSum = $this->addElement($settlement, 'ram:SpecifiedTradeSettlementLineMonetarySummation');
            $this->addElement($lineSum, 'ram:LineTotalAmount', number_format($netPrice * $qty, 2, '.', ''));
        }
    }

    private function addSeller($agreement)
    {
        $seller = $this->addElement($agreement, 'ram:SellerTradeParty');
        $this->addElement($seller, 'ram:Name', shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')));

        // BR-DE-2: Verkäufer-Kontakt (BG-6)
        $contact = $this->addElement($seller, 'ram:DefinedTradeContact');
        $this->addElement($contact, 'ram:PersonName', shop_setting('owner_proprietor', 'Alina Steinhauer'));
        $phone = shop_setting('company_phone', shop_setting('owner_phone', '+49 151 0000000'));
        if (!empty($phone)) {
            $tel = $this->addElement($contact, 'ram:TelephoneUniversalCommunication');
            $this->addElement($tel, 'ram:CompleteNumber', $phone);
        }
        $sellerEmail = shop_setting('owner_email_invoices') ?: shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
        $mailComm = $this->addElement($contact, 'ram:EmailURIUniversalCommunication');
        $this->addElement($mailComm, 'ram:URIID', $sellerEmail);

        // Addresse
        $address = $this->addElement($seller, 'ram:PostalTradeAddress');
        $this->addElement($address, 'ram:PostcodeCode', shop_setting('company_zip', '38518'));
        $street = shop_setting('company_street')
            ? trim(shop_setting('company_street') . ' ' . shop_setting('company_street_number', ''))
            : shop_setting('owner_street', 'Carl-Goerdeler-Ring 26');
        $this->addElement($address, 'ram:LineOne', $street);
        $this->addElement($address, 'ram:CityName', shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')));
        $this->addElement($address, 'ram:CountryID', strtoupper(shop_setting('company_country', 'DE')));

        // BT-34: Elektronische Adresse des Verkäufers (Peppol / XRechnung 3.0 Pflicht)
        $uriComm = $this->addElement($seller, 'ram:URIUniversalCommunication');
        $this->addElement($uriComm, 'ram:URIID', $sellerEmail, ['schemeID' => 'EM']);

        // Steuernummer & USt-IdNr.
        if ($ustId = shop_setting('company_ust_id', shop_setting('owner_ust_id'))) {
            $tax = $this->addElement($seller, 'ram:SpecifiedTaxRegistration');
            $this->addElement($tax, 'ram:ID', $ustId, ['schemeID' => 'VA']);
        }
        if ($taxId = shop_setting('owner_tax_id')) {
            $taxFc = $this->addElement($seller, 'ram:SpecifiedTaxRegistration');
            $this->addElement($taxFc, 'ram:ID', $taxId, ['schemeID' => 'FC']);
        }
    }

    private function addBuyer($agreement, AccountingInvoice $invoice)
    {
        $buyer = $this->addElement($agreement, 'ram:BuyerTradeParty');
        $name = !empty($invoice->billing_address['company'])
            ? $invoice->billing_address['company']
            : trim(($invoice->billing_address['first_name'] ?? '') . ' ' . ($invoice->billing_address['last_name'] ?? ''));

        $this->addElement($buyer, 'ram:Name', $name ?: 'Kunde');

        $address = $this->addElement($buyer, 'ram:PostalTradeAddress');
        $this->addElement($address, 'ram:PostcodeCode', $invoice->billing_address['postal_code'] ?? '');
        $this->addElement($address, 'ram:LineOne', $invoice->billing_address['address'] ?? '');
        $this->addElement($address, 'ram:CityName', $invoice->billing_address['city'] ?? '');
        $this->addElement($address, 'ram:CountryID', $invoice->billing_address['country'] ?? 'DE');

        // BT-49: Elektronische Adresse des Käufers
        $buyerEmail = $invoice->billing_address['email']
            ?? ($invoice->order->email ?? null)
            ?? ($invoice->customer->email ?? null)
            ?? 'rechnung@kunde.local';
        $uriComm = $this->addElement($buyer, 'ram:URIUniversalCommunication');
        $this->addElement($uriComm, 'ram:URIID', $buyerEmail, ['schemeID' => 'EM']);
    }

    private function addSettlement($transaction, AccountingInvoice $invoice)
    {
        $settlement = $this->addElement($transaction, 'ram:ApplicableHeaderTradeSettlement');
        $this->addElement($settlement, 'ram:PaymentReference', $invoice->invoice_number);
        $this->addElement($settlement, 'ram:InvoiceCurrencyCode', $this->currency);

        // IBAN (BT-84) & BIC (BT-86)
        $iban = shop_setting('owner_iban', 'DE81123456789012345678');
        if ($iban) {
            $payment = $this->addElement($settlement, 'ram:SpecifiedTradeSettlementPaymentMeans');
            $this->addElement($payment, 'ram:TypeCode', '58'); // 58 = SEPA-Überweisung
            $account = $this->addElement($payment, 'ram:PayeePartyCreditorFinancialAccount');
            $this->addElement($account, 'ram:IBANID', preg_replace('/\s+/', '', $iban));
            if ($bic = shop_setting('owner_bic')) {
                $institution = $this->addElement($payment, 'ram:PayeeSpecifiedCreditorFinancialInstitution');
                $this->addElement($institution, 'ram:BICID', preg_replace('/\s+/', '', $bic));
            }
        }

        $isSmallBusiness = $invoice->is_small_business !== null
            ? (bool)$invoice->is_small_business
            : app(\App\Services\Tax\TaxSettingService::class)->isSmallBusinessForYear($invoice->invoice_date?->year ?? now()->year);

        $defaultTaxRate = $isSmallBusiness ? 0.0 : (float)shop_setting('default_tax_rate', 19.0);
        $taxTotalAmount = abs((int)$invoice->tax_amount) / 100;
        $grandTotal = abs((int)$invoice->total) / 100;
        $netTotal = (abs((int)$invoice->total) - abs((int)$invoice->tax_amount)) / 100;

        // Gutschein-Rabatt (discount_amount) auf Dokumentenebene (BG-20; volume_discount ist bereits im unit_price der Positionen berücksichtigt)
        $totalDiscountCents = abs((int)($invoice->discount_amount ?? 0));
        $netDiscountAmount = 0.0;
        if ($totalDiscountCents > 0) {
            $grossDiscount = $totalDiscountCents / 100;
            $netDiscountAmount = ($isSmallBusiness || $defaultTaxRate == 0.0)
                ? $grossDiscount
                : ($grossDiscount / (1 + ($defaultTaxRate / 100)));
        }

        $catCode = $isSmallBusiness ? 'E' : 'S';
        if ($taxTotalAmount == 0 && !$isSmallBusiness) {
            $catCode = 'Z';
        }

        $tax = $this->addElement($settlement, 'ram:ApplicableTradeTax');
        $this->addElement($tax, 'ram:CalculatedAmount', number_format($taxTotalAmount, 2, '.', ''));
        $this->addElement($tax, 'ram:TypeCode', 'VAT');
        if ($isSmallBusiness) {
            $this->addElement($tax, 'ram:ExemptionReason', 'Umsatzsteuerfrei aufgrund der Kleinunternehmerregelung gemäß § 19 UStG');
        }
        $this->addElement($tax, 'ram:BasisAmount', number_format($netTotal, 2, '.', ''));
        $this->addElement($tax, 'ram:CategoryCode', $catCode);
        if ($isSmallBusiness) {
            $this->addElement($tax, 'ram:ExemptionReasonCode', 'VATEX-EU-19');
        }
        $this->addElement($tax, 'ram:RateApplicablePercent', $isSmallBusiness ? '0.00' : number_format($defaultTaxRate, 2, '.', ''));

        // Dokumenten-Rabatt (SpecifiedTradeAllowanceCharge)
        if ($netDiscountAmount > 0.004) {
            $allowance = $this->addElement($settlement, 'ram:SpecifiedTradeAllowanceCharge');
            $indicator = $this->addElement($allowance, 'ram:ChargeIndicator');
            $this->addElement($indicator, 'udt:Indicator', 'false');
            $this->addElement($allowance, 'ram:ActualAmount', number_format($netDiscountAmount, 2, '.', ''));
            $this->addElement($allowance, 'ram:Reason', 'Rabatt / Gutschein');
            $allowanceTax = $this->addElement($allowance, 'ram:CategoryTradeTax');
            $this->addElement($allowanceTax, 'ram:TypeCode', 'VAT');
            $this->addElement($allowanceTax, 'ram:CategoryCode', $catCode);
            $this->addElement($allowanceTax, 'ram:RateApplicablePercent', $isSmallBusiness ? '0.00' : number_format($defaultTaxRate, 2, '.', ''));
        }

        // Zahlungsbedingungen & Fälligkeitsdatum (BT-9 / BT-20)
        $paymentTerms = $this->addElement($settlement, 'ram:SpecifiedTradePaymentTerms');
        $dueDate = $invoice->due_date ?? ($invoice->invoice_date ? $invoice->invoice_date->copy()->addDays(14) : now()->addDays(14));
        $termsDesc = $invoice->paid_at
            ? 'Rechnungsbetrag bereits dankend erhalten am ' . $invoice->paid_at->format('d.m.Y') . '.'
            : 'Zahlbar ohne Abzug bis zum ' . $dueDate->format('d.m.Y') . '.';
        $this->addElement($paymentTerms, 'ram:Description', $termsDesc);
        $dueDateNode = $this->addElement($paymentTerms, 'ram:DueDateDateTime');
        $this->addElement($dueDateNode, 'udt:DateTimeString', $dueDate->format('Ymd'), ['format' => '102']);

        // Gesamtsummen
        $lineTotalBeforeDiscount = $netTotal + $netDiscountAmount;
        $summation = $this->addElement($settlement, 'ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $this->addElement($summation, 'ram:LineTotalAmount', number_format($lineTotalBeforeDiscount, 2, '.', ''));
        if ($netDiscountAmount > 0.004) {
            $this->addElement($summation, 'ram:AllowanceTotalAmount', number_format($netDiscountAmount, 2, '.', ''));
        }
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
            $textNode = $this->dom->createTextNode((string)$value);
            $element->appendChild($textNode);
        }

        foreach ($attributes as $key => $val) {
            $element->setAttribute($key, (string)$val);
        }

        $parent->appendChild($element);
        return $element;
    }
}
