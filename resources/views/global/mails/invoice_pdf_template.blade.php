<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 40px 40px 100px 40px; }
        body { font-family: sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
        .header { border-bottom: 2px solid #C5A059; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { width: 220px; }
        .invoice-title { font-size: 22px; font-weight: bold; color: #C5A059; text-transform: uppercase; text-align: right; margin-bottom: 5px; }
        .invoice-meta { text-align: right; font-size: 10px; color: #666; }
        .meta-table { width: 100%; margin-bottom: 30px; }
        .meta-table td { vertical-align: top; }
        .address-box { line-height: 1.5; }
        .shipping-address-box { margin-top: 20px; padding: 10px; background-color: #fafafa; border: 1px solid #eee; }
        .sender-small { font-size: 8px; color: #888; text-decoration: underline; margin-bottom: 5px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th { border-bottom: 2px solid #333; text-align: left; padding: 8px 5px; font-weight: bold; color: #000; text-transform: uppercase; font-size: 9px; }
        .items-table td { border-bottom: 1px solid #eee; padding: 10px 5px; vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .item-description { font-weight: bold; margin-bottom: 2px; font-size: 11px; }
        .item-config { font-size: 9px; color: #666; font-style: italic; }
        .totals-container { width: 100%; margin-top: 10px; }
        .totals-table { width: 45%; float: right; border-collapse: collapse; }
        .totals-table td { padding: 4px 5px; }
        .totals-label { text-align: right; color: #666; }
        .totals-value { text-align: right; width: 90px; white-space: nowrap; }
        .discount-row { color: #dc2626; }
        .total-row { font-weight: bold; font-size: 13px; color: #000; border-top: 2px solid #C5A059; padding-top: 8px !important; }

        /* FOOTER */
        .footer {
            position: fixed;
            bottom: -60px;
            left: 0;
            right: 0;
            height: 100px;
            text-align: center;
            font-size: 9px;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        .footer a { color: #C5A059; text-decoration: none; }

        .storno-badge { color: #dc2626; border: 2px solid #dc2626; padding: 5px 12px; display: inline-block; transform: rotate(-2deg); font-weight: bold; font-size: 14px; margin-bottom: 15px; }
        .clear { clear: both; }
        .subject { font-size: 13px; font-weight: bold; margin-bottom: 10px; margin-top: 20px; }
        .text-block { margin-bottom: 15px; white-space: pre-line; }

        /* STEMPEL */
        .paid-stamp {
            position: absolute;
            top: 25%;
            left: 50%;
            margin-left: -150px;
            transform: rotate(-20deg);
            border: 8px solid #16a34a;
            color: #16a34a;
            opacity: 0.12;
            font-size: 70px;
            font-weight: 900;
            padding: 10px 40px;
            text-transform: uppercase;
            z-index: -1;
        }
        .cancelled-stamp {
            position: absolute;
            top: 25%;
            left: 50%;
            margin-left: -150px;
            transform: rotate(-15deg);
            border: 8px solid #dc2626;
            color: #dc2626;
            opacity: 0.12;
            font-size: 60px;
            font-weight: 900;
            padding: 10px 40px;
            text-transform: uppercase;
            z-index: -1;
        }
    </style>
</head>
<body>

@php
    $isSmallBusiness = (bool)shop_setting('is_small_business', false);
    $ownerName = shop_setting('owner_name', 'Mein Seelenfunke');
    $proprietor = shop_setting('owner_proprietor', 'Alina Steinhauer');
    $ownerStreet = shop_setting('owner_street', 'Carl-Goerdeler-Ring 26');
    $ownerCity = shop_setting('owner_city', '38518 Gifhorn');
    $ownerEmail = shop_setting('owner_email', 'kontakt@mein-seelenfunke.de');
    $ownerWeb = shop_setting('owner_website', 'www.mein-seelenfunke.de');
    $ownerIban = shop_setting('owner_iban', 'Wird nachgereicht');
    $taxId = shop_setting('owner_tax_id', '19/143/11624');
    $ustId = shop_setting('owner_ust_id');
    $court = shop_setting('owner_court', 'Gifhorn');

    // Abweichende Lieferadresse prüfen
    $hasDifferentShipping = !empty($invoice->shipping_address) &&
                            serialize($invoice->billing_address) !== serialize($invoice->shipping_address);
@endphp

{{-- Stempel --}}
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
                    @if($invoice->reference_number) <strong>Referenz:</strong> {{ $invoice->reference_number }}<br> @endif
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
                <strong>@if(!empty($invoice->billing_address['company'])) {{ $invoice->billing_address['company'] }}<br> @endif
                    {{ $invoice->billing_address['first_name'] }} {{ $invoice->billing_address['last_name'] }}</strong><br>
                {{ $invoice->billing_address['address'] }}<br>
                @if(!empty($invoice->billing_address['address_addition'])) {{ $invoice->billing_address['address_addition'] }}<br> @endif
                {{ $invoice->billing_address['postal_code'] }} {{ $invoice->billing_address['city'] }}<br>
                {{ $invoice->billing_address['country'] }}
            </div>

            @if($hasDifferentShipping)
                <div class="shipping-address-box">
                    <div style="font-size: 8px; font-weight: bold; text-transform: uppercase; color: #888; margin-bottom: 3px;">Abweichende Lieferadresse:</div>
                    <div style="font-size: 10px; line-height: 1.3;">
                        @if(!empty($invoice->shipping_address['company'])) {{ $invoice->shipping_address['company'] }}<br> @endif
                        {{ $invoice->shipping_address['first_name'] }} {{ $invoice->shipping_address['last_name'] }}<br>
                        {{ $invoice->shipping_address['address'] }}<br>
                        {{ $invoice->shipping_address['postal_code'] }} {{ $invoice->shipping_address['city'] }}<br>
                        {{ $invoice->shipping_address['country'] }}
                    </div>
                </div>
            @endif
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

@if($invoice->type === 'cancellation')
    <div style="text-align: center;">
        <span class="storno-badge">Korrekturbeleg zu {{ $invoice->parent->invoice_number ?? 'Originalrechnung' }}</span>
    </div>
@endif

<div class="subject">{{ $invoice->subject }}</div>
<div class="text-block">
    {{ $invoice->header_text ?? "vielen Dank für Ihren Auftrag und das damit verbundene Vertrauen!\nHiermit stellen wir Ihnen folgende Leistungen in Rechnung:" }}
</div>

<table class="items-table">
    <thead>
    <tr>
        <th width="5%">Pos.</th>
        <th width="45%">Bezeichnung</th>
        <th width="10%" class="text-center">Menge</th>
        <th width="15%" class="text-right">Einzel</th>
        <th width="10%" class="text-center">USt.</th>
        <th width="15%" class="text-right">Gesamt</th>
    </tr>
    </thead>
    <tbody>
    @foreach($items as $index => $item)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>
                <div class="item-description">{{ is_object($item) ? $item->product_name : $item['product_name'] }}</div>
                @php $config = is_object($item) ? ($item->configuration ?? []) : ($item['configuration'] ?? []); @endphp
                @if(!empty($config['text']))
                    <div class="item-config">Gravur: "{{ $config['text'] }}"</div>
                @endif
            </td>
            <td class="text-center">{{ is_object($item) ? $item->quantity : $item['quantity'] }}</td>
            <td class="text-right">{{ number_format((is_object($item) ? $item->unit_price : $item['unit_price']) / 100, 2, ',', '.') }} €</td>
            <td class="text-center">{{ is_object($item) ? ($item->tax_rate ?? 19) : ($item['tax_rate'] ?? 19) }}%</td>
            <td class="text-right">{{ number_format((is_object($item) ? $item->total_price : $item['total_price']) / 100, 2, ',', '.') }} €</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="totals-container">
    <table class="totals-table">
        <tr>
            <td class="totals-label">Warenwert (Netto):</td>
            <td class="totals-value">{{ number_format(($invoice->subtotal - $invoice->tax_amount) / 100, 2, ',', '.') }} €</td>
        </tr>
        @if($invoice->volume_discount > 0)
            <tr class="discount-row">
                <td class="totals-label">Mengenrabatt:</td>
                <td class="totals-value">-{{ number_format($invoice->volume_discount / 100, 2, ',', '.') }} €</td>
            </tr>
        @endif
        @if($invoice->discount_amount > 0)
            <tr class="discount-row">
                <td class="totals-label">Gutschein-Rabatt:</td>
                <td class="totals-value">-{{ number_format($invoice->discount_amount / 100, 2, ',', '.') }} €</td>
            </tr>
        @endif
        @if($invoice->shipping_cost > 0)
            <tr>
                <td class="totals-label">Versandkosten:</td>
                <td class="totals-value">{{ number_format($invoice->shipping_cost / 100, 2, ',', '.') }} €</td>
            </tr>
        @endif

        @if(!$isSmallBusiness)
            <tr>
                <td class="totals-label">Enthaltene MwSt.:</td>
                <td class="totals-value">{{ number_format($invoice->tax_amount / 100, 2, ',', '.') }} €</td>
            </tr>
        @endif

        <tr class="total-row">
            <td class="totals-label">Gesamtbetrag:</td>
            <td class="totals-value">{{ number_format($invoice->total / 100, 2, ',', '.') }} €</td>
        </tr>
    </table>
</div>

<div class="clear" style="margin-top: 30px;">
    @if($isSmallBusiness)
        <p style="font-size: 10px; color: #666; font-style: italic; margin-bottom: 15px;">
            Hinweis: Umsatzsteuerfrei aufgrund der Kleinunternehmerregelung gemäß § 19 UStG.
        </p>
    @endif

    <div class="text-block">
        {{ $invoice->footer_text ?? "Der Rechnungsbetrag ist fällig bis zum " . ($invoice->due_date ? $invoice->due_date->format('d.m.Y') : now()->addDays(14)->format('d.m.Y')) . "." }}
    </div>

    <p>
        <strong>Zahlungsinformationen:</strong><br>
        Zahlungsart: {{ ucfirst($invoice->payment_method ?: ($invoice->order->payment_method ?? 'Stripe / Onlinezahlung')) }}<br>
        Status: @if($invoice->status == 'paid') Bezahlt am {{ $invoice->paid_at ? $invoice->paid_at->format('d.m.Y') : $invoice->invoice_date->format('d.m.Y') }} @else Offen @endif
    </p>
</div>

{{-- FOOTER --}}
@include("global.mails.partials.mail_footer")

</body>
</html>
