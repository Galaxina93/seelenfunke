<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>AGB &amp; Widerrufsbelehrung – Mein Seelenfunke</title>
    <style>
        @page { margin: 38px 40px 125px 40px; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9.8px;
            color: #2d2a26;
            line-height: 1.48;
            position: relative;
        }
        .header {
            border-bottom: 2px solid #C5A059;
            padding-bottom: 16px;
            margin-bottom: 22px;
        }
        .logo { width: 230px; height: auto; display: block; }
        .doc-title {
            font-size: 16px;
            font-weight: bold;
            color: #C5A059;
            text-transform: uppercase;
            text-align: right;
            letter-spacing: 0.6px;
            margin-bottom: 4px;
        }
        .doc-meta {
            text-align: right;
            font-size: 9px;
            color: #666;
            line-height: 1.4;
        }
        h1 {
            font-size: 13px;
            font-weight: bold;
            color: #1c1917;
            margin: 0 0 8px 0;
            padding-bottom: 4px;
            border-bottom: 1px solid #eee7da;
        }
        h2 {
            font-size: 10.5px;
            font-weight: bold;
            color: #1c1917;
            margin: 10px 0 3px 0;
        }
        p {
            margin: 0 0 7px 0;
            text-align: justify;
        }
        .highlight-box {
            margin: 10px 0 14px 0;
            padding: 10px 13px;
            background-color: #faf8f5;
            border-left: 3.5px solid #C5A059;
            border-radius: 0 5px 5px 0;
            font-size: 9.5px;
            color: #3f3a34;
        }
        .form-box {
            margin: 10px 0 16px 0;
            padding: 11px 14px;
            background-color: #fcfbf9;
            border: 1px dashed #C5A059;
            border-radius: 6px;
            font-size: 9.2px;
            color: #3f3a34;
            line-height: 1.55;
        }
        .divider {
            border: 0;
            border-top: 1px solid #e7e2d8;
            margin: 16px 0;
        }
        .footer {
            position: fixed;
            bottom: -95px;
            left: 0;
            right: 0;
            border-top: 1px solid #e7e2d8;
            padding-top: 14px;
            color: #777;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-col {
            width: 33.33%;
            vertical-align: top;
            font-size: 8.5px;
            line-height: 1.5;
        }
        .footer-heading {
            color: #C5A059;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.5px;
            letter-spacing: 0.8px;
            margin-bottom: 5px;
            display: block;
        }
        .text-right { text-align: right; }
    </style>
</head>
<body>
@php
    $ownerName     = function_exists('shop_setting') ? shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) : 'Mein Seelenfunke';
    $proprietor    = function_exists('shop_setting') ? shop_setting('owner_proprietor', 'Alina Steinhauer') : 'Alina Steinhauer';
    $ownerStreet   = function_exists('shop_setting')
        ? (shop_setting('company_street') ? trim(shop_setting('company_street') . ' ' . shop_setting('company_street_number', '26')) : shop_setting('owner_street', 'Carl-Goerdeler-Ring 26'))
        : 'Carl-Goerdeler-Ring 26';
    $ownerZip      = function_exists('shop_setting') ? shop_setting('company_zip', '38518') : '38518';
    $ownerCityName = function_exists('shop_setting') ? shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) : 'Gifhorn';
    $ownerCity     = trim($ownerZip . ' ' . $ownerCityName);
    $ownerEmail    = function_exists('shop_setting') ? (shop_setting('owner_email_invoices') ?: shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'))) : 'kontakt@mein-seelenfunke.de';
    $ownerPhone    = function_exists('shop_setting') ? shop_setting('company_phone', shop_setting('owner_phone', '+49 (0) 159 019 668 64')) : '+49 (0) 159 019 668 64';
    $ownerWeb      = function_exists('shop_setting') ? shop_setting('owner_website', 'www.mein-seelenfunke.de') : 'www.mein-seelenfunke.de';
    $ownerBankName = function_exists('shop_setting') ? shop_setting('owner_bank_name', 'Volksbank BraWo') : 'Volksbank BraWo';
    if ($ownerBankName === 'OWNER_BANK_NAME') {
        $ownerBankName = 'Volksbank BraWo';
    }
    $ownerIban     = function_exists('shop_setting') ? shop_setting('owner_iban', '') : '';
    $ownerBic      = function_exists('shop_setting') ? shop_setting('owner_bic', '') : '';
    $taxId         = function_exists('shop_setting') ? shop_setting('owner_tax_id', '19/143/11624') : '19/143/11624';
    $ustId         = function_exists('shop_setting') ? shop_setting('company_ust_id', shop_setting('owner_ust_id')) : '';
    $court         = function_exists('shop_setting') ? shop_setting('owner_court', 'Gifhorn') : 'Gifhorn';
    $orderRef      = $data['order_number'] ?? ($data['quote_number'] ?? ($order->order_number ?? null));
    $logoSvgFile   = public_path('shop/projekt/logo/mein-seelenfunke-logo.svg');
    $logoPngFile   = public_path('shop/projekt/logo/mein-seelenfunke-logo.png');
    $logoSrc       = file_exists($logoSvgFile)
        ? $logoSvgFile
        : (file_exists($logoPngFile) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPngFile)) : null);
@endphp

<div class="header">
    <table width="100%">
        <tr>
            <td style="vertical-align: middle; width: 48%;">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" class="logo" alt="{{ $ownerName }}">
                @else
                    <strong style="font-size: 20px; color: #C5A059;">{{ $ownerName }}</strong>
                @endif
            </td>
            <td class="text-right" style="vertical-align: middle; width: 52%;">
                <div class="doc-title">AGB &amp; Widerrufsbelehrung</div>
                <div class="doc-meta">
                    Vertragsinformationen auf dauerhaftem Datenträger (§ 312f Abs. 2 BGB)<br>
                    @if(!empty($orderRef) && $orderRef !== 'N/A')
                        <strong>Zur Bestellung:</strong> #{{ $orderRef }} ·
                    @endif
                    <strong>Stand:</strong> {{ now()->format('d.m.Y') }}
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- WIDERRUFSBELEHRUNG --}}
<h1>Widerrufsbelehrung für Verbraucher (§ 13 BGB)</h1>

<p>
    <strong>Widerrufsrecht:</strong> Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen. Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, der nicht der Beförderer ist, die letzte Ware in Besitz genommen haben bzw. hat (bei Verträgen über digitale Inhalte, die nicht auf einem körperlichen Datenträger geliefert werden: ab dem Tag des Vertragsabschlusses).
</p>
<p>
    Um Ihr Widerrufsrecht auszuüben, müssen Sie uns (<strong>{{ $ownerName }}, Inh. {{ $proprietor }}, {{ $ownerStreet }}, {{ $ownerCity }}, Deutschland, Telefon: {{ $ownerPhone }}, E-Mail: {{ $ownerEmail }}</strong>) mittels einer eindeutigen Erklärung (z.&nbsp;B. ein mit der Post versandter Brief oder eine E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren. Sie können dafür das nachfolgende Muster-Widerrufsformular oder unsere elektronische Widerrufsfunktion unter <strong>{{ url('/widerruf') }}</strong> verwenden, was jedoch nicht vorgeschrieben ist. Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.
</p>
<p>
    <strong>Folgen des Widerrufs:</strong> Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, einschließlich der Lieferkosten (mit Ausnahme der zusätzlichen Kosten, die sich daraus ergeben, dass Sie eine andere Art der Lieferung als die von uns angebotene, günstigste Standardlieferung gewählt haben), unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist. Für diese Rückzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der ursprünglichen Transaktion eingesetzt haben, es sei denn, mit Ihnen wurde ausdrücklich etwas anderes vereinbart; in keinem Fall werden Ihnen wegen dieser Rückzahlung Entgelte berechnet. Wir können die Rückzahlung verweigern, bis wir die Waren wieder zurückerhalten haben oder bis Sie den Nachweis erbracht haben, dass Sie die Waren zurückgesandt haben, je nachdem, welches der frühere Zeitpunkt ist. Sie haben die Waren unverzüglich und in jedem Fall spätestens binnen vierzehn Tagen ab dem Tag, an dem Sie uns über den Widerruf dieses Vertrags unterrichten, an uns zurückzusenden oder zu übergeben. Die Frist ist gewahrt, wenn Sie die Waren vor Ablauf der Frist von vierzehn Tagen absenden. Sie tragen die unmittelbaren Kosten der Rücksendung der Waren. Sie müssen für einen etwaigen Wertverlust der Waren nur aufkommen, wenn dieser Wertverlust auf einen zur Prüfung der Beschaffenheit, Eigenschaften und Funktionsweise der Waren nicht notwendigen Umgang mit ihnen zurückzuführen ist.
</p>

<div class="highlight-box">
    <strong style="color: #1c1917;">Wichtige gesetzliche Ausnahmen vom Widerrufsrecht (§ 312g Abs. 2 Nr. 1 &amp; § 356 Abs. 5 BGB):</strong><br>
    • <strong>Individuell gravierte / personalisierte Unikate:</strong> Das Widerrufsrecht besteht gemäß § 312g Abs. 2 Nr. 1 BGB <strong>nicht</strong> bei Verträgen zur Lieferung von Waren, die nicht vorgefertigt sind und für deren Herstellung eine individuelle Auswahl oder Bestimmung durch den Verbraucher maßgeblich ist oder die eindeutig auf die persönlichen Bedürfnisse des Verbrauchers zugeschnitten sind (insbesondere Artikel mit individueller Lasergravur, Wunschtext, Namen, Datum oder eigenem Motiv). Ihre gesetzlichen Mängelrechte (2 Jahre Gewährleistung bei Transportschäden, Materialfehlern oder Fehlgravuren durch uns) bleiben hiervon uneingeschränkt bestehen.<br>
    • <strong>Digitale Inhalte:</strong> Bei digitalen Inhalten erlischt das Widerrufsrecht gemäß § 356 Abs. 5 BGB, wenn wir nach Ihrer ausdrücklichen Zustimmung und Ihrer Bestätigung der Kenntnisnahme vom Erlöschen des Widerrufsrechts vor Ablauf der Widerrufsfrist mit der Vertragserfüllung begonnen haben.
</div>

<div class="form-box">
    <strong style="color: #1c1917;">Muster-Widerrufsformular (Anlage 2 zu Art. 246a § 1 Abs. 2 S. 1 Nr. 1 EGBGB)</strong><br>
    <em>(Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden Sie es zurück.)</em><br>
    — An: <strong>{{ $ownerName }}, Inh. {{ $proprietor }}, {{ $ownerStreet }}, {{ $ownerCity }}, E-Mail: {{ $ownerEmail }}</strong><br>
    — Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*) / die Erbringung der folgenden Dienstleistung (*): _______________________________________________________<br>
    — Bestellt am (*) / erhalten am (*): ___________________________ · Bestellnummer: {{ !empty($orderRef) && $orderRef !== 'N/A' ? '#' . $orderRef : '____________________' }}<br>
    — Name und Anschrift des/der Verbraucher(s): _________________________________________________________________<br>
    — Datum &amp; Unterschrift des/der Verbraucher(s) <em>(nur bei Mitteilung auf Papier)</em>: ___________________________________<br>
    <span style="font-size: 8px; color: #777;">(*) Unzutreffendes streichen.</span>
</div>

<hr class="divider">

{{-- ALLGEMEINE GESCHÄFTSBEDINGUNGEN (AGB) --}}
<h1>Allgemeine Geschäftsbedingungen (AGB)</h1>

<h2>§ 1 Geltungsbereich &amp; Anbieter</h2>
<p>
    Für alle Bestellungen über unseren Online-Shop gelten diese Allgemeinen Geschäftsbedingungen (AGB) zwischen Ihnen und <strong>{{ $ownerName }}, Inhaberin {{ $proprietor }}, {{ $ownerStreet }}, {{ $ownerCity }}, Deutschland</strong> (E-Mail: {{ $ownerEmail }}, Telefon: {{ $ownerPhone }}). Verbraucher im Sinne dieser AGB ist jede natürliche Person, die ein Rechtsgeschäft zu Zwecken abschließt, die überwiegend weder ihrer gewerblichen noch ihrer selbständigen beruflichen Tätigkeit zugerechnet werden können (§ 13 BGB). Unternehmer ist jede natürliche oder juristische Person oder rechtsfähige Personengesellschaft, die bei Abschluss eines Rechtsgeschäfts in Ausübung ihrer gewerblichen oder selbständigen beruflichen Tätigkeit handelt (§ 14 BGB).
</p>

<h2>§ 2 Vertragsschluss &amp; Produktkonfigurator</h2>
<p>
    Die Darstellung der Produkte im Online-Shop stellt kein rechtlich bindendes Angebot, sondern eine Aufforderung zur Abgabe einer Bestellung dar. Durch Anklicken des Buttons „Zahlungspflichtig bestellen“ geben Sie ein verbindliches Angebot zum Kauf der im Warenkorb befindlichen Waren ab. Die Annahme des Vertrags erfolgt durch unsere ausdrückliche Auftrags-/Bestellbestätigung oder durch Ausführung der Lieferung bzw. Bereitstellung. Bei im Online-Konfigurator gestalteten Unikaten sind die von Ihnen eingegebenen Texte, Motive, Schriftarten und Positionierungen maßgeblich; bitte prüfen Sie diese vor Abschluss der Bestellung sorgfältig auf Schreibfehler. Sie versichern, dass durch von Ihnen übermittelte Texte oder Motive keine Rechte Dritter (insbesondere Urheber-, Marken- oder Persönlichkeitsrechte) verletzt werden, und stellen uns von entsprechenden Ansprüchen Dritter frei.
</p>

<h2>§ 3 Preise, Versandkosten &amp; Zahlungsbedingungen</h2>
<p>
    Alle im Online-Shop angegebenen Endpreise verstehen sich in Euro inklusive der gesetzlichen Umsatzsteuer (sofern keine Kleinunternehmerregelung gem. § 19 UStG oder steuerfreie Ausfuhrlieferung greift) zuzüglich der im Bestellprozess ausgewiesenen Versandkosten. Die Zahlung erfolgt wahlweise über die im Checkout angebotenen Zahlungsmethoden (insbesondere Kreditkarte, PayPal, Klarna, Apple Pay / Google Pay, SEPA-Lastschrift oder Überweisung/Vorkasse). Der Kaufpreis ist unmittelbar mit Vertragsschluss zur Zahlung fällig.
</p>

<h2>§ 4 Lieferung, Lieferzeit &amp; Gefahrübergang</h2>
<p>
    Die Lieferung physischer Waren erfolgt auf dem Postweg an die von Ihnen angegebene Lieferanschrift; digitale Inhalte und Sofort-Download-Gutscheine werden elektronisch per Download-Link im Kundenbereich und per E-Mail bereitgestellt. Gegenüber Verbrauchern geht die Gefahr des zufälligen Untergangs und der zufälligen Verschlechterung der verkauften Ware erst mit der Übergabe der Ware an Sie oder eine empfangsberechtigte Person über (§ 475 Abs. 2 BGB).
</p>

<h2>§ 5 Eigentumsvorbehalt</h2>
<p>
    Die gelieferte Ware bleibt bis zur vollständigen Bezahlung des Kaufpreises in unserem Eigentum.
</p>

<h2>§ 6 Gesetzliches Widerrufsrecht &amp; Ausschluss bei individuellen Gravuren</h2>
<p>
    Verbrauchern steht grundsätzlich das oben abgedruckte vierzehntägige Widerrufsrecht zu. Gemäß § 312g Abs. 2 Nr. 1 BGB besteht das Widerrufsrecht nicht bei Verträgen zur Lieferung von Waren, die nicht vorgefertigt sind und für deren Herstellung eine individuelle Auswahl oder Bestimmung durch den Verbraucher maßgeblich ist oder die eindeutig auf die persönlichen Bedürfnisse des Verbrauchers zugeschnitten sind (insbesondere individuell nach Kundenwunsch lasergravierte Unikate). Für nicht personalisierte Standardartikel sowie Wertgutscheine gilt das gesetzliche 14-tägige Widerrufsrecht uneingeschränkt.
</p>

<h2>§ 7 Gesetzliche Mängelhaftung (Gewährleistung) &amp; werkstoffbedingte Eigenschaften</h2>
<p>
    Es gelten die gesetzlichen Vorschriften zur Mängelhaftung (§§ 434 ff. BGB). Gegenüber Verbrauchern beträgt die Verjährungsfrist für gesetzliche Mängelansprüche bei neuen Waren zwei Jahre ab Ablieferung der Ware. Auch bei vom Widerrufsrecht ausgeschlossenen personalisierten Unikaten stehen Ihnen bei Sachmängeln (z.&nbsp;B. Transportschäden, Materialfehlern oder einer von Ihrer Konfigurationsvorlage abweichenden Fehlgravur durch uns) sämtliche gesetzlichen Gewährleistungsrechte (Nacherfüllung durch Ersatzlieferung oder Reparatur, Minderung oder Rücktritt) uneingeschränkt zu. Naturmaterialien wie Holz und Naturschiefer weisen naturgegebene Abweichungen in Maserung, Struktur, Bruchkanten und Farbton auf; diese unterstreichen den Unikat-Charakter und stellen keinen Sachmangel dar.
</p>

<h2>§ 8 Haftung &amp; Seelenfunken-Treueprogramm</h2>
<p>
    Wir haften unbeschränkt für Vorsatz und grobe Fahrlässigkeit, bei schuldhafter Verletzung von Leben, Körper oder Gesundheit sowie nach den Vorschriften des Produkthaftungsgesetzes. Bei leicht fahrlässiger Verletzung wesentlicher Vertragspflichten (Kardinalpflichten) ist die Haftung der Höhe nach auf den bei Vertragsschluss vorhersehbaren, vertragstypischen Schaden begrenzt; im Übrigen ist die Haftung für leichte Fahrlässigkeit ausgeschlossen. Im Rahmen unseres optionalen Seelenfunken-Treueprogramms gesammelte Seelenfunken und freigeschaltete Rabatt-Gutscheine sind personengebunden, nicht übertragbar, nicht in bar auszahlbar und werden bei Widerruf oder Stornierung der zugrundeliegenden Bestellung entsprechend zurückgebucht.
</p>

<h2>§ 9 Anwendbares Recht &amp; Verbraucherstreitbeilegung (§ 36 VSBG)</h2>
<p>
    Für sämtliche Rechtsbeziehungen der Parteien gilt das Recht der Bundesrepublik Deutschland unter Ausschluss des UN-Kaufrechts (CISG). Gegenüber Verbrauchern gilt diese Rechtswahl nur insoweit, als nicht der gewährte Schutz durch zwingende Bestimmungen des Rechts des Staates, in dem der Verbraucher seinen gewöhnlichen Aufenthalt hat, entzogen wird. Wir sind weder bereit noch verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen. Den vollständigen Text unserer AGB können Sie jederzeit auch online unter <strong>{{ url('/agb') }}</strong> abrufen.
</p>

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
                Telefon: {{ $ownerPhone }}<br>
                Web: {{ str_replace(['http://', 'https://'], '', $ownerWeb) }}
            </td>
            <td class="footer-col">
                <span class="footer-heading">Steuerdaten &amp; Rechtliches</span>
                @if(!empty($ustId)) USt-IdNr.: {{ $ustId }}<br> @endif
                Steuernummer: {{ $taxId }}<br>
                Gerichtsstand (für Kaufleute): {{ $court }}<br>
                Online-Widerruf: {{ str_replace(['http://', 'https://'], '', url('/widerruf')) }}
            </td>
        </tr>
    </table>
</div>

</body>
</html>
