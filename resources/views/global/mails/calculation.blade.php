<!DOCTYPE html>
<html lang="de">
<head>
    <style>
        body { font-family: sans-serif; color: #333; line-height: 1.4; background-color: #eee; padding: 20px; }
        .card { background: white; padding: 20px; border-radius: 5px; max-width: 600px; margin: 0 auto; border-top: 5px solid #C5A059; }
        h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; color: #333; }
        .label { font-weight: bold; width: 120px; display: inline-block; color: #666; }
        .section { margin-bottom: 20px; }
        .badge { background: #333; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; text-transform: uppercase; }
        .badge-express { background: #dc2626; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th { background: #f2f2f2; text-align: left; padding: 8px; }
        td { border-bottom: 1px solid #eee; padding: 8px; vertical-align: top; }

        .file-info { font-size: 11px; color: #0066cc; background: #e6f3ff; padding: 6px; display: block; margin-top: 4px; border-radius: 3px; border: 1px dashed #0066cc; }
        .totals { text-align: right; margin-top: 20px; font-size: 14px; color: #666; }
        .total-final { font-weight: bold; font-size: 18px; color: #000; margin-top: 5px; border-top: 2px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
<div class="card">
    <h2>
        Anfrage/Bestellung #{{ $data['quote_number'] ?? 'NEU' }}
        @if(!empty($data['express']))
            <span class="badge badge-express">EXPRESS</span>
        @endif
    </h2>

    <div class="section">
        <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border: 1px solid #eee;">
            <div><span class="label">Kunde:</span> {{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }}</div>
            @if(!empty($data['contact']['firma']))
                <div><span class="label">Firma:</span> {{ $data['contact']['firma'] }}</div>
            @endif
            <div><span class="label">E-Mail:</span> <a href="mailto:{{ $data['contact']['email'] }}">{{ $data['contact']['email'] }}</a></div>
            <div><span class="label">Telefon:</span> {{ $data['contact']['telefon'] ?? '-' }}</div>
            <div><span class="label">Land:</span> {{ $data['contact']['country'] ?? 'DE' }}</div>
        </div>
    </div>

    @if(!empty($data['contact']['anmerkung']))
        <div class="section">
            <strong style="color: #C5A059;">Anmerkung:</strong><br>
            <em style="background: #ffffcc; display: block; padding: 10px; border-radius: 4px; border: 1px solid #e6db55; margin-top: 5px;">"{{ $data['contact']['anmerkung'] }}"</em>
        </div>
    @endif

    <h3>Positionen</h3>
    <table>
        <thead>
        <tr>
            <th>Artikel</th>
            <th style="text-align: center;">Menge</th>
            <th style="text-align: right;">Gesamt</th>
        </tr>
        </thead>
        <tbody>
        @foreach($data['items'] as $item)
            <tr>
                <td>
                    <strong>{{ $item['name'] }}</strong>
                    @if(!empty($item['config']))
                        <div style="font-size: 11px; color: #666; margin-top: 5px;">
                            @if(!empty($item['config']['text']))
                                <span style="color: #000;">Gravur:</span> "{{ $item['config']['text'] }}"
                                <span style="font-size: 10px;">({{ $item['config']['font'] ?? 'Standard' }})</span><br>
                            @endif

                            @if(!empty($item['config']['logo_storage_path']))
                                <span class="file-info">
                                    📂 Logo-Pfad: storage/app/{{ $item['config']['logo_storage_path'] }}
                                </span>
                            @endif
                        </div>
                    @endif
                </td>
                <td style="text-align: center;">{{ $item['quantity'] }}</td>
                <td style="text-align: right;">{{ $item['total_price'] }} €</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        {{-- Warenwert Brutto minus Steuern --}}
        @php
            $vatRate = 0.19;
            $grossGoods = (float)str_replace(',', '.', str_replace('.', '', $data['total_gross'])) - (float)str_replace(',', '.', str_replace('.', '', $data['shipping_price']));
            if(!empty($data['express'])) { $grossGoods -= 25.00; }
            $netGoods = $grossGoods / (1 + $vatRate);
        @endphp

        <div>Warenwert (Netto): {{ number_format($netGoods, 2, ',', '.') }} €</div>

        @if(!empty($data['shipping_price']) && $data['shipping_price'] !== '0,00')
            <div>Versandkosten: {{ $data['shipping_price'] }} €</div>
        @else
            <div style="color: #2ecc71;">Versandkosten: Kostenlos</div>
        @endif

        @if(!empty($data['express']))
            <div style="color: #dc2626;">Express-Zuschlag enthalten (+25,00 € Brutto)</div>
        @endif

        <div style="margin-top: 10px; border-top: 1px solid #eee; padding-top: 5px;">
            Gesamt (Netto): {{ $data['total_netto'] }} €
        </div>
        <div>MwSt (19%): {{ $data['total_vat'] }} €</div>
        <div class="total-final">Gesamtsumme (Brutto): {{ $data['total_gross'] }} €</div>
    </div>

    <div style="margin-top: 30px; font-size: 11px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 15px;">
        Diese Nachricht wurde zentral über das Order-System generiert.<br>
        Alle Anhänge (Rechnung/Logos) sind dieser E-Mail beigefügt.
    </div>
</div>
</body>
</html>
