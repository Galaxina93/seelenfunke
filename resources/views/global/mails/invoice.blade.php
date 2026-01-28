<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: sans-serif; font-size: 13px; color: #333; line-height: 1.4; }
        .header { border-bottom: 2px solid #C5A059; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { width: 200px; }
        .invoice-title { font-size: 24px; font-weight: bold; color: #C5A059; text-transform: uppercase; text-align: right; }
        .meta-table { width: 100%; margin-bottom: 30px; }
        .meta-table td { vertical-align: top; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th { border-bottom: 1px solid #ccc; text-align: left; padding: 8px; font-weight: bold; color: #555; }
        .items-table td { border-bottom: 1px solid #eee; padding: 8px; }
        .text-right { text-align: right; }
        .totals-table { width: 40%; float: right; border-collapse: collapse; }
        .totals-table td { padding: 5px; }
        .total-row { font-weight: bold; font-size: 15px; border-top: 1px solid #333; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; height: 50px; border-top: 1px solid #eee; padding-top: 10px; font-size: 10px; color: #777; text-align: center; }
        .storno-badge { color: #dc2626; border: 2px solid #dc2626; padding: 5px 10px; display: inline-block; transform: rotate(-5deg); font-weight: bold; }
    </style>
</head>
<body>

<div class="header">
    <table width="100%">
        <tr>
            <td>
                {{-- Logo Pfad muss absolut sein für DomPDF oder base64 --}}
                <img src="{{ public_path('images/projekt/logo/mein-seelenfunke-logo.png') }}" class="logo" alt="Mein Seelenfunke">
            </td>
            <td class="text-right">
                <div class="invoice-title">
                    @if($isStorno) STORNO-RECHNUNG @else RECHNUNG @endif
                </div>
                <div>Nr. {{ $invoice->invoice_number }}</div>
                <div>Datum: {{ $invoice->invoice_date->format('d.m.Y') }}</div>
            </td>
        </tr>
    </table>
</div>

<table class="meta-table">
    <tr>
        <td width="50%">
            <strong>Empfänger:</strong><br>
            {{ $invoice->billing_address['company'] ?? '' }}<br>
            {{ $invoice->billing_address['first_name'] }} {{ $invoice->billing_address['last_name'] }}<br>
            {{ $invoice->billing_address['address'] }}<br>
            {{ $invoice->billing_address['postal_code'] }} {{ $invoice->billing_address['city'] }}<br>
            {{ $invoice->billing_address['country'] }}
        </td>
        <td width="50%" class="text-right">
            <strong>Lieferant:</strong><br>
            Mein Seelenfunke<br>
            Inh. Alina Steinhauer<br>
            Carl-Goerdeler-Ring 26<br>
            38518 Gifhorn<br>
            Deutschland
        </td>
    </tr>
</table>

@if($isStorno)
    <div style="margin-bottom: 20px;">
        <span class="storno-badge">Gutschrift zur Rechnung {{ $invoice->parent->invoice_number ?? 'Original' }}</span>
    </div>
@endif

<table class="items-table">
    <thead>
    <tr>
        <th>Pos.</th>
        <th>Bezeichnung</th>
        <th class="text-right">Menge</th>
        <th class="text-right">Einzel (Brutto)</th>
        <th class="text-right">Gesamt</th>
    </tr>
    </thead>
    <tbody>
    @foreach($items as $index => $item)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>
                {{ $item->product_name }}
                @if(!empty($item->configuration))
                    <br><span style="font-size: 10px; color: #777;">Individuelle Konfiguration</span>
                @endif
            </td>
            <td class="text-right">{{ $item->quantity }}</td>
            <td class="text-right">{{ number_format($item->unit_price / 100, 2, ',', '.') }} €</td>
            <td class="text-right">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals-table">
    <tr>
        <td>Netto:</td>
        <td class="text-right">{{ number_format(($invoice->subtotal - $invoice->tax_amount) / 100, 2, ',', '.') }} €</td>
    </tr>
    <tr>
        <td>MwSt. (19%):</td>
        <td class="text-right">{{ number_format($invoice->tax_amount / 100, 2, ',', '.') }} €</td>
    </tr>
    <tr>
        <td>Versand:</td>
        <td class="text-right">{{ number_format($invoice->shipping_cost / 100, 2, ',', '.') }} €</td>
    </tr>
    <tr class="total-row">
        <td>Gesamtbetrag:</td>
        <td class="text-right">{{ number_format($invoice->total / 100, 2, ',', '.') }} €</td>
    </tr>
</table>

<div style="clear: both; margin-top: 50px;">
    <p>Zahlungsart: {{ ucfirst($invoice->order->payment_method ?? 'Stripe') }}<br>
        Zahlungsstatus: @if($invoice->status == 'paid') Bezahlt am {{ $invoice->paid_at->format('d.m.Y') }} @else Offen @endif</p>

    @if($isStorno)
        <p>Der Betrag wird Ihnen auf das ursprüngliche Zahlungsmittel erstattet.</p>
    @endif
</div>

<div class="footer">
    Mein Seelenfunke | Inh. Alina Steinhauer | Steuernummer: folgt | Gerichtsstand: Gifhorn<br>
    Vielen Dank für Ihre Bestellung!
</div>

</body>
</html>
