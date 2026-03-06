<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Liquiditätsplanung - Mein Seelenfunke</title>
    <style>
        /* Optimierte Ränder: Unten 15mm Platz für den sauberen Footer */
        @page { size: A4 landscape; margin: 6mm 12mm 15mm 12mm; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 7px; color: #2d3748; margin: 0; padding: 0; background-color: #ffffff; }

        /* Sicherer, fixierter Footer für DOMPDF */
        #footer { position: fixed; bottom: -12mm; left: 0px; right: 0px; height: 10mm; font-size: 6px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 4px; }
        .footer-table { width: 100%; border-collapse: collapse; border: none; }
        .footer-table td { border: none; padding: 0; background: transparent; }
        .page-number:after { content: "Seite " counter(page); font-weight: bold; }

        .header { margin-bottom: 4px; padding-bottom: 4px; border-bottom: 1px solid #C5A059; position: relative; }
        .logo { height: 90px; position: absolute; right: 0; top: -15px; }

        .doc-title { font-size: 16px; font-weight: bold; color: #111827; margin: 0 0 2px 0; }

        .erp-tag {
            display: inline-block; background: linear-gradient(135deg, #d4af37, #f3e5ab, #d4af37);
            color: #111827; padding: 2px 5px; font-size: 6px; font-weight: bold;
            border-radius: 3px; text-transform: uppercase; margin-bottom: 2px; border: 1px solid #b38b42;
        }

        .plan-year-title { font-size: 20px; font-weight: 900; color: #111827; margin-top: 10px; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 1px; }
        .section-heading { font-size: 9px; font-weight: bold; color: #111827; margin-top: 15px; margin-bottom: 4px; text-transform: uppercase; }

        .doc-meta { font-size: 7px; color: #6b7280; margin-top: 2px; }

        /* Tabellen Layout */
        table { border-collapse: collapse; margin-bottom: 6px; page-break-inside: auto; width: 100%; }
        .auto-table { width: auto; min-width: 60%; margin-bottom: 15px; }

        tr { page-break-inside: avoid; page-break-after: auto; }
        th, td { border: 1px solid #e5e7eb; padding: 1.5px 2px; text-align: right; white-space: nowrap; font-size: 6.5px;}
        th { background-color: #f3f4f6; font-weight: bold; text-align: center; color: #374151; font-size: 6.5px; }

        .text-left { text-align: left; }
        .category-col { width: 18%; font-weight: bold; text-align: left; background-color: #f9fafb; color: #1f2937; }
        .sum-row { background-color: #f3f4f6; font-weight: bold; color: #111827; }
        .net-row { background-color: #fef3c7; font-weight: bold; color: #d97706; }
        .end-row { background-color: #111827; font-weight: bold; color: #C5A059; font-size: 7px; border-color: #111827; }

        .negative { color: #dc2626; }
        .zero-val { color: #9ca3af; font-weight: normal; font-size: 5.5px; }
        .line-through { text-decoration: line-through; opacity: 0.3; }
        .legend-box { font-size: 6px; color: #6b7280; margin-top: 2px; margin-bottom: 10px; }

        /* Layout für Glossar & Textblöcke */
        .statement-box { margin-top: 10px; margin-bottom: 15px; padding: 8px; border-left: 2px solid #C5A059; background-color: #f9fafb; page-break-inside: avoid; }
        .statement-box h3 { font-size: 9px; color: #111827; margin-top: 0; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 1px; }
        .statement-box p { font-size: 7px; line-height: 1.4; color: #374151; margin-bottom: 3px; }

        .glossary-section { margin-bottom: 10px; page-break-inside: avoid; display: block; float: left; width: 31%; margin-right: 2%; }
        .glossary-section h3 { font-size: 8px; color: #111827; border-bottom: 1px solid #e5e7eb; padding-bottom: 2px; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;}
        .glossary-item { margin-bottom: 5px; }
        .glossary-item strong { color: #C5A059; font-size: 7px; }
        .glossary-item p { margin: 1.5px 0 0 0; font-size: 6.5px; color: #4b5563; line-height: 1.3; white-space: normal; }

        .score-container { clear: both; margin-top: 15px; margin-bottom: 15px; padding: 10px 12px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 6px; page-break-inside: avoid; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .score-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 3px; width: 60%; }
        .score-label { font-size: 6.5px; font-weight: bold; color: #374151; width: 40%; display: inline-block;}
        .score-bar-wrapper { width: 50%; background-color: #f3f4f6; border-radius: 4px; height: 8px; overflow: hidden; display: inline-block; vertical-align: middle; }
        .score-bar-fill { height: 100%; text-align: right; color: white; font-weight: bold; line-height: 8px; padding-right: 3px; font-size: 5.5px; }
        .score-value { width: 10%; text-align: right; font-size: 6.5px; font-weight: bold; display: inline-block; }

        .gold-heading { color: #C5A059; font-size: 12px; font-weight: bold; text-transform: uppercase; border-bottom: 1.5px solid #C5A059; padding-bottom: 4px; margin-top: 20px; margin-bottom: 10px; }
        .page-break { page-break-after: always; }
        .clearfix { clear: both; }
        a { color: #C5A059; text-decoration: none; }
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

@foreach($years as $index => $year)
    <div class="header">
        <img src="{{ public_path('images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Logo" class="logo">
        <div class="doc-title">Betriebswirtschaftliche Liquiditätsplanung</div>
        <div class="erp-tag">Liquiditätsplanung generiert durch: Seelenfunke ERP System</div>
        <div class="plan-year-title">PLANUNGSJAHR: {{ $year }}</div>
        <div class="doc-meta">
            Generiert am: {{ date('d.m.Y, H:i') }} Uhr<br>
            <strong>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}</strong>, {{ shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}, {{ shop_setting('owner_city', '38518 Gifhorn') }}
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th class="category-col">Kategorien (in EUR)</th>
            @for($m=1; $m<=12; $m++)
                <th>{{ sprintf('%02d.%02d', $m, $year % 100) }}</th>
            @endfor
        </tr>
        </thead>
        <tbody>
        <tr>
            <td class="category-col">Bestand Monatsanfang</td>
            @for($m = 1; $m <= 12; $m++)
                @php $val = $totals[$year][$m]['start'] ?? 0; @endphp
                <td class="{{ $val == 0 ? 'zero-val' : '' }}">{{ number_format($val, 2, ',', '.') }}&nbsp;€</td>
            @endfor
        </tr>

        <tr class="sum-row">
            <td class="text-left">Einzahlungen (Summe)</td>
            @for($m = 1; $m <= 12; $m++)
                @php $val = $totals[$year][$m]['in'] ?? 0; @endphp
                <td class="{{ $val == 0 ? 'zero-val' : '' }}">{{ number_format($val, 2, ',', '.') }}&nbsp;€</td>
            @endfor
        </tr>
        @foreach($receiptRows as $key => $row)
            @php
                $rowSum = 0;
                for($i=1; $i<=12; $i++) { $rowSum += abs((float) ($data[$year][$i]['in'][$key] ?? 0)); }
                $isZero = $rowSum == 0;
            @endphp
            <tr>
                <td class="text-left {{ $isZero ? 'line-through' : '' }}" style="padding-left: 10px; font-weight: normal;">{{ $row['label'] }}</td>
                @for($m = 1; $m <= 12; $m++)
                    @php $val = $data[$year][$m]['in'][$key] ?? 0; @endphp
                    <td class="{{ $val == 0 ? 'zero-val' : '' }} {{ $isZero ? 'line-through' : '' }}">{{ number_format($val, 2, ',', '.') }}&nbsp;€</td>
                @endfor
            </tr>
        @endforeach

        <tr class="sum-row">
            <td class="text-left">Auszahlungen (Summe)</td>
            @for($m = 1; $m <= 12; $m++)
                @php $val = $totals[$year][$m]['out'] ?? 0; @endphp
                <td class="{{ $val == 0 ? 'zero-val' : '' }}">{{ number_format($val, 2, ',', '.') }}&nbsp;€</td>
            @endfor
        </tr>
        @foreach($expenseRows as $key => $row)
            @php
                $rowSum = 0;
                for($i=1; $i<=12; $i++) { $rowSum += abs((float) ($data[$year][$i]['out'][$key] ?? 0)); }
                $isZero = $rowSum == 0;
            @endphp
            <tr>
                <td class="text-left {{ $isZero ? 'line-through' : '' }}" style="padding-left: 10px; font-weight: normal;">{{ $row['label'] }}</td>
                @for($m = 1; $m <= 12; $m++)
                    @php $val = $data[$year][$m]['out'][$key] ?? 0; @endphp
                    <td class="{{ $val == 0 ? 'zero-val' : '' }} {{ $isZero ? 'line-through' : '' }}">{{ number_format($val, 2, ',', '.') }}&nbsp;€</td>
                @endfor
            </tr>
        @endforeach

        <tr class="net-row">
            <td class="text-left">Über-/Unterdeckung Monat</td>
            @for($m = 1; $m <= 12; $m++)
                @php $net = $totals[$year][$m]['net'] ?? 0; @endphp
                <td class="{{ $net < 0 ? 'negative' : ($net == 0 ? 'zero-val' : '') }}">{{ number_format($net, 2, ',', '.') }}&nbsp;€</td>
            @endfor
        </tr>

        <tr class="sum-row">
            <td class="text-left">Ausgleichsmaßnahmen</td>
            @for($m = 1; $m <= 12; $m++)
                @php $val = $totals[$year][$m]['adj'] ?? 0; @endphp
                <td class="{{ $val == 0 ? 'zero-val' : '' }}">{{ number_format($val, 2, ',', '.') }}&nbsp;€</td>
            @endfor
        </tr>
        @foreach($adjustmentRows as $key => $row)
            @php
                $rowSum = 0;
                for($i=1; $i<=12; $i++) { $rowSum += abs((float) ($data[$year][$i]['adj'][$key] ?? 0)); }
                $isZero = $rowSum == 0;
            @endphp
            <tr>
                <td class="text-left {{ $isZero ? 'line-through' : '' }}" style="padding-left: 10px; font-weight: normal;">{{ $row['label'] }}</td>
                @for($m = 1; $m <= 12; $m++)
                    @php $val = $data[$year][$m]['adj'][$key] ?? 0; @endphp
                    <td class="{{ $val == 0 ? 'zero-val' : '' }} {{ $isZero ? 'line-through' : '' }}">{{ number_format($val, 2, ',', '.') }}&nbsp;€</td>
                @endfor
            </tr>
        @endforeach

        <tr class="end-row">
            <td class="text-left" style="border-color: #111827;">Bestand Monatsende</td>
            @for($m = 1; $m <= 12; $m++)
                @php $end = $totals[$year][$m]['end'] ?? 0; @endphp
                <td style="border-color: #111827;" class="{{ $end < 0 ? 'negative' : ($end == 0 ? 'zero-val' : '') }}">{{ number_format($end, 2, ',', '.') }}&nbsp;€</td>
            @endfor
        </tr>
        </tbody>
    </table>

    <div class="legend-box">
        <strong>* Legende:</strong> Grau durchgestrichene Positionen weisen im angezeigten Planungsjahr keine Liquiditätsbewegungen auf (0,00 €).
    </div>

    <div class="page-break"></div>
@endforeach

<div class="header">
    <img src="{{ public_path('images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Logo" class="logo">
    <div class="doc-title" style="color: #C5A059;">Ertrags- & Kapitalbedarfsplanung</div>
    <div class="erp-tag">Zusatz-Auswertungen</div>
</div>

<h3 class="section-heading">Kapitalbedarfsplanung (Startphase)</h3>
<table class="auto-table">
    <thead>
    <tr>
        <th class="category-col" style="text-align: left;">Positionen (Netto / Brutto in EUR)</th>
        <th>Betrag</th>
    </tr>
    </thead>
    <tbody>
    <tr class="sum-row"><td colspan="2" class="text-left" style="font-size: 6px; text-transform: uppercase;">Investitionsgüter</td></tr>
    <tr><td class="text-left zero-val">Grundstück</td><td class="zero-val">0,00&nbsp;€</td></tr>
    <tr><td class="text-left zero-val">Gebäude</td><td class="zero-val">0,00&nbsp;€</td></tr>
    <tr><td class="text-left zero-val">Umbaumaßnahmen</td><td class="zero-val">0,00&nbsp;€</td></tr>
    <tr><td class="text-left zero-val">Geschäfts- und Ladeneinrichtung</td><td class="zero-val">0,00&nbsp;€</td></tr>
    <tr><td class="text-left" style="font-weight: bold;">Maschinen + Werkzeuge</td><td style="font-weight: bold;">{{ number_format($kapitalbedarf['investitionen']['maschinen'] ?? 0, 2, ',', '.') }}&nbsp;€</td></tr>
    <tr><td class="text-left" style="font-weight: bold;">Warenanfangsbestand</td><td style="font-weight: bold;">{{ number_format($kapitalbedarf['investitionen']['waren'] ?? 0, 2, ',', '.') }}&nbsp;€</td></tr>
    <tr><td class="text-left zero-val">Fahrzeuge</td><td class="zero-val">0,00&nbsp;€</td></tr>
    <tr><td class="text-left zero-val">Unternehmenswert (bei Unternehmenskauf)</td><td class="zero-val">0,00&nbsp;€</td></tr>
    <tr><td class="text-left zero-val">Sonstiges</td><td class="zero-val">0,00&nbsp;€</td></tr>
    <tr style="background: #eef2f5;"><td class="text-left" style="font-weight: bold;">Summe Investitionsgüter</td><td style="font-weight: bold;">{{ number_format(array_sum($kapitalbedarf['investitionen'] ?? []), 2, ',', '.') }}&nbsp;€</td></tr>

    <tr class="sum-row"><td colspan="2" class="text-left" style="font-size: 6px; text-transform: uppercase;">Gründungsaufwendungen</td></tr>
    <tr><td class="text-left" style="font-weight: bold;">Werbung</td><td style="font-weight: bold;">{{ number_format($kapitalbedarf['gruendung']['werbung'] ?? 0, 2, ',', '.') }}&nbsp;€</td></tr>
    <tr><td class="text-left" style="font-weight: bold;">Beratungen, Gutachten (Schulung)</td><td style="font-weight: bold;">{{ number_format($kapitalbedarf['gruendung']['beratung'] ?? 0, 2, ',', '.') }}&nbsp;€</td></tr>
    <tr><td class="text-left zero-val">Anmeldungen/Genehmigungen</td><td class="zero-val">0,00&nbsp;€</td></tr>
    <tr><td class="text-left zero-val">Eintragung ins Handelsregister</td><td class="zero-val">0,00&nbsp;€</td></tr>
    <tr><td class="text-left zero-val">Notar</td><td class="zero-val">0,00&nbsp;€</td></tr>
    <tr><td class="text-left zero-val">Sonstiges</td><td class="zero-val">0,00&nbsp;€</td></tr>
    <tr style="background: #eef2f5;"><td class="text-left" style="font-weight: bold;">Summe Aufwendungen</td><td style="font-weight: bold;">{{ number_format(array_sum($kapitalbedarf['gruendung'] ?? []), 2, ',', '.') }}&nbsp;€</td></tr>

    <tr class="end-row"><td class="text-left" style="border-color: #111827;">Gesamter Finanzierungsbedarf</td><td style="border-color: #111827;">{{ number_format(array_sum($kapitalbedarf['investitionen'] ?? []) + array_sum($kapitalbedarf['gruendung'] ?? []), 2, ',', '.') }}&nbsp;€</td></tr>

    <tr class="sum-row"><td colspan="2" class="text-left" style="font-size: 6px; text-transform: uppercase;">Finanzierungsstruktur</td></tr>
    <tr><td class="text-left" style="color: #10b981; font-weight:bold;">Eigenmittel</td><td style="color: #10b981; font-weight:bold;">{{ number_format($kapitalbedarf['finanzierung']['eigenmittel'] ?? 0, 2, ',', '.') }}&nbsp;€</td></tr>
    <tr><td class="text-left zero-val">zusätzliche Belastung des bestehenden Kontokorrent-Rahmens</td><td class="zero-val">0,00&nbsp;€</td></tr>
    <tr><td class="text-left">Darlehen und Erweiterung des Kontokorrent-Rahmens</td><td>{{ number_format($kapitalbedarf['finanzierung']['darlehen'] ?? 0, 2, ',', '.') }}&nbsp;€</td></tr>
    <tr><td class="text-left zero-val">Liquiditäts-Puffer</td><td class="zero-val">0,00&nbsp;€</td></tr>
    </tbody>
</table>

<h3 class="section-heading">Ertrags-/ Rentabilitätsvorschau (Netto)</h3>
<table class="auto-table">
    <thead>
    <tr>
        <th class="category-col" style="text-align: left;">Beträge in EUR (Netto Kalkulation)</th>
        @foreach($years as $index => $y)
            <th>{{ $index + 1 }}. Jahr ({{ $y }})</th>
            <th>% vom Umsatz</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach($rentRows as $key => $label)
        @php
            $isBold = in_array($key, ['rohertrag', 'betriebsergebnis', 'ergebnis_vor_steuern', 'gewinn', 'cashflow']);
            $isBg = in_array($key, ['rohertrag', 'gewinn']);
            $rowStyle = $isBold ? 'font-weight: bold;' : '';
            $rowStyle .= $isBg ? ' background-color: #f3f4f6;' : '';
        @endphp
        <tr style="{{ $rowStyle }}">
            <td class="text-left">{{ $label }}</td>
            @foreach($years as $y)
                @php
                    $val = $rentabilitaet[$y][$key] ?? 0;
                    $umsatz = $rentabilitaet[$y]['umsatz'] ?? 0;
                    $pct = $umsatz > 0 ? ($val / $umsatz) * 100 : 0;
                @endphp
                <td class="{{ $val == 0 ? 'zero-val' : '' }}">{{ number_format($val, 2, ',', '.') }}&nbsp;€</td>
                <td class="{{ $pct == 0 ? 'zero-val' : '' }}" style="color: #6b7280;">{{ number_format($pct, 1, ',', '.') }}&nbsp;%</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>

<div class="page-break"></div>

<div class="header">
    <img src="{{ public_path('images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Logo" class="logo">
    <div class="doc-title" style="color: #C5A059;">Auswertung & Glossar</div>
    <div class="erp-tag">Liquiditätsplanung generiert durch: Seelenfunke ERP System</div>
</div>

<div class="score-container">
    <div class="statement-box" style="border-left: 3px solid #10b981; background-color: #f0fdf4; margin-top: 0;">
        <h3 style="color: #065f46;">Management Summary & Tragfähigkeitsnachweis</h3>
        <p style="font-size: 7px; color: #064e3b; margin-bottom: 2px;">
            <strong>1. Anlaufphase:</strong> Der private Lebensunterhalt in den ersten 6 Monaten (April bis September 2026) ist durch externe Zuschüsse vollständig gedeckt. Erwirtschaftete Umsätze verbleiben liquiditätsschonend im Unternehmen.<br>
            <strong>2. Tragfähigkeit:</strong> Ab dem 7. Monat entfallen die staatlichen Hilfen. Die Planung belegt, dass sich das Unternehmen ab diesem Zeitpunkt selbst trägt. Die <strong>Privatentnahme von 1.600 €</strong> ist fix kalkuliert. Der nötige Durchschnittsumsatz liegt bei realistischen&nbsp;<strong>{{ number_format($scoreData['avgSales'], 2, ',', '.') }}&nbsp;€ brutto</strong>.<br>
            <strong>3. Break-Even:</strong> Dank Eigennutzung vorhandener Räumlichkeiten sind Fixkosten minimal.
        </p>
    </div>

    <h3 style="margin-top: 10px; color: #111827; font-size: 10px; margin-bottom: 5px;">Gesamt-Tragfähigkeits-Score: {{ $scoreData['total'] }} / 100</h3>

    @foreach($scoreData['details'] as $detail)
        @php $w = ($detail['score'] / max(1, $detail['max'])) * 100; @endphp
        <div class="score-row">
            <span class="score-label">{{ $detail['label'] }}</span>
            <div class="score-bar-wrapper">
                <div class="score-bar-fill" style="width: {{ max(5, $w) }}%; background-color: {{ $detail['color'] }};"></div>
            </div>
            <span class="score-value" style="color: {{ $detail['color'] }}">{{ $detail['score'] }} / {{ $detail['max'] }}</span>
        </div>
        <div style="font-size: 6px; color: #6b7280; margin-bottom: 8px; margin-top: 1px; width: 60%;">{{ $detail['desc'] }}</div>
    @endforeach
</div>

<h2 class="gold-heading">Betriebswirtschaftliches Fachglossar</h2>
<div style="width: 100%;">
    <div class="glossary-section">
        <h3>1. Einnahmen & Kapitalzufluss</h3>
        <div class="glossary-item">
            <strong>Forderungseingänge (Sales)</strong>
            <p>Umsätze aus Verkäufen über den Online-Shop (mein-seelenfunke.de) oder Marktplätze. Im Liquiditätsplan als Brutto-Cash-Zufluss abzüglich Plattformgebühren dargestellt.</p>
        </div>
        <div class="glossary-item">
            <strong>Zuschüsse (ALG1 / Gründungsz.)</strong>
            <p>Staatliche Fördergelder während der 6-monatigen Anlaufphase zur Sicherung der privaten Lebenshaltungskosten.</p>
        </div>
        <div class="glossary-item">
            <strong>Eigenmittel (Privateinlage)</strong>
            <p>Vom Inhaber in das Unternehmen eingebrachtes privates Kapital zur Deckung des anfänglichen Investitionsbedarfs.</p>
        </div>
        <div class="glossary-item">
            <strong>Kontokorrentkredit / Darlehen</strong>
            <p>Zusätzliche Fremdfinanzierung. Die Planung weist aus, ob und in welcher Höhe externe Kreditlinien beansprucht werden müssen.</p>
        </div>
    </div>

    <div class="glossary-section">
        <h3>2. Ausgaben & Abflüsse</h3>
        <div class="glossary-item">
            <strong>Wareneinkauf (Materialaufwand)</strong>
            <p>Variable Kosten, die direkt an den Umsatz gekoppelt sind (z. B. K9 Glas, Naturschiefer, Acryl, hochwertige Verpackungsmaterialien).</p>
        </div>
        <div class="glossary-item">
            <strong>Investitionsgüter</strong>
            <p>Langfristige Vermögenswerte der Startphase (xTool F2 Ultra UV Laser, Abluftsysteme, Arbeitsplatz-Einrichtung).</p>
        </div>
        <div class="glossary-item">
            <strong>Privatentnahme (Lebenshaltung)</strong>
            <p>Der monatliche Betrag, der vom Inhaber entnommen werden muss, um Miete, Versicherungen und Lebensmittel privat decken zu können.</p>
        </div>
        <div class="glossary-item">
            <strong>Versicherungen / Beiträge</strong>
            <p>Gewerbliche Pflichtbeiträge (IHK, Verpackungslizenz LUCID) sowie betriebliche Versicherungen (Betriebshaftpflicht).</p>
        </div>
    </div>

    <div class="glossary-section">
        <h3>3. BWL Kennzahlen (KPIs)</h3>
        <div class="glossary-item">
            <strong>Bestand Monatsende (Kasse)</strong>
            <p>Der tatsächliche, kumulierte Kassenbestand. Dieser Wert indiziert die Zahlungsfähigkeit und darf im Rahmen der Planung niemals dauerhaft ins Minus rutschen.</p>
        </div>
        <div class="glossary-item">
            <strong>Rohertrag</strong>
            <p>Umsatzerlöse abzüglich des direkten Wareneinsatzes. Eine essenzielle Kennzahl, die die reine Produktmarge (Handelsspanne) vor Fixkosten aufzeigt.</p>
        </div>
        <div class="glossary-item">
            <strong>Betriebsergebnis (EBIT)</strong>
            <p>Gewinn vor Steuern und Zinsen. Ergibt sich aus dem Rohertrag abzüglich aller laufenden Fixkosten und kalkulatorischen Abschreibungen.</p>
        </div>
        <div class="glossary-item">
            <strong>Cash-Flow</strong>
            <p>Jahresüberschuss zuzüglich der nicht zahlungswirksamen Abschreibungen. Definiert die wahre, selbst erwirtschaftete Finanzkraft des Unternehmens.</p>
        </div>
    </div>
    <div class="clearfix"></div>
</div>

</body>
</html>
