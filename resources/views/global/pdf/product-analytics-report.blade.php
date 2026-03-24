<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Produkt Analyse Bericht</title>
    <style>
        @page { size: A4 landscape; margin: 6mm 12mm 15mm 12mm; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 8px; color: #2d3748; margin: 0; padding: 0; background-color: #ffffff; }

        #footer { position: fixed; bottom: -12mm; left: 0px; right: 0px; height: 10mm; font-size: 7px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 4px; }
        .footer-table { width: 100%; border-collapse: collapse; border: none; }
        .footer-table td { border: none; padding: 0; background: transparent; }
        .page-number:after { content: "Seite " counter(page); font-weight: bold; }

        .header { margin-bottom: 4px; padding-bottom: 4px; border-bottom: 1px solid #3b82f6; position: relative; }
        .logo { height: 90px; position: absolute; right: 0; top: -15px; }

        .doc-title { font-size: 18px; font-weight: bold; color: #111827; margin: 0 0 2px 0; }

        .erp-tag {
            display: inline-block; background: linear-gradient(135deg, #3b82f6, #93c5fd, #3b82f6);
            color: #111827; padding: 3px 6px; font-size: 7px; font-weight: bold;
            border-radius: 3px; text-transform: uppercase; margin-bottom: 2px; border: 1px solid #2563eb;
        }

        .plan-year-title { font-size: 22px; font-weight: 900; color: #111827; margin-top: 10px; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 1px; }
        .section-heading { font-size: 11px; font-weight: bold; color: #111827; margin-top: 18px; margin-bottom: 6px; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; padding-bottom: 2px;}

        .doc-meta { font-size: 8px; color: #6b7280; margin-top: 2px; }

        table { border-collapse: collapse; margin-bottom: 10px; page-break-inside: auto; width: 100%; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 4px; text-align: left; vertical-align: top; font-size: 8px;}
        th { background-color: #f3f4f6; font-weight: bold; text-align: left; color: #374151; font-size: 8px; text-transform: uppercase;}
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .statement-box { margin-top: 5px; margin-bottom: 10px; padding: 10px; border-left: 3px solid #3b82f6; background-color: #eff6ff; page-break-inside: avoid; }
        .statement-box h3 { font-size: 11px; color: #1e3a8a; margin-top: 0; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 1px; }
        .statement-box p { font-size: 8px; line-height: 1.5; color: #1e3a8a; margin-bottom: 4px; }

        a { color: #3b82f6; text-decoration: none; }
        
        .loss-summary { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .loss-summary td { width: 50%; padding: 8px; text-align: center; border: 1px solid #fca5a5; background: #fef2f2; }
        .loss-val { font-size: 12px; font-weight: bold; color: #991b1b; margin-top: 2px; }
        .loss-label { font-size: 8px; color: #b91c1c; text-transform: uppercase; font-weight: bold; }
    </style>
</head>
<body>

<div id="footer">
    <table class="footer-table">
        <tr>
            <td style="text-align: left; width: 33%;"><strong>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}</strong> | Inh. {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}</td>
            <td style="text-align: center; width: 33%; color: #111827;" class="page-number"></td>
            <td style="text-align: right; width: 33%;"><a href="{{ url('/') }}">{{ str_replace(['http://', 'https://'], '', shop_setting('owner_website', 'www.mein-seelenfunke.de')) }}</a></td>
        </tr>
    </table>
</div>

<div class="header">
    <img src="{{ public_path('images/projekt/logo/mein-seelenfunke-logo.svg') }}" alt="Logo" class="logo">
    <div class="doc-title">Produkt Analyse & Unit Economics</div>
    <div class="erp-tag">Generiert durch: Seelenfunke ERP Analytics</div>
    <div class="plan-year-title">REPORTING-DATUM: {{ $date }}</div>
    <div class="doc-meta">
        <strong>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}</strong>, {{ shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}, {{ shop_setting('owner_city', '38518 Gifhorn') }}
    </div>
</div>

<div class="statement-box">
    <h3 style="margin-bottom: 2px;">Management Summary</h3>
    <p>Dieser Bericht kombiniert die Margen-Analyse, die Verkaufsgeschwindigkeiten und die Schwund-Logs zu einer ganzheitlichen Geschäftsübersicht aller aktiven physischen Produkte.</p>
</div>

<!-- Section 1: Wahre Zahlen -->
<div class="section-heading" style="border-bottom-color: #d97706; color: #d97706;">1. Stückkosten-Analyse (Reingewinn)</div>
<table>
    <thead>
        <tr>
            <th style="width: 30%;">Produkt</th>
            <th style="width: 10%;">Netto VK</th>
            <th style="width: 10%;">EK Preis</th>
            <th style="width: 12%;">Strom & Verschleiß</th>
            <th style="width: 13%;">Verpackung & Versand</th>
            <th style="width: 10%; text-align: right;">Total Kosten</th>
            <th style="width: 15%; text-align: right; background: #fef3c7;">Netto EK & Marge</th>
        </tr>
    </thead>
    <tbody>
        @foreach($trueCostData as $tc)
            <tr>
                <td><strong style="font-size: 9px; color: #111827;">{{ $tc['name'] }}</strong></td>
                <td>{{ number_format($tc['net_price'], 2, ',', '.') }} €</td>
                <td>{{ number_format($tc['purchase_price'], 2, ',', '.') }} €</td>
                <td>{{ number_format($tc['laser_cost'], 2, ',', '.') }} €</td>
                <td>
                    VP: {{ number_format($tc['packaging_cost'], 2, ',', '.') }} €<br>
                    VS: {{ number_format($tc['shipping_cost'], 2, ',', '.') }} €
                </td>
                <td class="text-right" style="font-weight: bold; color: #4b5563;">{{ number_format($tc['total_cost'], 2, ',', '.') }} €</td>
                <td class="text-right" style="background: #fffbeb;">
                    <div style="font-size: 10px; font-weight: bold; color: #111827; margin-bottom: 2px;">{{ number_format($tc['net_margin'], 2, ',', '.') }} €</div>
                    <div style="font-size: 8px; font-weight: bold; color: {{ $tc['margin_percent'] >= 50 ? '#059669' : ($tc['margin_percent'] >= 20 ? '#d97706' : '#dc2626') }}">{{ $tc['margin_percent'] }}% Marge</div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div style="page-break-after: always;"></div>

<!-- Section 2: Forecasting -->
<div class="section-heading" style="border-bottom-color: #2563eb; color: #2563eb;">2. Bestands-Prognose & Sales Velocity</div>
<table>
    <thead>
        <tr>
            <th style="width: 30%;">Produkt</th>
            <th style="width: 10%; text-align: center;">Auf Lager</th>
            <th style="width: 15%; text-align: center;">Sales (30 Tage)</th>
            <th style="width: 15%; text-align: center;">Ø Velocity / Tag</th>
            <th style="width: 15%; text-align: center;">Reichweite (Tage)</th>
            <th style="width: 15%; text-align: center;">Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($forecastingData as $fc)
            <tr>
                <td><strong style="font-size: 9px; color: #111827;">{{ $fc['name'] }}</strong></td>
                <td class="text-center" style="font-weight: bold;">{{ $fc['stock'] }}</td>
                <td class="text-center">{{ $fc['sold_last_30'] }}</td>
                <td class="text-center">{{ $fc['velocity'] }}</td>
                <td class="text-center" style="font-weight: bold;">{{ $fc['reach_days'] }}</td>
                <td class="text-center">
                    @if($fc['status'] === 'out_of_stock')
                        <span style="color: #dc2626; font-weight: bold;">AUSVERKAUFT</span>
                    @elseif($fc['status'] === 'critical' || $fc['status'] === 'warning')
                        <span style="color: #d97706; font-weight: bold;">NACHBESTELLEN</span>
                    @else
                        <span style="color: #059669; font-weight: bold;">OK</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div style="page-break-after: auto;"></div>

<!-- Section 3: Losses -->
<div class="section-heading" style="border-bottom-color: #dc2626; color: #dc2626; margin-top: 15px;">3. Bruch & Schwund Logbuch</div>

<table class="loss-summary">
    <tr>
        <td>
            <div class="loss-label">Verlust aktueller Monat</div>
            <div class="loss-val">{{ number_format($lossesData['this_month'], 2, ',', '.') }} €</div>
        </td>
        <td>
            <div class="loss-label">Verlust All-Time</div>
            <div class="loss-val">{{ number_format($lossesData['total'], 2, ',', '.') }} €</div>
        </td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th style="width: 15%;">Datum</th>
            <th style="width: 30%;">Produkt</th>
            <th style="width: 10%; text-align: center;">Menge</th>
            <th style="width: 30%;">Defekt-Ursache</th>
            <th style="width: 15%; text-align: right;">Verlustsumme</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lossesData['recent'] as $loss)
            <tr>
                <td>{{ $loss->created_at->format('d.m.Y H:i') }}</td>
                <td><strong style="font-size: 9px; color: #111827;">{{ $loss->product->name ?? 'GELÖSCHTES PRODUKT' }}</strong></td>
                <td class="text-center" style="font-weight: bold;">-{{ $loss->quantity }}</td>
                <td style="font-style: italic;">{{ \Illuminate\Support\Str::limit($loss->reason, 80) }}</td>
                <td class="text-right" style="font-weight: bold; color: #dc2626;">-{{ number_format($loss->cost_value / 100, 2, ',', '.') }} €</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center" style="padding: 10px; color: #6b7280;">Keine Schwund-Einträge vorhanden. Saubere Arbeit!</td>
            </tr>
        @endforelse
    </tbody>
</table>

</body>
</html>
