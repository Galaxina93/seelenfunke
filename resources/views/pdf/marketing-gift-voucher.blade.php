<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Geschenkgutschein</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            background-color: #ffffff;
            color: #2b2b2b;
            margin: 0;
            padding: 0;
            width: 842pt;
            height: 595pt;
        }
        .voucher-container {
            width: 842pt;
            height: 595pt;
            position: relative;
            background-color: #fdfcfb; /* Edles, helles Naturpapierweiß */
        }
        .outer-border {
            position: absolute;
            top: 40pt;
            left: 40pt;
            width: 759pt; /* 842pt - 80pt - 3pt (border) = 759pt */
            height: 512pt; /* 595pt - 80pt - 3pt (border) = 512pt */
            border: 1.5pt solid #c5a059; /* Feine goldene Linie */
        }
        .inner-border {
            position: absolute;
            top: 25pt;
            left: 25pt;
            width: 707pt; /* 759pt - 50pt - 2pt (border) = 707pt */
            height: 460pt; /* 512pt - 50pt - 2pt (border) = 460pt */
            border: 1pt solid rgba(197, 160, 89, 0.25); /* Sehr dezente innere Linie */
        }
        .corner {
            position: absolute;
            width: 25pt;
            height: 25pt;
            border: 1.5pt solid #c5a059;
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
            color: #c5a059; /* Gold */
            margin: 0;
            letter-spacing: 8pt;
            text-transform: uppercase;
            font-weight: normal;
        }
        .header p {
            font-size: 12pt;
            color: #6b7280;
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
            color: #8a7343; /* Dunkles Gold/Bronze */
            text-transform: uppercase;
            letter-spacing: 1.5pt;
            padding-bottom: 6pt;
        }
        .value {
            font-size: 20pt;
            font-weight: normal;
            color: #1f2937;
            padding-bottom: 25pt;
        }
        
        .message-box {
            background-color: #f7f6f2; /* Soft beige/white background */
            border-left: 3pt solid #c5a059; /* Golden bar on the left */
            padding: 18pt 24pt;
            border-radius: 4px;
            font-style: italic;
            color: #4b5563;
            font-size: 14pt;
            line-height: 1.6;
            margin-top: 6pt;
        }
        
        .amount-badge {
            font-size: 46pt;
            font-weight: normal;
            color: #c5a059;
            text-align: right;
            vertical-align: middle;
        }
        
        .footer {
            position: absolute;
            bottom: 25pt;
            left: 0;
            width: 707pt;
            font-size: 9pt;
            color: #8b8f9a;
            text-align: center;
            letter-spacing: 1pt;
        }
        
        .footer-brand {
            color: #c5a059;
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
                                    <div class="value" style="font-weight: bold; color: #111827;">{{ $voucher->recipient_name }}</div>
                                    
                                    <div class="label">Gutscheincode:</div>
                                    <div class="value" style="font-family: monospace; font-size: 24pt; color: #c5a059; letter-spacing: 1pt; padding-bottom: 0; font-weight: bold;">{{ $voucher->code }}</div>
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
                    <div class="footer-brand">MEIN SEELENFUNKE</div>
                    <div>Einlösbar auf www.mein-seelenfunke.de &bull; Gültig bis zum {{ $voucher->valid_until->format('d.m.Y') }}</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
