<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; color: #333; }
        .label { font-weight: bold; width: 150px; display: inline-block; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; }
        .express-badge { color: white; background-color: #dc2626; padding: 5px 10px; font-weight: bold; border-radius: 4px; display: inline-block; margin-bottom: 10px; }
        .sub-info { font-size: 0.9em; color: #555; margin-top: 3px; }
        .totals-container { margin-top: 20px; text-align: right; border-top: 2px solid #ddd; padding-top: 10px; }
        .total-row { margin-bottom: 5px; }
        .total-gross { font-size: 1.2em; font-weight: bold; color: #000; margin-top: 5px; border-top: 1px solid #eee; padding-top: 5px; display: inline-block;}
    </style>
</head>
<body>

@if(!empty($data['express']) && $data['express'] == true)
    <div class="express-badge">⚠ ACHTUNG: EXPRESS-AUFTRAG</div>
@endif

<h2>Neue Anfrage (Mein Seelenfunke)</h2>

<h3>Kontaktdaten:</h3>
<div>
    <span class="label">Firma / Verein:</span> {{ $data['contact']['firma'] ?: '–' }}<br>
    <span class="label">Name:</span> {{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }}<br>
    <span class="label">E-Mail:</span> <a href="mailto:{{ $data['contact']['email'] }}">{{ $data['contact']['email'] }}</a><br>
    <span class="label">Telefon:</span> {{ $data['contact']['telefon'] ?: '–' }}<br>

    @if(!empty($data['express']) && !empty($data['deadline']))
        <span class="label" style="color: red;">Wunschtermin:</span> <strong>{{ \Carbon\Carbon::parse($data['deadline'])->format('d.m.Y') }}</strong><br>
    @endif
</div>

<h3>Gewählte Produkte & Konfiguration:</h3>
<table>
    <thead>
    <tr>
        <th>Artikel</th>
        <th>Konfiguration</th>
        <th>Menge</th>
        <th>Preis (Netto)</th>
    </tr>
    </thead>
    <tbody>
    @foreach($data['items'] as $item)
        <tr>
            <td>{{ $item['name'] }}</td>
            <td>
                @if(!empty($item['config']['text']))
                    <div class="sub-info"><strong>Gravur:</strong> "{{ $item['config']['text'] }}"</div>
                    <div class="sub-info">Schrift: {{ $item['config']['font'] }} | {{ $item['config']['align'] }}</div>

                    @if(isset($item['config']['text_x']))
                        <div class="sub-info">Pos: X:{{ round($item['config']['text_x']) }}% Y:{{ round($item['config']['text_y']) }}% ({{ round($item['config']['text_size']*100) }}%)</div>
                    @elseif(isset($item['config']['text_pos']))
                        <div class="sub-info">Pos: {{ $item['config']['text_pos'] }}</div>
                    @endif
                @endif

                @if(!empty($item['config']['logo_storage_path']))
                    <div class="sub-info" style="margin-top:5px;">
                        <strong>Logo:</strong>
                        <span style="color: green; font-weight: bold;">(Siehe E-Mail Anhang)</span>
                        <br>
                        <span style="font-size: 0.8em; color: #999;">(Datei ist sicher gespeichert)</span>
                        <br>
                        @if(isset($item['config']['logo_x']))
                            (Pos: X:{{ round($item['config']['logo_x']) }}% Y:{{ round($item['config']['logo_y']) }}% | {{ $item['config']['logo_size'] }}px)
                        @elseif(isset($item['config']['logo_pos']))
                            (Position: {{ $item['config']['logo_pos'] }})
                        @endif
                    </div>
                @endif

                @if(!empty($item['config']['notes']))
                    <div class="sub-info" style="margin-top:5px; background: #ffffdd; padding:3px;">
                        <strong>Note:</strong> {{ $item['config']['notes'] }}
                    </div>
                @endif
            </td>
            <td>{{ $item['quantity'] }} Stk.</td>
            <td>{{ $item['total_price'] }} €</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="totals-container">
    <div class="total-row">Summe (Netto): <strong>{{ $data['total_netto'] }} €</strong></div>
    <div class="total-row" style="color: #666;">zzgl. 19% MwSt.: {{ $data['total_vat'] }} €</div>
    <div class="total-gross">Endsumme (Brutto): {{ $data['total_gross'] }} €</div>
</div>

</body>
</html>
