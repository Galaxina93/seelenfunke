<!DOCTYPE html>
<html lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Anfrage Produktfotografie – Mein Seelenfunke</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 11mm 14mm 11mm 14mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9.5pt;
            color: #1f2937;
            margin: 0;
            padding: 0;
            line-height: 1.42;
            background-color: #ffffff;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        p {
            margin: 0 0 7px 0;
        }

        /* Top Dark Luxury Header Banner */
        .header-banner {
            background-color: #0f172a;
            border-bottom: 3px solid #C5A059;
            border-radius: 6px;
            padding: 14px 18px;
            margin-bottom: 14px;
            color: #ffffff;
        }
        .header-banner td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }
        .logo-img {
            max-height: 54px;
            max-width: 245px;
        }
        .doc-badge {
            display: inline-block;
            background-color: rgba(197, 160, 89, 0.18);
            color: #e5c378;
            border: 1px solid #C5A059;
            padding: 2px 8px;
            font-size: 7.5pt;
            font-weight: bold;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .doc-title {
            font-size: 15pt;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .doc-subtitle {
            font-size: 8pt;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* Sender & Meta Info Strip */
        .meta-strip {
            background-color: #fdfbf7;
            border: 1px solid #eadcc3;
            border-left: 3.5px solid #C5A059;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 13px;
            font-size: 8.5pt;
        }
        .meta-strip td {
            vertical-align: top;
            padding: 0;
        }
        .meta-label {
            font-size: 7pt;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #8c6d23;
            font-weight: bold;
            margin-bottom: 1px;
        }
        .meta-val {
            color: #111827;
            font-weight: bold;
            font-size: 8.5pt;
        }
        .meta-sub {
            color: #4b5563;
            font-size: 8pt;
        }

        /* Main Letter Section */
        .subject-line {
            font-size: 11.5pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e5e7eb;
        }
        .subject-accent {
            color: #9a7428;
        }
        .letter-text {
            font-size: 9.3pt;
            color: #334155;
            line-height: 1.46;
            text-align: justify;
        }

        /* Section Title */
        .section-heading {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #8c6d23;
            margin: 11px 0 6px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #eadcc3;
        }

        /* Product Grid (2 rows x 3 columns) */
        .product-grid {
            width: 100%;
            margin-bottom: 10px;
        }
        .product-grid td.prod-cell {
            width: 33.33%;
            padding: 3px;
            vertical-align: top;
        }
        .prod-card {
            border: 1px solid #e5e7eb;
            background-color: #fafaf9;
            border-radius: 5px;
            padding: 6px 7px;
        }
        .prod-card-inner td {
            vertical-align: middle;
            padding: 0;
        }
        .prod-thumb {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #d6d3d1;
            background-color: #0f172a;
        }
        .prod-title {
            font-size: 8.3pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 1px;
        }
        .prod-mat {
            font-size: 7.3pt;
            color: #8c6d23;
            font-weight: bold;
            margin-bottom: 1px;
        }
        .prod-dim {
            font-size: 7pt;
            color: #64748b;
            line-height: 1.25;
        }

        /* Shooting Requirements Two-Column Box */
        .scope-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 8px 11px;
            margin-bottom: 11px;
        }
        .scope-box td {
            vertical-align: top;
            padding: 0 6px 0 0;
        }
        .scope-title {
            font-size: 8.2pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 3px;
        }
        .scope-list {
            margin: 0;
            padding-left: 13px;
            font-size: 8pt;
            color: #334155;
            line-height: 1.38;
        }
        .scope-list li {
            margin-bottom: 2px;
        }

        /* Contact & Closing Footer Card */
        .closing-table {
            width: 100%;
            margin-top: 6px;
        }
        .closing-table td {
            vertical-align: bottom;
        }
        .contact-card {
            background-color: #0f172a;
            color: #f8fafc;
            border-radius: 5px;
            border-left: 3.5px solid #C5A059;
            padding: 8px 12px;
            font-size: 8pt;
            line-height: 1.42;
        }
        .contact-card-title {
            color: #e5c378;
            font-weight: bold;
            font-size: 8.2pt;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 3px;
        }
        .sig-name {
            font-weight: bold;
            color: #0f172a;
            font-size: 9.5pt;
            margin-top: 6px;
        }
        .sig-role {
            font-size: 8pt;
            color: #8c6d23;
        }

        /* Bottom Page Footer */
        .page-footer {
            position: fixed;
            bottom: -4mm;
            left: 0;
            right: 0;
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
            font-size: 7.2pt;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>

@php
    $logoPath = public_path('shop/projekt/logo/mein-seelenfunke-logo.png');
    $logoBase64 = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;

    $encodeImg = function (string $relPath): ?string {
        $full = storage_path('app/public/' . $relPath);
        if (!file_exists($full)) {
            return null;
        }
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($full));
    };

    $products = [
        [
            'name' => 'Der Seelen Kristall',
            'material' => 'Optisches K9-Kristallglas (Facettenschliff)',
            'details' => "Maße: 180 × 200 × 40 mm · ca.\u{00A0}920\u{00A0}g · 3D/2D-Lasergravur im Glasblock",
            'img' => $encodeImg('produkte/products/seelen-kristall/seelen-kristall_b.jpg'),
        ],
        [
            'name' => 'Das Goldene Seelen-Herz',
            'material' => 'Edelstahl · 14 Karat vergoldet (IP)',
            'details' => 'Maße: 25 × 22 × 4,0 mm · Schmuckanhänger mit Öse & beidseitiger Gravur',
            'img' => $encodeImg('produkte/products/goldenes-seelen-herz/goldenes-seelen-herz-main.jpg'),
        ],
        [
            'name' => 'Der Seelen-Talisman',
            'material' => 'Edelstahl Spiegelpoliert (Gold-Finish)',
            'details' => 'Maße: 50 × 28 × 2,2 mm · Schlüsselanhänger mit tiefer Faserlaser-Gravur',
            'img' => $encodeImg('produkte/products/seelen-talisman/seelen-talisman-main.jpg'),
        ],
        [
            'name' => 'Der Seelenanhänger',
            'material' => 'Massives eloxiertes Aluminium-Herz',
            'details' => 'Maße: 45 × 40 × 2,3 mm · Handschmeichler mit Silber-Weiß-Lasergravur',
            'img' => $encodeImg('produkte/products/seelen-anhaenger/seelen-anhaenger-back.jpg'),
        ],
        [
            'name' => 'Die Seelen-Pfote',
            'material' => 'Hochglanzpolierter V2A-Edelstahl',
            'details' => 'Maße: 40 × 22 × 1,8 mm · Hundemarke mit Pfoten-Durchbruch & Gravur',
            'img' => $encodeImg('produkte/products/seelen-pfote-hundemarke/seelen-pfote-main.jpg'),
        ],
        [
            'name' => 'Der Feierabend-Funke',
            'material' => 'Kristall-Weizenbierglas (0,5 l)',
            'details' => 'Maße: Ø 7,4 × 21,8 cm · Umlaufende 360°-Rotations-Lasergravur',
            'img' => $encodeImg('produkte/products/weizenglas-personalisiert/beer_glas_main.jpg'),
        ],
    ];
@endphp

<!-- HEADER BANNER -->
<div class="header-banner">
    <table>
        <tr>
            <td style="width: 52%;">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" alt="Mein Seelenfunke" class="logo-img">
                @else
                    <div style="font-size: 18pt; font-weight: bold; color: #C5A059;">mein-seelenfunke</div>
                @endif
            </td>
            <td style="width: 48%; text-align: right;">
                <div class="doc-badge">Unverbindliche Anfrage</div>
                <div class="doc-title">Produkt- &amp; Detailfotografie</div>
                <div class="doc-subtitle">Manufaktur für personalisierte Laser-Unikate · Gifhorn</div>
            </td>
        </tr>
    </table>
</div>

<!-- META / SENDER STRIP -->
@php
    $photoCompanyName = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));
    $photoProprietor = shop_setting('owner_proprietor', 'Alina Steinhauer');
    $photoStreet = shop_setting('company_street')
        ? trim(shop_setting('company_street') . ' ' . shop_setting('company_street_number', '26'))
        : shop_setting('owner_street', 'Carl-Goerdeler-Ring 26');
    $photoCity = shop_setting('company_city')
        ? trim(shop_setting('company_zip', '38518') . ' ' . shop_setting('company_city'))
        : shop_setting('owner_city', '38518 Gifhorn');
    $photoCityName = shop_setting('company_city', shop_setting('owner_city', 'Gifhorn'));
    $photoPhone = shop_setting('company_phone', shop_setting('owner_phone', '+49 159 019 668 64'));
    $photoEmail = shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
    $photoWebsite = str_replace(['http://', 'https://'], '', shop_setting('owner_website', 'www.mein-seelenfunke.de'));
@endphp
<div class="meta-strip">
    <table>
        <tr>
            <td style="width: 34%;">
                <div class="meta-label">Anfragende Manufaktur</div>
                <div class="meta-val">{{ $photoCompanyName }}</div>
                <div class="meta-sub">Inh. {{ $photoProprietor }} · {{ $photoCity }}</div>
            </td>
            <td style="width: 36%;">
                <div class="meta-label">Projekt &amp; Online-Shop</div>
                <div class="meta-val">{{ $photoWebsite }}</div>
                <div class="meta-sub">Personalisierte Geschenke &amp; Erinnerungsstücke</div>
            </td>
            <td style="width: 30%; text-align: right;">
                <div class="meta-label">Datum &amp; Ort</div>
                <div class="meta-val">{{ $photoCityName }}, {{ now()->format('d.m.Y') }}</div>
                <div class="meta-sub">Direktkontakt: {{ $photoPhone }}</div>
            </td>
        </tr>
    </table>
</div>

<!-- ANFRAGETEXT -->
<div class="subject-line">
    Anfrage: <span class="subject-accent">Erstellung hochwertiger Produktfotos für unsere veredelten Unikate</span>
</div>

<div class="letter-text">
    <p><strong>Hallo und herzliche Grüße aus Gifhorn,</strong></p>
    <p>
        über meinen Freund bin ich auf deine fotografische Arbeit aufmerksam geworden und wollte ganz herzlich und unverbindlich anfragen, ob für dich grundsätzlich auch die <strong>Erstellung von Produkt- und Detailaufnahmen</strong> in Frage kommen würde.
    </p>
    <p>
        Mit meinem Herzensprojekt <strong>„Mein Seelenfunke“</strong> (<span style="color: #8c6d23; font-weight: bold;">www.mein-seelenfunke.de</span>) fertige ich hier in Gifhorn personalisierte Erinnerungsstücke, Schmuckanhänger und Glas-Unikate mit hochpräziser Faser- und Diodenlaser-Gravur an. Aktuell arbeiten wir im Shop teilweise noch mit digitalen 3D-Vorschauen – möchten unseren Kundinnen und Kunden aber zeigen, wie edel, massiv und detailreich die echten veredelten Stücke im Zusammenspiel mit natürlichem Licht wirken.
    </p>
    <p>
        Da unsere Produkte aus <strong>geschliffenem K9-Kristallglas, 14 Karat vergoldetem Edelstahl und poliertem Metall</strong> bestehen, leben sie ganz besonders von einer schönen Lichtführung, feinen Reflexionen und einem geschulten Blick für Details. Genau deshalb würde ich mich riesig freuen, wenn du Lust auf ein solches Shooting hättest!
    </p>
</div>

<!-- PRODUKTÜBERSICHT -->
<div class="section-heading">Unsere 6 Hauptprodukte im Überblick (Musterstücke liegen fertig graviert bereit)</div>

<table class="product-grid">
    @foreach(array_chunk($products, 3) as $row)
        <tr>
            @foreach($row as $prod)
                <td class="prod-cell">
                    <div class="prod-card">
                        <table class="prod-card-inner">
                            <tr>
                                @if($prod['img'])
                                    <td style="width: 50px; padding-right: 6px;">
                                        <img src="{{ $prod['img'] }}" class="prod-thumb" alt="{{ $prod['name'] }}">
                                    </td>
                                @endif
                                <td>
                                    <div class="prod-title">{{ $prod['name'] }}</div>
                                    <div class="prod-mat">{{ $prod['material'] }}</div>
                                    <div class="prod-dim">{{ $prod['details'] }}</div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            @endforeach
        </tr>
    @endforeach
</table>

<!-- ECKDATEN & WÜNSCHE -->
<div class="section-heading">Was wir uns vorstellen &amp; wie der Ablauf aussehen könnte</div>

<div class="scope-box">
    <table>
        <tr>
            <td style="width: 50%;">
                <div class="scope-title">Wunsch-Motive (ca. 3–4 Bilder pro Produkt)</div>
                <ul class="scope-list">
                    <li><strong>Detail- &amp; Gravurfokus:</strong> Sichtbarkeit der feinen Lasergravur auf dem jeweiligen Material (Glas / Gold / Edelstahl).</li>
                    <li><strong>Stimmungsvolle Szene (Lifestyle):</strong> Ruhiger, edler Look (z. B. warmes Licht, dezenter Untergrund wie Holz, Stein oder Leinen).</li>
                    <li><strong>Klare Hauptansicht:</strong> Ein aufgeräumtes Produktbild als Titelbild für die jeweilige Produktseite im Shop.</li>
                </ul>
            </td>
            <td style="width: 50%; padding-left: 10px; border-left: 1px solid #e2e8f0;">
                <div class="scope-title">Unkomplizierte Rahmenbedingungen</div>
                <ul class="scope-list">
                    <li><strong>Umfang:</strong> Aktuell 6 kompakte physische Produkte (gerne auch erst einmal als kleiner Testlauf mit 2–3 Stücken).</li>
                    <li><strong>Übergabe:</strong> Ich kann dir die fertig gravierten Musterstücke jederzeit flexibel vorbeibringen oder zusenden.</li>
                    <li><strong>Verwendung:</strong> Kommerzielle Nutzung im eigenen Online-Shop (<span style="color:#8c6d23;">mein-seelenfunke.de</span>) sowie auf Instagram/Pinterest.</li>
                </ul>
            </td>
        </tr>
    </table>
</div>

<!-- CLOSING & CONTACT -->
<div class="letter-text" style="margin-bottom: 8px;">
    <p>
        Falls Produktfotografie für dich interessant ist und in deinen Zeitplan passt, freue ich mich sehr über eine kurze Rückmeldung – gerne ganz ungezwungen über meinen Freund, per WhatsApp, telefonisch oder per E-Mail mit einer ersten Einschätzung zu deinen Konditionen.
    </p>
</div>

<table class="closing-table">
    <tr>
        <td style="width: 42%;">
            <div style="font-size: 9pt; color: #334155;">Herzliche Grüße und vielen Dank für deine Zeit</div>
            <div class="sig-name">{{ $photoProprietor }}</div>
            <div class="sig-role">Gründerin &amp; Inhaberin · {{ $photoCompanyName }}</div>
        </td>
        <td style="width: 58%;">
            <div class="contact-card">
                <div class="contact-card-title">Direkter Kontakt für Rückfragen</div>
                <table>
                    <tr>
                        <td style="width: 37%; color: #cbd5e1; padding: 0;">
                            <strong style="color: #ffffff;">{{ $photoProprietor }}</strong><br>
                            {{ $photoStreet }}<br>
                            {{ $photoCity }}
                        </td>
                        <td style="width: 63%; color: #cbd5e1; padding: 0; white-space: nowrap;">
                            <strong style="color: #e5c378;">Tel. / WhatsApp:</strong> {{ $photoPhone }}<br>
                            <strong style="color: #e5c378;">E-Mail:</strong> {{ $photoEmail }}<br>
                            <strong style="color: #e5c378;">Web:</strong> {{ $photoWebsite }}
                        </td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="page-footer">
    {{ $photoCompanyName }} – Ein Funke, der bleibt · Inhaberin: {{ $photoProprietor }} · {{ $photoStreet }}, {{ $photoCity }} · {{ $photoWebsite }}
</div>

</body>
</html>
