<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Geschenkgutschein</title>
    @php
        $resolvedCardColor = ($cardColor ?? $voucher->card_color ?? ($voucher->orderItem->configuration['card_color'] ?? 'gold')) === 'black' ? 'black' : 'gold';
        $isBlack = $resolvedCardColor === 'black';
    @endphp
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        body {
            font-family: 'DejaVu Serif', 'Georgia', 'Times New Roman', serif;
            background-color: {{ $isBlack ? '#141414' : '#ffffff' }};
            color: {{ $isBlack ? '#f3f4f6' : '#2b2b2b' }};
            margin: 0;
            padding: 0;
            width: 842pt;
            height: 595pt;
        }
        .voucher-container {
            width: 842pt;
            height: 595pt;
            position: relative;
            background-color: {{ $isBlack ? '#181818' : '#fdfcfb' }};
        }
        .outer-border {
            position: absolute;
            top: 40pt;
            left: 40pt;
            width: 759pt; /* 842pt - 80pt - 3pt (border) = 759pt */
            height: 512pt; /* 595pt - 80pt - 3pt (border) = 512pt */
            border: 1.5pt solid {{ $isBlack ? '#d1d5db' : '#c5a059' }};
        }
        .inner-border {
            position: absolute;
            top: 25pt;
            left: 25pt;
            width: 707pt; /* 759pt - 50pt - 2pt (border) = 707pt */
            height: 460pt; /* 512pt - 50pt - 2pt (border) = 460pt */
            border: 1pt solid {{ $isBlack ? 'rgba(209, 213, 219, 0.25)' : 'rgba(197, 160, 89, 0.25)' }};
        }
        .corner {
            position: absolute;
            width: 25pt;
            height: 25pt;
            border: 1.5pt solid {{ $isBlack ? '#e5e7eb' : '#c5a059' }};
        }
        .corner-tl { top: -1.5pt; left: -1.5pt; border-right: none; border-bottom: none; }
        .corner-tr { top: -1.5pt; right: -1.5pt; border-left: none; border-bottom: none; }
        .corner-bl { bottom: -1.5pt; left: -1.5pt; border-right: none; border-top: none; }
        .corner-br { bottom: -1.5pt; right: -1.5pt; border-left: none; border-top: none; }
        
        .voucher-content {
            margin-top: 35pt;
            margin-left: 45pt;
            margin-right: 45pt;
        }
        .header {
            text-align: center;
            margin-bottom: 30pt;
        }
        .header h1 {
            font-size: 38pt;
            color: {{ $isBlack ? '#f9fafb' : '#c5a059' }};
            margin: 0;
            letter-spacing: 8pt;
            text-transform: uppercase;
            font-weight: normal;
        }
        .header p {
            font-size: 12pt;
            color: {{ $isBlack ? '#9ca3af' : '#6b7280' }};
            letter-spacing: 3pt;
            margin-top: 6pt;
            text-transform: uppercase;
        }
        
        .content {
            margin-top: 25pt;
        }
        
        .details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .label {
            font-size: 11pt;
            color: {{ $isBlack ? '#d1d5db' : '#8a7343' }};
            text-transform: uppercase;
            letter-spacing: 1.5pt;
            padding-bottom: 6pt;
        }
        .value {
            font-size: 20pt;
            font-weight: normal;
            color: {{ $isBlack ? '#ffffff' : '#1f2937' }};
            padding-bottom: 25pt;
        }
        
        .message-box {
            background-color: {{ $isBlack ? '#222222' : '#f7f6f2' }};
            border-left: 3pt solid {{ $isBlack ? '#d1d5db' : '#c5a059' }};
            padding: 18pt 24pt;
            border-radius: 4px;
            font-style: italic;
            color: {{ $isBlack ? '#e5e7eb' : '#4b5563' }};
            font-size: 14pt;
            line-height: 1.6;
            margin-top: 6pt;
        }
        
        .amount-badge {
            font-size: 46pt;
            font-weight: normal;
            color: {{ $isBlack ? '#ffffff' : '#c5a059' }};
            text-align: right;
            vertical-align: middle;
        }
        
        .footer {
            position: absolute;
            bottom: 25pt;
            left: 0;
            width: 707pt;
            font-size: 9pt;
            color: {{ $isBlack ? '#9ca3af' : '#8b8f9a' }};
            text-align: center;
            letter-spacing: 1pt;
        }
        
        .footer-brand {
            color: {{ $isBlack ? '#f3f4f6' : '#c5a059' }};
            font-weight: bold;
            margin-bottom: 5pt;
            font-size: 10pt;
            letter-spacing: 2pt;
        }
    </style>
</head>
<body>
    <div class="voucher-container">
        <div class="outer-border">
            <div class="inner-border">
                <div class="corner corner-tl"></div>
                <div class="corner corner-tr"></div>
                <div class="corner corner-bl"></div>
                <div class="corner corner-br"></div>
                
                <div class="voucher-content">
                    <div class="header">
                        <h1>Seelenfunke</h1>
                        <p>Geschenkgutschein</p>
                    </div>
                    
                    <div class="content">
                        <table class="details-table">
                            <tr>
                                <td style="width: 65%; vertical-align: top;">
                                    <div class="label">Für:</div>
                                    <div class="value" style="font-weight: bold; color: {{ $isBlack ? '#ffffff' : '#111827' }};">{{ $voucher->recipient_name }}</div>
                                    
                                    <div class="label">Gutscheincode:</div>
                                    <div class="value" style="font-family: monospace; font-size: 24pt; color: {{ $isBlack ? '#f3f4f6' : '#c5a059' }}; letter-spacing: 1pt; padding-bottom: 0; font-weight: bold;">{{ $voucher->code }}</div>
                                </td>
                                <td style="width: 35%; text-align: right; vertical-align: middle;">
                                    <div class="label" style="text-align: right; padding-right: 5px;">Wert:</div>
                                    <div class="amount-badge">{{ number_format($voucher->initial_value / 100, 2, ',', '.') }} €</div>
                                </td>
                            </tr>
                        </table>
                        
                        @if($voucher->personal_message)
                            <div class="label" style="margin-top: 15px;">Persönliche Botschaft:</div>
                            <div class="message-box">
                                „{{ $voucher->personal_message }}“
                            </div>
                        @endif
                    </div>
                </div>
                
                <div class="footer">
                    <div class="footer-brand">{{ strtoupper(shop_setting('company_name', shop_setting('owner_name', 'MEIN SEELENFUNKE'))) }}</div>
                    <div>Einlösbar auf {{ str_replace(['http://', 'https://'], '', shop_setting('owner_website', 'www.mein-seelenfunke.de')) }} &bull; {{ $voucher->valid_until ? 'Gültig bis zum ' . $voucher->valid_until->format('d.m.Y') : 'Gültig 3 Jahre ab Ausstellungsdatum (gem. §§ 195, 199 BGB)' }} &bull; Restguthaben bleibt erhalten &bull; Keine Barauszahlung</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
