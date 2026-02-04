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
            line-height: 1.4;
            -webkit-text-size-adjust: none;
        }

        .container {
            max-width: 650px;
            margin: 10px auto;
            background-color: #ffffff;
            padding: 30px 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        /* HEADER & LOGO */
        .header {
            border-bottom: 2px solid #C5A059;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        /* Die mail_logo partial sollte .logo-img oder ähnliches stylen,
           wir stellen hier sicher, dass die Zelle passt */
        .header-table td {
            vertical-align: middle;
        }

        /* ADDRESS SECTION */
        .address-container {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .address-box {
            vertical-align: top;
            width: 50%;
            font-size: 11px;
            line-height: 1.5;
        }

        .sender-small {
            font-size: 8px;
            color: #888;
            text-decoration: underline;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .shipping-address-box {
            margin-top: 10px;
            padding: 8px;
            background-color: #fafafa;
            border: 1px solid #eeeeee;
            border-radius: 4px;
        }

        /* TYPOGRAPHY */
        .subject {
            font-size: 15px;
            font-weight: bold;
            color: #C5A059;
            margin: 15px 0 10px 0;
            text-transform: uppercase;
        }

        p { font-size: 12px; color: #444; margin-bottom: 10px; }

        /* TABELLEN */
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }
        .table th {
            text-align: left;
            color: #888;
            text-transform: uppercase;
            font-size: 9px;
            border-bottom: 2px solid #333;
            padding: 6px 4px;
        }
        .table td { padding: 8px 4px; border-bottom: 1px solid #f5f5f5; vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* PRODUKT VORSCHAU & MARKER */
        .preview-wrapper { margin-top: 8px; display: block; }
        .preview-container {
            position: relative;
            width: 80px;
            height: 80px;
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
            width: 6px;
            height: 6px;
            border-radius: 50%;
            margin-left: -3px;
            margin-top: -3px;
            border: 1px solid white;
            box-shadow: 0 0 2px rgba(0,0,0,0.5);
        }
        .marker-text { background-color: #007bff; }
        .marker-logo { background-color: #28a745; }

        /* DETAILS */
        .detail-info { font-size: 10px; color: #666; margin-top: 3px; line-height: 1.3; }
        .detail-label { font-weight: bold; color: #444; margin-right: 3px; }
        .note-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            padding: 8px;
            margin-top: 8px;
            font-size: 11px;
            border-radius: 4px;
            font-style: italic;
        }

        /* TOTALS */
        .totals { margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; }
        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-row td { padding: 3px 0; font-size: 12px; color: #555; }
        .totals-final {
            font-size: 16px;
            font-weight: bold;
            color: #000;
            border-top: 2px solid #C5A059;
            padding-top: 10px !important;
            margin-top: 8px;
        }

        /* FOOTER & MISC */
        .clear { clear: both; }
        .footer-info {
            margin-top: 25px;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }

        .badge-express {
            background-color: #dc2626;
            color: #ffffff;
            padding: 3px 6px;
            border-radius: 3px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            display: inline-block;
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

    $billingAddr = $data['billing_address'] ?? ($invoice->billing_address ?? []);
    $shippingAddr = $data['shipping_address'] ?? ($invoice->shipping_address ?? []);
    $hasDifferentShipping = !empty($shippingAddr) && serialize($billingAddr) !== serialize($shippingAddr);
@endphp

<div class="container">
    {{-- HEADER MIT LOGO UND META --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="text-align: left; width: 50%;">
                    @include('global.mails.partials.mail_logo')
                </td>
                <td class="text-right" style="width: 50%;">
                    <div style="font-size: 9px; color: #888; text-transform: uppercase; letter-spacing: 1px;">
                        Angebot Nr. {{ $data['quote_number'] ?? 'N/A' }}
                    </div>
                    <div style="font-size: 11px; color: #333; font-weight: bold;">
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
                        <div style="font-size: 10px; line-height: 1.3; color: #666;">
                            <strong>{{ $shippingAddr['first_name'] ?? '' }} {{ $shippingAddr['last_name'] ?? '' }}</strong><br>
                            @if(!empty($shippingAddr['company'])) {{ $shippingAddr['company'] }}<br> @endif
                            {{ $shippingAddr['address'] ?? '' }}<br>
                            {{ $shippingAddr['postal_code'] ?? '' }} {{ $shippingAddr['city'] ?? '' }}<br>
                            {{ $shippingAddr['country'] ?? 'DE' }}
                        </div>
                    </div>
                @endif
            </td>
            <td class="address-box text-right" style="color: #666;">
                <div style="margin-top: 14px;">
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

    <p>Hallo {{ $data['contact']['vorname'] }}, vielen Dank für deine Anfrage. Basierend auf deinen Konfigurationen unterbreiten wir dir folgendes Angebot:</p>

    {{-- KUNDENAUSWAHL --}}
    @include('global.mails.partials.mail_item_list')

    {{-- PREISAUFSTELLUNG --}}
    @include('global.mails.partials.mail_price_list')

    <div class="clear"></div>

    {{-- ADDITIONAL INFO --}}
    <div class="footer-info">
        @if($isSmallBusiness)
            <p style="font-size: 11px; color: #666; font-style: italic; margin-bottom: 10px;">
                <strong>Hinweis:</strong> Umsatzsteuerfrei aufgrund der Kleinunternehmerregelung gemäß § 19 UStG.
            </p>
        @endif

        <table width="100%" style="border-collapse: collapse;">
            <tr>
                <td style="vertical-align: top; width: 65%;">
                    <strong>Kontakt & Rückfragen:</strong><br>
                    {{ $ownerEmail }}<br>
                    @if(!empty($data['contact']['telefon'])) Tel: {{ $data['contact']['telefon'] }} @endif

                    <div style="margin-top: 10px; font-style: italic; color: #888; font-size: 10px;">
                        Gültig bis zum <strong>{{ $data['quote_expiry'] ?? now()->addDays(14)->format('d.m.Y') }}</strong>.
                    </div>
                </td>
                <td style="vertical-align: top; text-align: right;">
                    @if(!empty($data['express']))
                        <div class="badge-express">EXPRESS AKTIVIERT</div>
                        @if(!empty($data['deadline']))
                            <div style="font-size: 9px; color: #dc2626; margin-top: 3px;">Termin: {{ \Carbon\Carbon::parse($data['deadline'])->format('d.m.Y') }}</div>
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
