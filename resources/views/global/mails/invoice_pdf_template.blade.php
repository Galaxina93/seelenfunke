<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 40px 40px 140px 40px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #333; line-height: 1.4; position: relative; }
        .header { border-bottom: 2px solid #C5A059; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { width: 220px; }
        .invoice-title { font-size: 22px; font-weight: bold; color: #C5A059; text-transform: uppercase; text-align: right; margin-bottom: 5px; }
        .invoice-meta { text-align: right; font-size: 10px; color: #666; }
        .meta-table { width: 100%; margin-bottom: 30px; }
        .meta-table td { vertical-align: top; }
        .address-box { line-height: 1.5; }
        .sender-small { font-size: 8px; color: #888; text-decoration: underline; margin-bottom: 5px; }

        /* Styles für Partials-Kompatibilität */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .clear { clear: both; }
        .subject { font-size: 13px; font-weight: bold; margin-bottom: 10px; margin-top: 20px; }
        .text-block { margin-bottom: 15px; white-space: pre-line; }

        /* STEMPEL */
        .paid-stamp { position: absolute; top: 20%; left: 50%; margin-left: -150px; transform: rotate(-20deg); border: 8px solid #16a34a; color: #16a34a; opacity: 0.12; font-size: 70px; font-weight: 900; padding: 10px 40px; text-transform: uppercase; z-index: -1; }
        .cancelled-stamp { position: absolute; top: 20%; left: 50%; margin-left: -150px; transform: rotate(-15deg); border: 8px solid #dc2626; color: #dc2626; opacity: 0.12; font-size: 60px; font-weight: 900; padding: 10px 40px; text-transform: uppercase; z-index: -1; }

        /* ZAHLUNGSHINWEIS BOX */
        .payment-info-box {
            margin-top: 30px;
            padding: 15px;
            background-color: #f9f9f9;
            border-left: 4px solid #C5A059;
            font-size: 11px;
            color: #555;
        }

        /* FOOTER DESIGN */
        .footer {
            position: fixed;
            bottom: -100px;
            left: 0;
            right: 0;
            border-top: 1px solid #eee;
            padding-top: 20px;
            color: #777;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-col {
            width: 33.33%;
            vertical-align: top;
            font-size: 9px;
            line-height: 1.6;
        }
        .footer-heading {
            color: #C5A059;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
            display: block;
        }
        .footer a {
            color: #777;
            text-decoration: none;
        }
    </style>
</head>
<body>

@php
    // Generiere formatierte Daten aus dem Model via Trait
    $data = $invoice->toFormattedArray();

    // Dynamische Shop-Einstellungen laden
    $ownerName     = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));
    $proprietor    = shop_setting('owner_proprietor', 'Alina Steinhauer');
    $ownerStreet   = shop_setting('company_street')
        ? trim(shop_setting('company_street') . ' ' . shop_setting('company_street_number', '26'))
        : shop_setting('owner_street', 'Carl-Goerdeler-Ring 26');
    $ownerCity     = shop_setting('company_city')
        ? trim(shop_setting('company_zip', '38518') . ' ' . shop_setting('company_city'))
        : shop_setting('owner_city', '38518 Gifhorn');
    $ownerEmail    = shop_setting('owner_email_invoices') ?: shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
    $ownerWeb      = shop_setting('owner_website', 'www.mein-seelenfunke.de');
    $ownerBankName = shop_setting('owner_bank_name', 'Volksbank BraWo');
    if ($ownerBankName === 'OWNER_BANK_NAME') {
        $ownerBankName = 'Volksbank BraWo';
    }
    $ownerIban     = shop_setting('owner_iban', '');
    $ownerBic      = shop_setting('owner_bic', '');
    $taxId         = shop_setting('owner_tax_id', '19/143/11624');
    $ustId         = shop_setting('company_ust_id', shop_setting('owner_ust_id'));
    $court         = shop_setting('owner_court', 'Gifhorn');
    $isSmallBusiness = $invoice->is_small_business !== null
        ? (bool)$invoice->is_small_business
        : app(\App\Services\Tax\TaxSettingService::class)->isSmallBusinessForYear($invoice->invoice_date?->year ?? now()->year);

    $invoiceSvc = app(\App\Services\InvoiceService::class);
    $giroCodeDataUri = (!in_array($invoice->type, ['cancellation', 'credit_note']) && !$invoice->paid_at && !empty($ownerIban))
        ? $invoiceSvc->generateGiroCodeSvgDataUri($invoice, (string)$ownerName, (string)$ownerIban, (string)$ownerBic, 100)
        : '';
    $portalQrDataUri = (!in_array($invoice->type, ['cancellation', 'credit_note']) && $invoice->paid_at)
        ? $invoiceSvc->generatePortalQrSvgDataUri($invoice, (string)$ownerWeb, 76)
        : '';
@endphp

{{-- Stempel-Logik --}}
@if($invoice->paid_at && !in_array($invoice->type, ['cancellation', 'credit_note']))
    <div class="paid-stamp">Bezahlt</div>
@endif

@if($invoice->status === 'cancelled' || $invoice->type === 'cancellation')
    <div class="cancelled-stamp">Storniert</div>
@endif

<div class="header">
    <table width="100%">
        <tr>
            <td>
                {{-- Logo Pfad fixen für PDF Generierung --}}
                <img src="{{ public_path('shop/projekt/logo/mein-seelenfunke-logo.svg') }}" class="logo" alt="{{ $ownerName }}">
            </td>
            <td class="text-right">
                <div class="invoice-title">
                    @if($invoice->type === 'cancellation') RECHNUNGSKORREKTUR / STORNO @elseif($invoice->type === 'credit_note') RECHNUNGSKORREKTUR / GUTSCHRIFT @else RECHNUNG @endif
                </div>
                <div class="invoice-meta">
                    <strong>Nummer:</strong> {{ $invoice->invoice_number }}<br>
                    <strong>Datum:</strong> {{ $invoice->invoice_date->format('d.m.Y') }}<br>
                    @if(in_array($invoice->type, ['cancellation', 'credit_note']) && $invoice->parent)
                        <strong>Ursprungsrechnung:</strong> {{ $invoice->parent->invoice_number }} ({{ $invoice->parent->invoice_date ? $invoice->parent->invoice_date->format('d.m.Y') : '' }})<br>
                    @endif
                    <strong>Leistungsdatum:</strong> {{ ($invoice->delivery_date ?? $invoice->invoice_date)->format('d.m.Y') }}<br>
                    <strong>Kunden-Nr.:</strong> {{ $invoice->customer_id ? strtoupper(explode('-', $invoice->customer_id)[0]) : 'Gast' }}
                </div>
            </td>
        </tr>
    </table>
</div>

<table class="meta-table">
    <tr>
        <td width="55%">
            <div class="sender-small">{{ $ownerName }} · {{ $ownerStreet }} · {{ $ownerCity }}</div>
            <div class="address-box">
                @if(!empty($data['contact']['firma'])) <strong>{{ $data['contact']['firma'] }}</strong><br> @endif
                <strong>{{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }}</strong><br>
                {{ $data['billing_address']['address'] ?? '' }}<br>
                @if(!empty($data['billing_address']['address_addition'])) {{ $data['billing_address']['address_addition'] }}<br> @endif
                {{ $data['billing_address']['postal_code'] ?? '' }} {{ $data['billing_address']['city'] ?? '' }}<br>
                {{ $data['billing_address']['country'] ?? 'DE' }}
            </div>
        </td>
        <td width="45%" class="text-right address-box">
            <strong>{{ $ownerName }}</strong><br>
            Inh. {{ $proprietor }}<br>
            {{ $ownerStreet }}<br>
            {{ $ownerCity }}<br>
            Deutschland<br><br>
            E-Mail: {{ $ownerEmail }}<br>
            Web: {{ str_replace(['http://', 'https://'], '', $ownerWeb) }}
        </td>
    </tr>
</table>

<div class="subject">{{ $invoice->subject ?? 'Rechnung zur Bestellung #' . ($invoice->order ? $invoice->order->order_number : '') }}</div>

<div class="text-block">
    {{ $invoice->header_text ?? "vielen Dank für deinen Auftrag und dein Vertrauen!\nHiermit stellen wir dir folgende Leistungen in Rechnung:" }}
</div>

{{-- ARTIKELLISTE & PREISE --}}
@include('global.mails.partials.mail_item_list', ['data' => $data, 'isPdf' => true])
@include('global.mails.partials.mail_price_list', ['data' => $data, 'isPdf' => true, 'invoice' => $invoice, 'order' => $invoice->order])


{{-- [NEU] INTELLIGENTE ZAHLUNGS-INFO BOX --}}
<div class="payment-info-box">
    @if(in_array($invoice->type, ['cancellation', 'credit_note']))
        {{-- FALL: GUTSCHRIFT / STORNO --}}
        <p style="margin: 0; font-weight: bold;">
            Der Betrag wird Ihnen wie vereinbart erstattet oder mit offenen Forderungen verrechnet.
        </p>
    @elseif($invoice->paid_at)
        {{-- FALL 1: BEREITS BEZAHLT --}}
        <table width="100%" style="border-collapse: collapse;">
            <tr>
                <td style="vertical-align: middle;">
                    <p style="margin: 0; color: #16a34a; font-weight: bold;">
                        Der Rechnungsbetrag wurde bereits am {{ $invoice->paid_at->format('d.m.Y') }} vollständig beglichen. Vielen Dank!
                    </p>
                    <p style="margin: 4px 0 0 0; font-size: 9px; color: #666;">
                        Beleg-Referenz: {{ $invoice->invoice_number }} · Ihren digitalen Beleg und Bestellstatus finden Sie jederzeit im Kundenportal.
                    </p>
                </td>
                @if(!empty($portalQrDataUri))
                <td style="width: 110px; text-align: right; vertical-align: middle;">
                    <div style="display: inline-block; text-align: center; background: #fff; padding: 6px; border: 1px solid #e5e7eb; border-radius: 6px;">
                        <img src="{{ $portalQrDataUri }}" alt="Kundenportal QR-Code" style="width: 68px; height: 68px; display: block; margin: 0 auto;">
                        <span style="display: block; font-size: 7px; color: #666; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.4px;">Online-Beleg</span>
                    </div>
                </td>
                @endif
            </tr>
        </table>
    @else
        {{-- FALL 2: NOCH OFFEN --}}
        <table width="100%" style="border-collapse: collapse;">
            <tr>
                <td style="vertical-align: top; padding-right: 15px;">
                    <p style="margin: 0 0 10px 0; font-weight: bold;">
                        Der Rechnungsbetrag ist fällig bis zum {{ $invoice->due_date ? $invoice->due_date->format('d.m.Y') : now()->addDays(14)->format('d.m.Y') }}.
                    </p>

                    @if($invoice->order && $invoice->order->payment_method === 'stripe_link')
                        {{-- OPTION A: STRIPE ZAHLUNGSLINK --}}
                        <p style="margin: 0;">
                            Bitte nutzen Sie zur Zahlung den <strong>Link in der E-Mail</strong>, die wir Ihnen gesendet haben.<br>
                            Alternativ können Sie den Betrag auch auf unten genanntes Konto überweisen (oder per GiroCode scannen):<br><br>
                            Empfänger: <strong>{{ $ownerName }}</strong> · IBAN: <strong>{{ $ownerIban }}</strong>
                        </p>
                    @else
                        {{-- OPTION B: KLASSISCHE ÜBERWEISUNG --}}
                        <p style="margin: 0;">
                            Bitte überweisen Sie den Betrag unter Angabe der Rechnungsnummer <strong>{{ $invoice->invoice_number }}</strong> auf folgendes Konto:<br><br>
                            Empfänger: <strong>{{ $ownerName }}</strong><br>
                            IBAN: <strong>{{ $ownerIban }}</strong><br>
                            Bank: {{ $ownerBankName ?: 'Volksbank BraWo' }}{{ $ownerBic ? ' (BIC: ' . $ownerBic . ')' : '' }}
                        </p>
                    @endif
                </td>
                @if(!empty($giroCodeDataUri))
                <td style="width: 125px; text-align: right; vertical-align: middle;">
                    <div style="display: inline-block; text-align: center; background: #fff; padding: 7px; border: 1px solid #C5A059; border-radius: 6px;">
                        <img src="{{ $giroCodeDataUri }}" alt="EPC GiroCode QR" style="width: 88px; height: 88px; display: block; margin: 0 auto;">
                        <span style="display: block; font-size: 7.5px; font-weight: bold; color: #C5A059; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.4px;">GiroCode (SEPA-QR)</span>
                        <span style="display: block; font-size: 7px; color: #666; line-height: 1.2;">Mit Banking-App scannen</span>
                    </div>
                </td>
                @endif
            </tr>
        </table>
    @endif
</div>

{{-- FOOTER --}}
<div class="footer">
    <table class="footer-table">
        <tr>
            <td class="footer-col">
                <span class="footer-heading">Unternehmen</span>
                <strong>{{ $ownerName }}</strong><br>
                Inhaberin {{ $proprietor }}<br>
                {{ $ownerStreet }}<br>
                {{ $ownerCity }}
            </td>
            <td class="footer-col">
                <span class="footer-heading">Kontakt</span>
                E-Mail: {{ $ownerEmail }}<br>
                Web: {{ str_replace(['http://', 'https://'], '', $ownerWeb) }}<br>
                @if(!empty($ustId)) USt-IdNr.: {{ $ustId }}<br> @endif
                Steuernummer: {{ $taxId }}
            </td>
            <td class="footer-col">
                <span class="footer-heading">Bankverbindung</span>
                @if(!empty($ownerBankName)) Bank: {{ $ownerBankName }}<br> @endif
                @if(!empty($ownerIban)) IBAN: {{ $ownerIban }}<br> @endif
                @if($ownerBic) BIC: {{ $ownerBic }}<br> @endif
                Gerichtsstand (für Kaufleute): {{ $court }}<br>
                @if(isset($isSmallBusiness) && $isSmallBusiness)
                    <span style="font-size: 8px; font-style: italic; color: #999;">Umsatzsteuerfrei gem. § 19 UStG.</span>
                @endif
            </td>
        </tr>
    </table>
</div>

</body>
</html>
