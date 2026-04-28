<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>LUCID Jahresbericht {{ $lucidData['year'] }}</title>
    <style>
        @page { size: A4 portrait; margin: 6mm 12mm 15mm 12mm; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 8px; color: #2d3748; margin: 0; padding: 0; background-color: #ffffff; }

        #footer { position: fixed; bottom: -12mm; left: 0px; right: 0px; height: 10mm; font-size: 7px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 4px; }
        .footer-table { width: 100%; border-collapse: collapse; border: none; }
        .footer-table td { border: none; padding: 0; background: transparent; }
        .page-number:after { content: "Seite " counter(page); font-weight: bold; }

        .header { margin-bottom: 4px; padding-bottom: 4px; border-bottom: 1px solid #10b981; position: relative; }
        .logo { height: 90px; position: absolute; right: 0; top: -15px; }

        .doc-title { font-size: 18px; font-weight: bold; color: #111827; margin: 0 0 2px 0; }

        .erp-tag {
            display: inline-block; background: linear-gradient(135deg, #10b981, #d1fae5, #10b981);
            color: #111827; padding: 3px 6px; font-size: 7px; font-weight: bold;
            border-radius: 3px; text-transform: uppercase; margin-bottom: 2px; border: 1px solid #059669;
        }

        .plan-year-title { font-size: 22px; font-weight: 900; color: #111827; margin-top: 10px; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 1px; }
        .section-heading { font-size: 11px; font-weight: bold; color: #111827; margin-top: 18px; margin-bottom: 6px; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; padding-bottom: 2px;}

        .doc-meta { font-size: 8px; color: #6b7280; margin-top: 2px; }

        /* Tabellen Layout */
        table { border-collapse: collapse; margin-bottom: 10px; page-break-inside: auto; width: 100%; }

        tr { page-break-inside: avoid; page-break-after: auto; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 4px; text-align: left; vertical-align: top; font-size: 8px;}
        th { background-color: #f3f4f6; font-weight: bold; text-align: center; color: #374151; font-size: 8px; text-transform: uppercase;}

        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .statement-box { margin-top: 5px; margin-bottom: 15px; padding: 10px; border-left: 3px solid #10b981; background-color: #f9fafb; page-break-inside: avoid; }
        .statement-box h3 { font-size: 11px; color: #111827; margin-top: 0; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 1px; }
        .statement-box p { font-size: 8px; line-height: 1.5; color: #374151; margin-bottom: 4px; }

        a { color: #10b981; text-decoration: none; }
        
        .totals-grid { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .totals-grid td { width: 25%; padding: 8px; text-align: center; border: 1px solid #e5e7eb; background: #fafafa; }
        .totals-val { font-size: 14px; font-weight: bold; color: #111827; margin-top: 3px; }
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
    <img src="{{ public_path('shop/projekt/logo/mein-seelenfunke-logo.svg') }}" alt="Logo" class="logo">
    <div class="doc-title">LUCID Jahresbericht {{ $lucidData['year'] }}</div>
    <div class="erp-tag">Generiert durch: Seelenfunke ERP Analytics</div>
    <div class="plan-year-title">EXPORT-DATUM: {{ $date }}</div>
    <div class="doc-meta">
        <strong>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}</strong>, {{ shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}, {{ shop_setting('owner_city', '38518 Gifhorn') }}
    </div>
</div>

<div class="statement-box">
    <h3 style="margin-bottom: 2px;">Report Summary</h3>
    <p>Dieser Bericht enthält alle aufkumulierten Verpackungsmaterialien des laufenden Kalenderjahres, basierend auf den abgewickelten und bezahlten Bestellungen. Die Gesamtsummen in Kilogramm sind maßgeblich für die Jahresmeldung im Verpackungsregister (LUCID) und bei deinem dualen System (z.B. Lizenzero).</p>
</div>

<div class="section-heading">Gesamtsummen (Zur Meldung im Register)</div>
<table class="totals-grid">
    <tr>
        @php
            $labels = [
                'paper' => 'PPK (Papier/Pappe)',
                'plastic' => 'Kunststoffe',
                'glass' => 'Glas',
                'wood' => 'Holz',
                'tin' => 'Weißblech',
                'alu' => 'Aluminium',
                'composite' => 'Verbund',
                'other' => 'Sonstige',
            ];
            $count = 0;
        @endphp
        @foreach($lucidData['totals_kg'] as $key => $weight)
            @if($weight > 0)
                @if($count > 0 && $count % 4 == 0)
                    </tr><tr>
                @endif
                <td>
                    <div style="font-size: 8px; color: #6b7280; text-transform: uppercase; font-weight: bold;">{{ $labels[$key] }}</div>
                    <div class="totals-val">{{ number_format($weight, 3, ',', '.') }} kg</div>
                </td>
                @php $count++; @endphp
            @endif
        @endforeach
    </tr>
</table>

<div class="section-heading">Detaillierte Zusammensetzung pro Produkt</div>

<table>
    <thead>
        <tr>
            <th style="width: 25%; text-align: left;">Produkt</th>
            <th style="width: 10%; text-align: center;">Sales (Stk.)</th>
            @foreach($labels as $key => $label)
                @if($lucidData['totals_kg'][$key] > 0)
                    <th style="text-align: right;">{{ $label }} (kg)</th>
                @endif
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($lucidData['details'] as $item)
            <tr>
                <td><strong style="color: #111827; font-size: 9px; display:block;">{{ $item['name'] }}</strong></td>
                <td class="text-center" style="font-weight: bold;">{{ $item['sold'] }}</td>
                @foreach($labels as $key => $label)
                    @if($lucidData['totals_kg'][$key] > 0)
                        <td class="text-right">
                            {{ $item[$key . '_kg'] > 0 ? number_format($item[$key . '_kg'], 3, ',', '.') : '-' }}
                        </td>
                    @endif
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="10" class="text-center" style="padding: 15px; color: #6b7280;">Keine abgewickelten Verkäufe im aktuellen Jahr.</td>
            </tr>
        @endforelse
    </tbody>
</table>

</body>
</html>
