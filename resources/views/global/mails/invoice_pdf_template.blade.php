<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 40px 40px 140px 40px; }
        body { font-family: sans-serif; font-size: 11px; color: #333; line-height: 1.4; position: relative; }
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

        /* MODERN FOOTER DESIGN - MEIN SEELENFUNKE */
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
        .footer-bottom-links {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            border-top: 1px solid #f9f9f9;
            padding-top: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .footer-bottom-links a {
            margin: 0 15px;
            color: #aaa;
        }
    </style>
</head>
<body>

@php
    // Generiere formatierte Daten aus dem Model via Trait
    $data = $invoice->toFormattedArray();

    // Dynamische Shop-Einstellungen laden
    $ownerName     = shop_setting('owner_name', 'Mein Seelenfunke');
    $proprietor    = shop_setting('owner_proprietor', 'Alina Steinhauer');
    $ownerStreet   = shop_setting('owner_street', 'Carl-Goerdeler-Ring 26');
    $ownerCity     = shop_setting('owner_city', '38518 Gifhorn');
    $ownerEmail    = shop_setting('owner_email', 'kontakt@mein-seelenfunke.de');
    $ownerWeb      = shop_setting('owner_website', 'www.mein-seelenfunke.de');
    $ownerIban     = shop_setting('owner_iban', 'Wird nachgereicht');
    $ownerBic      = shop_setting('owner_bic', '');
    $taxId         = shop_setting('owner_tax_id', '19/143/11624');
    $ustId         = shop_setting('owner_ust_id');
    $court         = shop_setting('owner_court', 'Gifhorn');
    $isSmallBusiness = (bool)shop_setting('is_small_business', false);
@endphp

{{-- Stempel-Logik --}}
@if($invoice->status === 'paid' && $invoice->type !== 'cancellation')
    <div class="paid-stamp">Bezahlt</div>
@endif

@if($invoice->status === 'cancelled' || $invoice->type === 'cancellation')
    <div class="cancelled-stamp">Storniert</div>
@endif

<div class="header">
    <table width="100%">
        <tr>
            <td>
                <img src="{{ public_path('images/projekt/logo/mein-seelenfunke-logo.png') }}" class="logo" alt="{{ $ownerName }}">
            </td>
            <td class="text-right">
                <div class="invoice-title">
                    @if($invoice->type === 'cancellation') STORNO-RECHNUNG @else RECHNUNG @endif
                </div>
                <div class="invoice-meta">
                    <strong>Nummer:</strong> {{ $invoice->invoice_number }}<br>
                    <strong>Datum:</strong> {{ $invoice->invoice_date->format('d.m.Y') }}<br>
                    @if($invoice->delivery_date) <strong>Leistungsdatum:</strong> {{ $invoice->delivery_date->format('d.m.Y') }}<br> @endif
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
                <strong>
                    @if(!empty($data['contact']['firma'])) {{ $data['contact']['firma'] }}<br> @endif
                    {{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }}
                </strong><br>
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

<div class="subject">{{ $invoice->subject ?? 'Rechnung' }}</div>
<div class="text-block">
    {{ $invoice->header_text ?? "vielen Dank für deinen Auftrag und dein Vertrauen!\nHiermit stellen wir dir folgende Leistungen in Rechnung:" }}
</div>

{{-- ZENTRALE PARTIALS NUTZEN --}}
@include('global.mails.partials.mail_item_list', ['data' => $data])
@include('global.mails.partials.mail_price_list', ['data' => $data])

<div class="clear" style="margin-top: 30px;">
    <div class="text-block">
        {{ $invoice->footer_text ?? "Der Rechnungsbetrag ist fällig bis zum " . ($invoice->due_date ? $invoice->due_date->format('d.m.Y') : now()->addDays(14)->format('d.m.Y')) . "." }}
    </div>

    <p style="font-size: 10px; color: #555;">
        <strong>Zahlungsinformationen:</strong><br>
        Zahlungsart: {{ ucfirst($invoice->payment_method ?: 'Onlinezahlung') }}<br>
        Status: {{ $invoice->status === 'paid' ? 'Bezahlt' : 'Offen' }}
    </p>
</div>

{{-- FOOTER - MODERN & DYNAMISCH --}}
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
                E-Mail: <a href="mailto:{{ $ownerEmail }}">{{ $ownerEmail }}</a><br>
                Web: <a href="{{ url('/') }}">{{ str_replace(['http://', 'https://'], '', $ownerWeb) }}</a><br>
                USt-IdNr.: {{ $ustId ?? 'n.a.' }}<br>
                Steuernummer: {{ $taxId }}
            </td>
            <td class="footer-col">
                <span class="footer-heading">Bankverbindung</span>
                IBAN: {{ $ownerIban }}<br>
                @if($ownerBic) BIC: {{ $ownerBic }}<br> @endif
                Gerichtsstand: {{ $court }}<br>
                @if(isset($isSmallBusiness) && $isSmallBusiness)
                    <span style="font-size: 8px; font-style: italic; color: #999;">Umsatzsteuerfrei gem. § 19 UStG.</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="footer-bottom-links">
        <a href="{{ url('/agb') }}">AGB</a>
        <a href="{{ url('/datenschutz') }}">Datenschutz</a>
        <a href="{{ url('/impressum') }}">Impressum</a>
    </div>
</div>

</body>
</html>
