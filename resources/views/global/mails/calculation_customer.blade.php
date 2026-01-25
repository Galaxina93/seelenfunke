<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; color: #4a4a4a; line-height: 1.6; }
        h2 { color: #C5A059; }
        ul { list-style: none; padding: 0; }
        li { margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .express-note { color: #dc2626; font-weight: bold; }
        .totals-block { margin-top: 20px; border-top: 2px solid #C5A059; padding-top: 10px; }
        .total-row { display: flex; justify-content: space-between; max-width: 300px; margin-bottom: 5px; }
        .gross { font-weight: bold; font-size: 1.1em; color: #333; margin-top: 5px; border-top: 1px solid #eee; padding-top: 5px; }
        .price-info { font-size: 0.9em; color: #666; }
    </style>
</head>
<body>
<h2>Vielen Dank für deine Anfrage!</h2>

<p>Hallo {{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }},</p>

<p>
    Wir haben deine Anfrage für <strong>Mein Seelenfunke</strong> erhalten. <br>
    Gerade bei größeren Mengen oder individuellen Anfertigungen ist es uns sehr wichtig, sicherzustellen, dass wir deine Wünsche perfekt umsetzen können.
</p>

@if(!empty($data['express']) || !empty($data['logo_url']))
    <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #C5A059; margin: 20px 0;">
        @if(!empty($data['express']))
            <p class="express-note">⚡ Express-Service gebucht (Wunschtermin: {{ \Carbon\Carbon::parse($data['deadline'])->format('d.m.Y') }})</p>
        @endif
        @if(!empty($data['logo_url']))
            <p>✔ Deine Logo-Datei wurde erfolgreich übertragen.</p>
        @endif
    </div>
@endif

<p>Im Anhang findest du eine unverbindliche Übersicht deiner Zusammenstellung als PDF.</p>

<h3>Deine Zusammenfassung:</h3>
<ul>
    @foreach($data['items'] as $item)
        <li>
            <strong>{{ $item['quantity'] }}x {{ $item['name'] }}</strong><br>
            <span class="price-info">
                Einzelpreis (Brutto): {{ $item['single_price'] }} € | Gesamt (Netto): {{ $item['total_price'] }} €
            </span>
        </li>
    @endforeach
</ul>

{{-- NEU: Detaillierte Summen --}}
<div class="totals-block">
    <p>
        Summe (Netto): {{ $data['total_netto'] }} €<br>
        <span style="color: #666; font-size: 0.9em;">zzgl. 19% MwSt.: {{ $data['total_vat'] }} €</span><br>
        <strong style="font-size: 1.1em; color: #000;">Endsumme (Brutto): {{ $data['total_gross'] }} €</strong>
    </p>
</div>

<p>
    Wir werden uns schnellstmöglich bei dir melden, um die Details zu besprechen oder dir die offizielle Auftragsbestätigung zu senden.
</p>

<p>
    Herzliche Grüße,<br>
    <strong>Mein Seelenfunke</strong><br>
</p>
</body>
</html>
