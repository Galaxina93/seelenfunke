<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Information zu Ihrem Widerruf — Mein Seelenfunke</title>
    <style>
        /* Outlook & Webmail Fixes */
        #body h1, #body h2, #body h3, #body p {
            margin: 0;
            padding: 0;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: #fcfbf9;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #374151;
            -webkit-font-smoothing: antialiased;
            line-height: 1.6;
        }

        table { border-collapse: collapse; width: 100%; }

        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #fcfbf9;
            padding-top: 40px;
            padding-bottom: 40px;
        }

        /* Das "Seelenfunke" Card-Design */
        .content {
            max-width: 600px;
            background-color: #ffffff;
            margin: 0 auto;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.03);
            border: 1px solid #f3f4f6;
        }

        .header {
            background-color: #111827; /* Das tiefe Schwarz aus dem Dashboard */
            padding: 40px 20px;
            text-align: center;
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        }

        .logo-text {
            color: #ffffff;
            font-family: 'Playfair Display', Georgia, serif; /* Edle Serif-Schrift */
            font-size: 26px;
            font-weight: bold;
            letter-spacing: 1px;
            text-decoration: none;
        }

        .sparkle {
            color: #C5A059; /* Das Gold von Seelenfunke */
        }

        .body {
            padding: 50px 40px;
            background-color: #ffffff;
        }

        /* Typografie */
        h1 {
            font-family: Georgia, serif;
            color: #111827;
            font-size: 26px;
            font-weight: bold;
            line-height: 1.3;
            margin-bottom: 25px !important;
            text-align: center;
        }

        p {
            margin-bottom: 18px !important;
            font-size: 15px;
            color: #4b5563;
        }

        /* Info-Box für den rechtlichen Hinweis */
        .link-info {
            background-color: #fef2f2;
            border-radius: 12px;
            padding: 20px;
            margin-top: 35px;
            border: 1px solid #fca5a5;
            border-left: 4px solid #ef4444;
        }

        .footer {
            background-color: #f9fafb;
            padding: 30px 40px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }

        .footer a {
            color: #6b7280;
            text-decoration: none;
            font-weight: bold;
        }

        .footer a:hover {
            color: #C5A059;
        }

        .signature {
            margin-top: 35px;
            border-top: 1px solid #f3f4f6;
            padding-top: 25px;
            font-style: italic;
        }
    </style>
</head>
<body id="body">
<table class="wrapper" role="presentation">
    <tr>
        <td>
            <div class="content">
                <div class="header">
                    <span class="logo-text">Mein Seelenfunke<span class="sparkle">.</span></span>
                </div>

                <div class="body">
                    <h1>Information zu Ihrem Widerruf</h1>

                    <p>Hallo {{ $revocation->name }},</p>

                    <p>wir haben uns dein Anliegen zu deiner Bestellung <strong>{{ $revocation->order_number }}</strong> ganz genau angesehen.</p>

                    <p>Leider müssen wir dir mitteilen, dass wir deinen Widerruf in diesem Fall <strong>nicht annehmen können</strong>.</p>

                    @if($revocation->rejection_reason === 'personalized')
                    <div class="link-info">
                        <p style="font-size: 14px; color: #991b1b; font-weight: bold; margin-bottom: 8px !important;">
                            Hinweis zu Sonderanfertigungen (§ 312g Abs. 2 Nr. 1 BGB):
                        </p>
                        <p style="font-size: 14px; color: #991b1b; margin-bottom: 0 !important; line-height: 1.5;">
                            Da wir dein Andenken ganz genau nach deinen persönlichen Wünschen gefertigt und graviert haben, ist ein Widerruf hierbei ausgeschlossen. Solche individuellen Unikate können wir nicht mehr an andere Kunden weitergeben.
                        </p>
                    </div>
                    <p style="margin-top: 25px !important;">Wir haben dein Schmuckstück bereits mit viel Liebe speziell für dich veredelt. Daher hoffen wir umso mehr, dass es dir dennoch eine große Freude bereiten wird!</p>
                    
                    @elseif($revocation->rejection_reason === 'damaged')
                    <div class="link-info">
                        <p style="font-size: 14px; color: #991b1b; font-weight: bold; margin-bottom: 8px !important;">
                            Hinweis zum Zustand der Ware &amp; Wertersatz (§ 357a Abs. 1 BGB)
                        </p>
                        <p style="font-size: 14px; color: #991b1b; margin-bottom: 0 !important; line-height: 1.5;">
                            Wir mussten bei der Prüfung feststellen, dass der Artikel Gebrauchsspuren oder Beschädigungen aufweist, die auf einen zur Prüfung der Beschaffenheit, Eigenschaften und Funktionsweise der Ware nicht notwendigen Umgang zurückzuführen sind.
                        </p>
                    </div>
                    <p style="margin-top: 25px !important;">Gemäß § 357a Abs. 1 BGB besteht in diesem Fall eine Verpflichtung zum Wertersatz in Höhe des eingetretenen Wertverlusts. Wir bitten hierfür um dein Verständnis.</p>
                    
                    @elseif($revocation->rejection_reason === 'expired')
                    <div class="link-info">
                        <p style="font-size: 14px; color: #991b1b; font-weight: bold; margin-bottom: 8px !important;">
                            Widerrufsfrist überschritten (§ 355 Abs. 2 BGB)
                        </p>
                        <p style="font-size: 14px; color: #991b1b; margin-bottom: 0 !important; line-height: 1.5;">
                            Die gesetzliche Widerrufsfrist beträgt 14 Tage ab Erhalt der Ware. Leider ist uns deine Widerrufserklärung erst nach Ablauf dieser gesetzlichen Frist zugegangen.
                        </p>
                    </div>
                    <p style="margin-top: 25px !important;">Deshalb können wir den Widerruf leider nicht mehr annehmen. Deine gesetzlichen Gewährleistungsrechte bei Sachmängeln bleiben hiervon selbstverständlich unberührt.</p>
                    
                    @else
                    <div class="link-info">
                        <p style="font-size: 14px; color: #991b1b; font-weight: bold; margin-bottom: 8px !important;">
                            Gesetzlicher Ausschluss- bzw. Erlöschensgrund (§ 312g Abs. 2 / § 356 Abs. 5 BGB)
                        </p>
                        <p style="font-size: 14px; color: #991b1b; margin-bottom: 0 !important; line-height: 1.5;">
                            Nach sorgfältiger Prüfung greift für diesen Vertrag ein gesetzlicher Ausschluss- oder Erlöschensgrund des Widerrufsrechts (insbesondere § 312g Abs. 2 Nr. 1 BGB bei individuell nach Kundenspezifikation gefertigten Waren oder § 356 Abs. 5 BGB bei digitalen Inhalten nach Ausführungsbeginn).
                        </p>
                    </div>
                    <p style="margin-top: 25px !important;">Deine gesetzlichen Mängelrechte (2 Jahre Gewährleistung bei Transportschäden oder Fehlgravuren) bleiben hiervon uneingeschränkt bestehen.</p>
                    @endif
                    
                    <p style="margin-top: 25px !important;">Solltest du noch Rückfragen haben, antworte gerne einfach auf diese E-Mail.</p>

                    <div class="signature">
                        <p>Herzliche Grüße aus der Manufaktur,<br>
                            <strong style="color: #111827;">Dein Team von Mein Seelenfunke</strong></p>
                    </div>
                </div>

                @php
                    $footerCompany = function_exists('shop_setting') ? shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) : 'Mein Seelenfunke';
                    $footerOwner   = function_exists('shop_setting') ? shop_setting('owner_proprietor', 'Alina Steinhauer') : 'Alina Steinhauer';
                    $footerStreet  = function_exists('shop_setting') ? trim(shop_setting('company_street', 'Carl-Goerdeler-Ring') . ' ' . shop_setting('company_street_number', '26')) : 'Carl-Goerdeler-Ring 26';
                    $footerCity    = function_exists('shop_setting') ? trim(shop_setting('company_zip', '38518') . ' ' . shop_setting('company_city', 'Gifhorn')) : '38518 Gifhorn';
                    $footerEmail   = function_exists('shop_setting') ? shop_setting('company_email', 'kontakt@mein-seelenfunke.de') : 'kontakt@mein-seelenfunke.de';
                    $footerPhone   = function_exists('shop_setting') ? shop_setting('company_phone', '+49 159 019 668 64') : '+49 159 019 668 64';
                    $footerUstId   = function_exists('shop_setting') ? shop_setting('company_ust_id', shop_setting('owner_ust_id', '')) : '';
                @endphp
                <div class="footer">
                    <p style="margin-bottom: 6px !important;"><strong>{{ $footerCompany }}</strong> — Inhaberin: {{ $footerOwner }}</p>
                    <p style="margin-bottom: 6px !important; font-size: 11px;">
                        {{ $footerStreet }} &bull; {{ $footerCity }} &bull; Deutschland<br>
                        E-Mail: {{ $footerEmail }}@if(!empty($footerPhone)) &bull; Tel.: {{ $footerPhone }}@endif
                        @if(!empty($footerUstId))<br>USt-IdNr.: {{ $footerUstId }}@endif
                    </p>
                    <p>
                        <a href="{{ url('/impressum') }}">Impressum</a> &nbsp;•&nbsp;
                        <a href="{{ url('/datenschutz') }}">Datenschutz</a> &nbsp;•&nbsp;
                        <a href="{{ url('/agb') }}">AGB</a> &nbsp;•&nbsp;
                        <a href="{{ url('/widerruf') }}">Widerrufsbelehrung</a>
                    </p>
                </div>
            </div>
        </td>
    </tr>
</table>
</body>
</html>
