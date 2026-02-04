<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ 'Angebotsanfrage' . '-' . ($data['quote_number'] ?? 'Anfrage') . ' | ' . shop_setting('owner_name', 'Mein Seelenfunke') }}</title>

    <style>
        /* PDF/E-Mail Reset & Base Styles */
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            color: #333333;
            line-height: 1.5;
            -webkit-text-size-adjust: none;
        }

        .container {
            max-width: 650px;
            margin: 20px auto;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        /* HEADER */
        .header {
            border-bottom: 2px solid #C5A059;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .logo {
            max-width: 180px;
            height: auto;
        }

        /* ADDRESS SECTION */
        .address-container {
            width: 100%;
            margin-bottom: 30px;
            border-collapse: collapse;
        }

        .address-box {
            vertical-align: top;
            width: 50%;
            font-size: 12px;
            line-height: 1.6;
        }

        .sender-small {
            font-size: 9px;
            color: #888;
            text-decoration: underline;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .shipping-address-box {
            margin-top: 15px;
            padding: 12px;
            background-color: #fafafa;
            border: 1px solid #eeeeee;
            border-radius: 4px;
        }

        /* TYPOGRAPHY */
        h1 { font-size: 22px; color: #111; margin-bottom: 10px; font-weight: bold; }
        .subject { font-size: 16px; font-weight: bold; color: #C5A059; margin-bottom: 15px; text-transform: uppercase; }
        p { font-size: 14px; color: #444; margin-bottom: 15px; }

        /* TABELLEN */
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
        .table th {
            text-align: left;
            color: #888;
            text-transform: uppercase;
            font-size: 10px;
            border-bottom: 2px solid #333;
            padding: 8px 4px;
        }
        .table td { padding: 12px 4px; border-bottom: 1px solid #f5f5f5; vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* PRODUKT VORSCHAU & MARKER */
        .preview-wrapper { margin-top: 10px; display: block; }
        .preview-container {
            position: relative;
            width: 100px;
            height: 100px;
            display: inline-block;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            background-color: #f9f9f9;
            background-repeat: no-repeat;
            background-position: center center;
            background-size: contain;
            overflow: hidden;
        }
        .marker {
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-left: -4px;
            margin-top: -4px;
            border: 1px solid white;
            box-shadow: 0 0 2px rgba(0,0,0,0.5);
        }
        .marker-text { background-color: #007bff; }
        .marker-logo { background-color: #28a745; }

        /* DETAILS */
        .detail-info { font-size: 11px; color: #666; margin-top: 4px; line-height: 1.4; }
        .detail-label { font-weight: bold; color: #444; margin-right: 4px; }
        .note-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            padding: 10px;
            margin-top: 10px;
            font-size: 12px;
            border-radius: 4px;
            font-style: italic;
        }

        /* SEAL / FINGERPRINT */
        .seal-box {
            margin-top: 15px;
            padding: 8px 12px;
            background-color: #f0fdf4;
            border: 1px solid #dcfce7;
            border-radius: 4px;
            display: inline-block;
        }
        .seal-text { font-size: 10px; color: #166534; font-family: monospace; font-weight: bold; }

        /* TOTALS */
        .totals { margin-top: 30px; border-top: 2px solid #eee; padding-top: 20px; }
        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-row td { padding: 4px 0; font-size: 14px; color: #555; }
        .totals-final {
            font-size: 18px;
            font-weight: bold;
            color: #000;
            border-top: 2px solid #C5A059;
            padding-top: 15px !important;
            margin-top: 10px;
        }
        .totals-final .amount { color: #C5A059; font-size: 22px; }

        /* FOOTER & MISC */
        .clear { clear: both; }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 10px;
            color: #999;
            line-height: 1.6;
        }
        .footer a { color: #C5A059; text-decoration: none; font-weight: bold; }

        .badge-express {
            background-color: #dc2626;
            color: #ffffff;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            display: inline-block;
            margin-bottom: 10px;
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

    // Abweichende Lieferadresse prüfen (Sicherer Fallback für $invoice oder $data)
    $billingAddr = $data['billing_address'] ?? ($invoice->billing_address ?? []);
    $shippingAddr = $data['shipping_address'] ?? ($invoice->shipping_address ?? []);
    $hasDifferentShipping = !empty($shippingAddr) && serialize($billingAddr) !== serialize($shippingAddr);
@endphp

<div class="container">
    {{-- LOGO --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="text-align: left;">
                    @include('global.mails.partials.mail_logo')
                </td>
                <td class="text-right" style="vertical-align: bottom;">
                    <div style="font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 1px;">
                        Angebot Nr. {{ $data['quote_number'] ?? 'N/A' }}
                    </div>
                    <div style="font-size: 12px; color: #333; font-weight: bold;">
                        Datum: {{ now()->format('d.m.Y') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ADDRESSES --}}
    <table class="address-container">
        <tr>
            <td class="address-box">
                <div class="sender-small">{{ $ownerName }} · {{ $ownerStreet }} · {{ $ownerCity }}</div>
                <strong>{{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }}</strong><br>
                @if(!empty($data['contact']['firma']))
                    {{ $data['contact']['firma'] }}<br>
                @endif
                @if(!empty($billingAddr))
                    {{ $billingAddr['address'] ?? '' }}<br>
                    {{ $billingAddr['postal_code'] ?? '' }} {{ $billingAddr['city'] ?? '' }}<br>
                    {{ $billingAddr['country'] ?? 'DE' }}
                @elseif(isset($data['contact']['anmerkung_adresse']))
                    {{ $data['contact']['anmerkung_adresse'] }}
                @endif

                @if($hasDifferentShipping)
                    <div class="shipping-address-box">
                        <div style="font-size: 8px; font-weight: bold; text-transform: uppercase; color: #C5A059; margin-bottom: 2px;">Abweichende Lieferadresse:</div>
                        <div style="font-size: 11px; line-height: 1.4; color: #666;">
                            <strong>{{ $shippingAddr['first_name'] ?? '' }} {{ $shippingAddr['last_name'] ?? '' }}</strong><br>
                            @if(!empty($shippingAddr['company'])) {{ $shippingAddr['company'] }}<br> @endif
                            {{ $shippingAddr['address'] ?? '' }}<br>
                            {{ $shippingAddr['postal_code'] ?? '' }} {{ $shippingAddr['city'] ?? '' }}<br>
                            {{ $shippingAddr['country'] ?? 'DE' }}
                        </div>
                    </div>
                @endif
            </td>
            <td class="address-box text-right" style="font-size: 11px; color: #666;">
                <div style="margin-top: 18px;">
                    <strong style="color: #000;">{{ $ownerName }}</strong><br>
                    Inhaberin: {{ $proprietor }}<br>
                    {{ $ownerStreet }}<br>
                    {{ $ownerCity }}<br>
                    {{ $ownerEmail }}
                </div>
            </td>
        </tr>
    </table>

    <div class="subject">Persönliches Angebot</div>

    <div style="margin-bottom: 25px;">
        <p>Hallo {{ $data['contact']['vorname'] }},</p>
        <p>vielen Dank für deine individuelle Anfrage und das damit verbundene Vertrauen in unsere Manufaktur. Basierend auf deinen Konfigurationen unterbreiten wir dir gerne folgendes Angebot:</p>
    </div>

    {{-- KUNDENAUSWAHL --}}
    @include('global.mails.partials.mail_item_list')

    {{-- PREISAUFSTELLUNG --}}
    @include('global.mails.partials.mail_price_list')

    <div class="clear"></div>

    {{-- ADDITIONAL INFO --}}
    <div style="margin-top: 40px; font-size: 11px; color: #555; border-top: 1px solid #eee; padding-top: 20px;">
        @if($isSmallBusiness)
            <div style="background-color: #f9f9f9; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <p style="font-size: 12px; color: #666; font-style: italic; margin-bottom: 0;">
                    <strong>Hinweis:</strong> Umsatzsteuerfrei aufgrund der Kleinunternehmerregelung gemäß § 19 UStG.
                </p>
            </div>
        @endif

        <table width="100%" style="border-collapse: collapse;">
            <tr>
                <td style="vertical-align: top; width: 60%;">
                    <strong>Kontakt & Rückfragen:</strong><br>
                    {{ $ownerEmail }}<br>
                    @if(!empty($data['contact']['telefon'])) Tel: {{ $data['contact']['telefon'] }} @endif

                    <div style="margin-top: 15px; font-style: italic; color: #888;">
                        Dieses Angebot wurde digital erstellt und ist gültig bis zum <strong>{{ $data['quote_expiry'] ?? now()->addDays(14)->format('d.m.Y') }}</strong>.
                    </div>
                </td>
                <td style="vertical-align: top; text-align: right;">
                    @if(!empty($data['express']))
                        <div class="badge-express">
                            EXPRESS-SERVICE AKTIVIERT
                        </div>
                        @if(!empty($data['deadline']))
                            <br><span style="font-size: 10px; color: #dc2626;">Wunschtermin: {{ \Carbon\Carbon::parse($data['deadline'])->format('d.m.Y') }}</span>
                        @endif
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')
</div>

</body>
</html>
