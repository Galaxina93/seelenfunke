<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 40px 40px 100px 40px; }
        body { font-family: sans-serif; font-size: 12px; color: #333; line-height: 1.4; }
        .header { border-bottom: 2px solid #C5A059; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { width: 180px; }
        .invoice-title { font-size: 24px; font-weight: bold; color: #C5A059; text-transform: uppercase; text-align: right; margin-bottom: 5px; }
        .invoice-meta { text-align: right; font-size: 11px; color: #666; }
        .meta-table { width: 100%; margin-bottom: 40px; }
        .meta-table td { vertical-align: top; }
        .address-box { line-height: 1.6; }
        .sender-small { font-size: 9px; color: #888; text-decoration: underline; margin-bottom: 5px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th { border-bottom: 2px solid #333; text-align: left; padding: 10px 8px; font-weight: bold; color: #000; text-transform: uppercase; font-size: 10px; }
        .items-table td { border-bottom: 1px solid #eee; padding: 12px 8px; vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .item-description { font-weight: bold; margin-bottom: 3px; }
        .item-config { font-size: 10px; color: #666; font-style: italic; }
        .totals-container { width: 100%; margin-top: 20px; }
        .totals-table { width: 55%; float: right; border-collapse: collapse; }
        .totals-table td { padding: 5px 8px; }
        .totals-label { text-align: right; color: #666; }
        .totals-value { text-align: right; width: 100px; }
        .discount-row { color: #dc2626; }
        .total-row { font-weight: bold; font-size: 14px; color: #000; border-top: 2px solid #C5A059; padding-top: 10px !important; }

        /* FOOTER */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 11px;
            color: #999;
        }
        .footer a { color: #C5A059; text-decoration: none; }

        .storno-badge { color: #dc2626; border: 2px solid #dc2626; padding: 8px 15px; display: inline-block; transform: rotate(-3deg); font-weight: bold; font-size: 16px; margin-bottom: 20px; }
        .clear { clear: both; }
        .subject { font-size: 14px; font-weight: bold; margin-bottom: 15px; }
        .text-block { margin-bottom: 20px; white-space: pre-line; }

        /* BEZAHLT STEMPEL AUS STAGE */
        .paid-stamp {
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            border: 8px solid rgba(22, 163, 74, 0.15);
            color: rgba(22, 163, 74, 0.15);
            font-size: 90px;
            font-weight: 900;
            padding: 20px 40px;
            text-transform: uppercase;
            z-index: -1;
            pointer-events: none;
        }
    </style>
</head>
<body>

@php
    $isSmallBusiness = (bool)shop_setting('is_small_business', false);
    $ownerName = shop_setting('owner_proprietor', 'Alina Steinhauer');
    $taxId = shop_setting('owner_tax_id', '19/143/11624');
    $ustId = shop_setting('owner_ust_id', 'folgt');
@endphp

{{-- Bezahlt-Stempel Hintergrund --}}
@if($invoice->status === 'paid')
    <div class="paid-stamp">Bezahlt</div>
@endif

<div class="header">
    <table width="100%">
        <tr>
            <td>
                <img src="{{ public_path('images/projekt/logo/mein-seelenfunke-logo.png') }}" class="logo" alt="Mein Seelenfunke">
            </td>
            <td class="text-right">
                <div class="invoice-title">
                    @if($invoice->type === 'cancellation') STORNO-RECHNUNG @else RECHNUNG @endif
                </div>
                <div class="invoice-meta">
                    <strong>Nummer:</strong> {{ $invoice->invoice_number }}<br>
                    <strong>Datum:</strong> {{ $invoice->invoice_date->format('d.m.Y') }}<br>
                    @if($invoice->delivery_date) <strong>Leistung:</strong> {{ $invoice->delivery_date->format('d.m.Y') }}<br> @endif
                    @if($invoice->reference_number) <strong>Referenz:</strong> {{ $invoice->reference_number }}<br> @endif
                </div>
            </td>
        </tr>
    </table>
</div>

<table class="meta-table">
    <tr>
        <td width="55%">
            <div class="sender-small">Mein Seelenfunke · Carl-Goerdeler-Ring 26 · 38518 Gifhorn</div>
            <div class="address-box">
                <strong>@if(!empty($invoice->billing_address['company'])) {{ $invoice->billing_address['company'] }}<br> @endif
                    {{ $invoice->billing_address['first_name'] }} {{ $invoice->billing_address['last_name'] }}</strong><br>
                {{ $invoice->billing_address['address'] }}<br>
                @if(!empty($invoice->billing_address['address_addition'])) {{ $invoice->billing_address['address_addition'] }}<br> @endif
                {{ $invoice->billing_address['postal_code'] }} {{ $invoice->billing_address['city'] }}<br>
                {{ $invoice->billing_address['country'] }}
            </div>
        </td>
        <td width="45%" class="text-right address-box">
            <strong>Mein Seelenfunke</strong><br>
            Inh. {{ $ownerName }}<br>
            Carl-Goerdeler-Ring 26<br>
            38518 Gifhorn<br>
            Deutschland<br><br>
            E-Mail: kontakt@mein-seelenfunke.de
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
    vielen Dank für Ihren Auftrag und das damit verbundene Vertrauen!
    Hiermit stellen wir Ihnen folgende Leistungen in Rechnung:
</div>

<table class="items-table">
    <thead>
    <tr>
        <th width="5%">Pos..</th>
        <th width="45%">Bezeichnung</th>
        <th width="10%" class="text-center">Menge</th>
        <th width="15%" class="text-right">Einzel</th>
        <th width="10%" class="text-center">USt.</th>
        <th width="15%" class="text-right">Gesamt</th>
    </tr>
    </thead>
    <tbody>
    @php $actualItems = $invoice->custom_items ?? $invoice->order->items ?? []; @endphp
    @foreach($actualItems as $index => $item)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>
                <div class="item-description">{{ $item->product_name ?? $item['product_name'] }}</div>
                @if(isset($item->configuration['text']) && !empty($item->configuration['text']))
                    <div class="item-config">Gravur: "{{ $item->configuration['text'] }}"</div>
                @endif
            </td>
            <td class="text-center">{{ $item->quantity ?? $item['quantity'] }}</td>
            <td class="text-right">{{ number_format(($item->unit_price ?? $item['unit_price']) / 100, 2, ',', '.') }} €</td>
            <td class="text-center">{{ $item->tax_rate ?? $item['tax_rate'] ?? 19 }}%</td>
            <td class="text-right">{{ number_format(($item->total_price ?? $item['total_price']) / 100, 2, ',', '.') }} €</td>
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
        @else
            <tr><td colspan="2" class="text-right" style="font-size: 10px; color: #666; padding-top: 10px;">Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.</td></tr>
        @endif
        <tr class="total-row">
            <td class="totals-label">Gesamtbetrag:</td>
            <td class="totals-value">{{ number_format($invoice->total / 100, 2, ',', '.') }} €</td>
        </tr>
    </table>
</div>

<div class="clear" style="margin-top: 40px;">
    <div class="text-block">
        Der Rechnungsbetrag ist fällig bis zum {{$invoice->due_date ? $invoice->due_date->format('d.m.Y') : ''}}
    </div>
    <p>
        <strong>Zahlungsinformationen:</strong><br>
        Zahlungsart: {{ ucfirst($invoice->payment_method ?: ($invoice->order->payment_method ?? 'Banküberweisung')) }}<br>
        Status: @if($invoice->status == 'paid') Bezahlt am {{ $invoice->paid_at ? $invoice->paid_at->format('d.m.Y') : $invoice->invoice_date->format('d.m.Y') }} @else Offen @endif
    </p>
</div>

{{-- FOOTER --}}
@include('global.mails.partials.mail_footer')

</body>
</html>
