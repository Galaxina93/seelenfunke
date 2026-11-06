@php
    $amountFormatted = number_format($voucher->initial_value / 100, 2, ',', '.') . ' €';
    $recipientName = trim((string) ($voucher->recipient_name ?? ''));
    $personalMessage = trim((string) ($voucher->personal_message ?? ''));
    $validUntilText = $voucher->valid_until
        ? 'Gültig bis ' . $voucher->valid_until->format('d.m.Y')
        : 'Gültig 3 Jahre (§ 195 BGB)';
    $website = str_replace(['http://', 'https://'], '', shop_setting('owner_website', 'www.mein-seelenfunke.de'));

    // Automatischer Zeilenumbruch für die persönliche Widmung (max. 3 Zeilen à ~52 Zeichen auf 86x54mm)
    $wrappedMessageLines = [];
    if ($personalMessage !== '') {
        $cleanMsg = preg_replace('/\s+/', ' ', $personalMessage);
        $wrapped = wordwrap('„' . $cleanMsg . '“', 52, "\n", true);
        $wrappedMessageLines = array_slice(explode("\n", $wrapped), 0, 3);
    }
@endphp
{!! '<' . '?xml version="1.0" encoding="UTF-8" standalone="no"?' . '>' !!}
<svg xmlns="http://www.w3.org/2000/svg"
     width="86mm"
     height="54mm"
     viewBox="0 0 86 54">

    <!-- ========================================================================= -->
    <!-- EBENE 1 (ROT #FF0000): AUSSENKONTUR 86 x 54 mm (NUR FÜR FRAMING / AUSRICHTUNG) -->
    <!-- In xTool Studio diese rote Ebene auf "Ignorieren / Output Off" stellen!   -->
    <!-- ========================================================================= -->
    <g id="Framing_86x54mm_Ignorieren" stroke="#FF0000" fill="none" stroke-width="0.15">
        <rect x="0.075" y="0.075" width="85.85" height="53.85" rx="3.0" ry="3.0" />
    </g>

    <!-- ========================================================================= -->
    <!-- EBENE 2 (SCHWARZ #000000): LASER-GRAVUR AUF GOLDENER METALLKARTE          -->
    <!-- ========================================================================= -->
    <g id="Laser_Gravur_Seelenfunke" fill="#000000" stroke="none">

        <!-- Edler Doppelrahmen mit Eck-Ornamenten -->
        <g fill="none" stroke="#000000">
            <!-- Äußerer Zierrand -->
            <rect x="3.2" y="3.2" width="79.6" height="47.6" rx="1.8" ry="1.8" stroke-width="0.35" />
            <!-- Feiner innerer Zierrand -->
            <rect x="4.4" y="4.4" width="77.2" height="45.2" rx="1.2" ry="1.2" stroke-width="0.15" />

            <!-- Eck-Ornamente (Oben Links, Oben Rechts, Unten Links, Unten Rechts) -->
            <path d="M 3.2 7.2 L 7.2 7.2 M 7.2 3.2 L 7.2 7.2" stroke-width="0.22" />
            <path d="M 82.8 7.2 L 78.8 7.2 M 78.8 3.2 L 78.8 7.2" stroke-width="0.22" />
            <path d="M 3.2 46.8 L 7.2 46.8 M 7.2 50.8 L 7.2 46.8" stroke-width="0.22" />
            <path d="M 82.8 46.8 L 78.8 46.8 M 78.8 50.8 L 78.8 46.8" stroke-width="0.22" />
        </g>

        <!-- HEADER: Marke (Links) & Gutscheinwert (Rechts) -->
        <text x="8.5" y="10.2" font-family="Georgia, 'Times New Roman', serif" font-size="1.95" font-weight="bold" letter-spacing="0.45">
            MEIN SEELENFUNKE
        </text>
        <text x="8.5" y="13.5" font-family="Georgia, 'Times New Roman', serif" font-size="2.75" font-weight="bold" letter-spacing="0.3">
            GESCHENKGUTSCHEIN
        </text>

        <!-- Gutscheinwert oben rechts -->
        <text x="77.5" y="13.2" text-anchor="end" font-family="Georgia, 'Times New Roman', serif" font-size="5.2" font-weight="bold">
            {{ $amountFormatted }}
        </text>

        <!-- Horizontale Trennlinie unter Header -->
        <line x1="8.5" y1="15.6" x2="77.5" y2="15.6" stroke="#000000" stroke-width="0.18" />

        <!-- EMPFÄNGER -->
        <text x="8.5" y="19.5" font-family="Arial, Helvetica, sans-serif" font-size="1.85" font-weight="bold" letter-spacing="0.25">
            FÜR:
        </text>
        <text x="15.8" y="19.6" font-family="Georgia, 'Times New Roman', serif" font-size="3.2" font-weight="bold">
            {{ $recipientName !== '' ? $recipientName : 'Dich' }}
        </text>

        <!-- PERSÖNLICHE WIDMUNG (Optional, bis zu 3 Zeilen) -->
        @if(count($wrappedMessageLines) > 0)
            @foreach($wrappedMessageLines as $idx => $line)
                <text x="8.5" y="{{ 23.8 + ($idx * 3.1) }}" font-family="Georgia, 'Times New Roman', serif" font-style="italic" font-size="2.4">
                    {{ $line }}
                </text>
            @endforeach
        @else
            <text x="8.5" y="25.8" font-family="Georgia, 'Times New Roman', serif" font-style="italic" font-size="2.4">
                „Ein kleiner Funke für einen ganz besonderen Moment.“
            </text>
        @endif

        <!-- GUTSCHEINCODE (Ohne Rahmen: Label "CODE" mittig darüber, Code mittig darunter) -->
        <text x="43.0" y="34.8" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="1.75" font-weight="bold" letter-spacing="0.5">
            CODE
        </text>
        <text x="43.0" y="38.8" text-anchor="middle" font-family="'Courier New', Courier, monospace" font-size="3.5" font-weight="bold" letter-spacing="0.35">
            {{ $voucher->code }}
        </text>

        <!-- Dezente Trennlinie über dem Footer -->
        <line x1="10.0" y1="41.4" x2="76.0" y2="41.4" stroke="#000000" stroke-width="0.14" />

        <!-- FOOTER (Zweizeilig & mittig, damit links/rechts reichlich Abstand zu den Ecken bleibt) -->
        <text x="43.0" y="44.4" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="1.55" font-weight="bold" letter-spacing="0.1">
            Einlösbar auf {{ $website }}
        </text>
        <text x="43.0" y="47.0" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="1.35" letter-spacing="0.08">
            {{ $validUntilText }} • Restguthaben bleibt erhalten
        </text>
    </g>
</svg>
