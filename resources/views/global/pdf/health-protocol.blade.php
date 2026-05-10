<!DOCTYPE html>
<html lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Gesprächsprotokoll / Analyse</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #333333;
            line-height: 1.6;
            font-size: 13px;
            margin: 0;
            padding: 20px;
        }

        .header {
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        .header table {
            width: 100%;
            border-collapse: collapse;
        }

        .header td {
            vertical-align: top;
        }

        .company-info .logo {
            max-width: 250px;
            margin-bottom: 5px;
            display: block;
        }

        .company-info p {
            margin: 0;
            font-size: 11px;
            color: #666;
            font-weight: bold;
        }

        .doc-meta {
            text-align: right;
        }

        .doc-meta h2 {
            margin: 0 0 5px 0;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #1f2937;
        }

        .doc-meta p {
            margin: 0;
            font-size: 11px;
            color: #666;
            line-height: 1.6;
        }

        .protocol-content {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 30px;
        }

        .protocol-content h1, .protocol-content h2, .protocol-content h3 {
            color: #1e40af;
            margin-top: 0;
        }

        .protocol-content p {
            margin-bottom: 15px;
        }

        .footer {
            margin-top: 50px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #6c757d;
            line-height: 1.4;
            display: table;
            width: 100%;
        }

        .footer-left {
            display: table-cell;
            width: 50%;
            text-align: left;
            vertical-align: top;
        }

        .footer-right {
            display: table-cell;
            width: 50%;
            text-align: right;
            vertical-align: top;
        }
        
        .footer-bottom {
            margin-top: 15px;
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
        }
    </style>
</head>
<body>

    <div class="header">
        <table>
            <tr>
                <td class="company-info">
                    @php
                        $logoPath = public_path('shop/projekt/logo/mein-seelenfunke-logo.svg');
                        $logoBase64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
                    @endphp
                    @if($logoBase64)
                        <img src="data:image/svg+xml;base64,{{ $logoBase64 }}" class="logo" alt="Logo">
                    @endif
                    <p>Interner Medizinischer Assistent</p>
                    <p>Behandelnder Arzt: {{ $protocol->agent->name ?? 'Dr. Funki' }}</p>
                </td>
                <td class="doc-meta">
                    <h2>Protokoll & Analyse</h2>
                    <p><strong>Datum:</strong> {{ $protocol->created_at->format('d.m.Y H:i') }}</p>
                    <p><strong>Patient:</strong> {{ $protocol->user->first_name ?? 'Nutzer' }} {{ $protocol->user->last_name ?? '' }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="protocol-content">
        {!! \Illuminate\Support\Str::markdown($protocol->content) !!}
    </div>

    <div class="footer">
        <div class="footer-left">
            <strong>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}</strong> | Inh. {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}<br>
            {{ shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}, {{ shop_setting('owner_city', '38518 Gifhorn') }}<br>
            {{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }} | 
            {{ str_replace(['http://', 'https://'], '', shop_setting('owner_website', 'www.mein-seelenfunke.de')) }}
        </div>
        <div class="footer-right">
            IBAN: {{ shop_setting('owner_iban', 'Wird nachgereicht') }}<br>
            Steuernummer: {{ shop_setting('owner_tax_id') }}<br>
            Gerichtsstand: {{ shop_setting('owner_court', 'Gifhorn') }}
        </div>
    </div>
    <div class="footer-bottom">
        Generiert durch Seelenfunke AI (Agent: {{ $protocol->agent->name ?? 'Dr. Funki' }}) am {{ now()->format('d.m.Y H:i') }} Uhr. Dieses Dokument dient internen Tracking-Zwecken der Geschäftsführung.
    </div>

</body>
</html>
