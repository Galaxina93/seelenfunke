<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Top 5 Nischen Produkte - Mein Seelenfunke</title>
    <style>
        /* Optimierte Ränder: Unten 15mm Platz für den sauberen Footer */
        @page { size: A4 landscape; margin: 6mm 12mm 15mm 12mm; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 8px; color: #2d3748; margin: 0; padding: 0; background-color: #ffffff; }

        /* Sicherer, fixierter Footer für DOMPDF */
        #footer { position: fixed; bottom: -12mm; left: 0px; right: 0px; height: 10mm; font-size: 7px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 4px; }
        .footer-table { width: 100%; border-collapse: collapse; border: none; }
        .footer-table td { border: none; padding: 0; background: transparent; }
        .page-number:after { content: "Seite " counter(page); font-weight: bold; }

        .header { margin-bottom: 4px; padding-bottom: 4px; border-bottom: 1px solid #C5A059; position: relative; }
        .logo { height: 90px; position: absolute; right: 0; top: -15px; }

        .doc-title { font-size: 18px; font-weight: bold; color: #111827; margin: 0 0 2px 0; }

        .erp-tag {
            display: inline-block; background: linear-gradient(135deg, #d4af37, #f3e5ab, #d4af37);
            color: #111827; padding: 3px 6px; font-size: 7px; font-weight: bold;
            border-radius: 3px; text-transform: uppercase; margin-bottom: 2px; border: 1px solid #b38b42;
        }

        .plan-year-title { font-size: 22px; font-weight: 900; color: #111827; margin-top: 10px; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 1px; }
        .section-heading { font-size: 11px; font-weight: bold; color: #111827; margin-top: 18px; margin-bottom: 6px; text-transform: uppercase; }

        .doc-meta { font-size: 8px; color: #6b7280; margin-top: 2px; }

        /* Tabellen Layout */
        table { border-collapse: collapse; margin-bottom: 10px; page-break-inside: auto; width: 100%; }

        tr { page-break-inside: avoid; page-break-after: auto; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 4px; text-align: left; vertical-align: top; font-size: 8px;}
        th { background-color: #f3f4f6; font-weight: bold; text-align: center; color: #374151; font-size: 8px; text-transform: uppercase;}

        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .gold-row { background-color: #fef3c7; border-left: 2px solid #d97706; }
        .silver-row { background-color: #f9fafb; border-left: 2px solid #9ca3af; }
        .bronze-row { background-color: #fff7ed; border-left: 2px solid #c2410c; }

        .score-bar-wrapper { width: 100%; background-color: #e5e7eb; border-radius: 2px; height: 10px; overflow: hidden; display: block; margin-top: 3px;}
        .score-bar-fill { height: 100%; color: white; display: block;}

        .score-green { background-color: #22c55e; }
        .score-yellow { background-color: #eab308; }
        .score-red { background-color: #ef4444; }

        .statement-box { margin-top: 15px; margin-bottom: 15px; padding: 12px; border-left: 3px solid #C5A059; background-color: #f9fafb; page-break-inside: avoid; }
        .statement-box h3 { font-size: 11px; color: #111827; margin-top: 0; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 1px; }
        .statement-box p { font-size: 8px; line-height: 1.5; color: #374151; margin-bottom: 4px; }

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

<div class="header">
    <img src="{{ public_path('images/projekt/logo/mein-seelenfunke-logo.svg') }}" alt="Logo" class="logo">
    <div class="doc-title">{{ $docTitle ?? 'Marktanalyse: Top 5 Nischen-Produkte' }}</div>
    <div class="erp-tag">Generiert durch: Seelenfunke Stealth Crawler</div>
    <div class="plan-year-title">SCAN-DATUM: {{ $date }}</div>
    <div class="doc-meta">
        <strong>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}</strong>, {{ shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}, {{ shop_setting('owner_city', '38518 Gifhorn') }}
    </div>
</div>

<div class="statement-box" style="margin-top: 5px; margin-bottom: 5px; padding: 10px; border-left: 3px solid #C5A059; background-color: #f9fafb;">
    <h3 style="margin-bottom: 2px;">Management Summary</h3>
    <p>Dieser Bericht enthält alle identifizierten Nischenprodukte (Top 40) aus dem Stealth-Scan. Die Produkte wurden anhand ihres Verkaufspotenzials, Preises und Rankings bewertet ("Niche Score"). Schwerpunkt: Personalisierbare Artikel (ideal für kleine und mittlere Faser- & CO2-Lasergravuren).</p>
</div>

@if(!empty($aiRecommendation))
<div class="statement-box" style="margin-top: 10px; margin-bottom: 15px; border-left: 4px solid #8b5cf6; background-color: #faf5ff;">
    <h3 style="color: #6d28d9; font-size: 13px;">💡 Dein Nischenprodukt (Empfohlen von {{ $aiAgentName ?? 'KI-Agent' }})</h3>
    <p style="white-space: pre-line; color: #4c1d95; font-size: 9px; font-weight: bold;">{{ $aiRecommendation }}</p>
</div>
@endif

<div class="section-heading">Nischen Ranking (Top 40)</div>

<table>
    <thead>
        <tr>
            <th style="width: 5%;">Rang</th>
            <th style="width: 40%; text-align: left;">Produkttitel</th>
            <th style="width: 10%;">Plattform</th>
            <th style="width: 10%; text-align: right;">Preis</th>
            <th style="width: 15%; text-align: right;">Est. Sales / Reviews</th>
            <th style="width: 20%; text-align: center;">Niche Score</th>
        </tr>
    </thead>
    <tbody>
        @foreach($products as $index => $product)
            @php
                $rowClass = '';
                if($index === 0) $rowClass = 'gold-row';
                if($index === 1) $rowClass = 'silver-row';
                if($index === 2) $rowClass = 'bronze-row';

                $scoreColor = 'score-red';
                if($product->niche_score >= 50) $scoreColor = 'score-yellow';
                if($product->niche_score >= 75) $scoreColor = 'score-green';
            @endphp
            <tr class="{{ $rowClass }}">
                <td class="text-center" style="font-weight: bold; font-size: 12px;">#{{ $index + 1 }}</td>
                <td>
                    <strong style="color: #111827; font-size: 9px; display:block; margin-bottom: 2px;">{{ \Illuminate\Support\Str::limit($product->title, 100) }}</strong>
                    <a href="{{ $product->url }}" style="font-size: 7px;">Gefundenes Original-Inserat ansehen &rarr;</a>
                </td>
                <td class="text-center"><span style="background: #fdf6e3; padding: 2px 4px; border: 1px solid #ebd8ab; border-radius: 2px;">{{ $product->platform }}</span></td>
                <td class="text-right"><strong>{{ $product->price ? number_format($product->price, 2, ',', '.') . ' €' : 'N/A' }}</strong></td>
                <td class="text-right">
                    <span style="font-weight:bold; color: #111827;">{{ number_format($product->sales_volume, 0, ',', '.') }}</span><br>
                    <span style="color: #6b7280; font-size: 7px;">{{ number_format($product->review_count, 0, ',', '.') }} Bewertungen</span>
                </td>
                <td class="text-center">
                    <div style="font-size: 11px; font-weight: bold; margin-bottom: 2px;">{{ $product->niche_score }} / 100</div>
                    <div class="score-bar-wrapper">
                        <div class="score-bar-fill {{ $scoreColor }}" style="width: {{ $product->niche_score }}%;"></div>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<!-- Removed original bottom AI box since it is now at the top -->
</body>
</html>
