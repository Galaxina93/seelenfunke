<!DOCTYPE html>
<html lang="de">
<head>
    <style>
        body { font-family: sans-serif; color: #333; line-height: 1.4; background-color: #eee; padding: 20px; }
        .card { background: white; padding: 20px; border-radius: 5px; max-width: 600px; margin: 0 auto; border-top: 5px solid #333; }
        h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .label { font-weight: bold; width: 120px; display: inline-block; color: #666; }
        .section { margin-bottom: 20px; }
        .badge { background: #333; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; text-transform: uppercase; }
        .badge-express { background: #dc2626; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th { background: #f2f2f2; text-align: left; padding: 8px; }
        td { border-bottom: 1px solid #eee; padding: 8px; vertical-align: top; }

        .file-info { font-size: 11px; color: #0066cc; background: #e6f3ff; padding: 4px; display: block; margin-top: 4px; border-radius: 3px; }
    </style>
</head>
<body>
<div class="card">
    <h2>
        Neue Anfrage #{{ $data['quote_number'] ?? 'NEU' }}
        @if(!empty($data['express']) && $data['express'])
            <span class="badge badge-express">EXPRESS</span>
        @endif
    </h2>

    <div class="section">
        <div style="background: #f9f9f9; padding: 10px; border-radius: 4px;">
            <div><span class="label">Kunde:</span> {{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }}</div>
            @if(!empty($data['contact']['firma']))
                <div><span class="label">Firma:</span> {{ $data['contact']['firma'] }}</div>
            @endif
            <div><span class="label">E-Mail:</span> <a href="mailto:{{ $data['contact']['email'] }}">{{ $data['contact']['email'] }}</a></div>
            <div><span class="label">Telefon:</span> {{ $data['contact']['telefon'] ?? '-' }}</div>
        </div>
    </div>

    @if(!empty($data['contact']['anmerkung']))
        <div class="section">
            <strong>Kunden-Anmerkung:</strong><br>
            <em style="background: #ffffcc; display: block; padding: 10px;">"{{ $data['contact']['anmerkung'] }}"</em>
        </div>
    @endif

    <h3>Positionen</h3>
    <table>
        <thead>
        <tr>
            <th>Artikel</th>
            <th>Menge</th>
            <th style="text-align: right;">Preis (Stk)</th>
        </tr>
        </thead>
        <tbody>
        @foreach($data['items'] as $item)
            <tr>
                <td>
                    <strong>{{ $item['name'] }}</strong>

                    {{-- Config Details --}}
                    @if(!empty($item['config']))
                        <div style="font-size: 11px; color: #666; margin-top: 5px;">
                            @if(!empty($item['config']['text']))
                                Gravur: "{{ $item['config']['text'] }}"<br>
                            @endif

                            {{-- DATEIEN --}}
                            @if(!empty($item['config']['logo_storage_path']))
                                <span class="file-info">
                                            📂 Datei: {{ $item['config']['logo_storage_path'] }}<br>
                                            (Gesichert im Storage Ordner)
                                        </span>
                            @endif

                            @if(!empty($item['config']['notes']))
                                <span style="color: #d35400;">Interne Note: {{ $item['config']['notes'] }}</span>
                            @endif
                        </div>
                    @endif
                </td>
                <td>{{ $item['quantity'] }}</td>
                <td style="text-align: right;">{{ $item['single_price'] }} €</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div style="text-align: right; margin-top: 20px; font-size: 14px;">
        <div>Netto: {{ $data['total_netto'] }} €</div>
        <div>MwSt: {{ $data['total_vat'] }} €</div>
        <div style="font-weight: bold; font-size: 16px; margin-top: 5px;">Brutto: {{ $data['total_gross'] }} €</div>
    </div>

    <div style="margin-top: 30px; font-size: 11px; color: #999; text-align: center;">
        Diese Anfrage wurde automatisch im Backend unter "Angebotsanfragen" gespeichert.<br>
        Das PDF wurde generiert und dem Kunden gesendet.
    </div>
</div>
</body>
</html>
