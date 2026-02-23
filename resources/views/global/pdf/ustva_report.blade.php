<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>UStVA Report {{ $data['month_number'] }}/{{ $data['year'] }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; line-height: 1.5; padding: 30px; font-size: 11px; background: #fff; }
        .header { border-bottom: 3px solid #C5A059; padding-bottom: 20px; margin-bottom: 40px; display: table; width: 100%; }
        .logo { width: 200px; }
        .header-left { display: table-cell; vertical-align: bottom; }
        .header-right { display: table-cell; vertical-align: bottom; text-align: right; }
        h1 { font-size: 20px; margin: 0; color: #111; text-transform: uppercase; letter-spacing: 1px; }
        .subtitle { font-size: 12px; color: #666; margin-top: 5px; }

        .kpi-container { width: 100%; margin-bottom: 40px; border-collapse: separate; border-spacing: 15px 0; }
        .kpi-box { background: #fcfbf9; border: 1px solid #eee; border-radius: 8px; padding: 20px; text-align: center; }
        .kpi-box.highlight { border-color: #C5A059; background: #fffdf8; }
        .kpi-label { font-size: 9px; text-transform: uppercase; color: #888; font-weight: bold; letter-spacing: 1.5px; }
        .kpi-value { font-size: 24px; font-weight: bold; margin-top: 10px; color: #222; }
        .text-green { color: #10b981; }
        .text-red { color: #ef4444; }

        h2 { font-size: 14px; text-transform: uppercase; letter-spacing: 1.5px; color: #C5A059; border-bottom: 1px solid #eee; padding-bottom: 6px; margin-top: 40px; margin-bottom: 15px; }

        table.list { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10px; }
        table.list th { text-align: left; background: #f4f4f4; padding: 10px 8px; border-bottom: 2px solid #ddd; color: #444; text-transform: uppercase; letter-spacing: 0.5px; font-weight: bold; }
        table.list td { padding: 10px 8px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .bwa-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11px; border: 1px solid #eee; }
        .bwa-table td { padding: 12px; border-bottom: 1px solid #eee; }
        .bwa-table .indent { padding-left: 30px; color: #555; }
        .bwa-table .total-row { background: #fcfbf9; font-weight: bold; font-size: 13px; }

        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #999; border-top: 1px solid #eee; padding-top: 15px; letter-spacing: 1px; text-transform: uppercase; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="{{ public_path('images/projekt/logo/mein-seelenfunke-logo.png') }}" class="logo" alt="Logo">
    </div>
    <div class="header-right">
        <h1>Steuer Report</h1>
        <div class="subtitle">Abrechnungsmonat: <strong>{{ $data['month_name'] }} {{ $data['year'] }}</strong></div>
    </div>
</div>

<table class="kpi-container">
    <tr>
        <td class="kpi-box">
            <div class="kpi-label">Umsatzsteuer (Eingenommen)</div>
            <div class="kpi-value text-green">+ {{ number_format($data['vat_collected'], 2, ',', '.') }} €</div>
            <div style="font-size: 8px; color: #999; margin-top: 8px;">Aus {{ $data['raw_orders']->count() }} Shop-Bestellungen</div>
        </td>
        <td class="kpi-box">
            <div class="kpi-label">Vorsteuer (Ausgaben/Abzug)</div>
            <div class="kpi-value text-red">- {{ number_format($data['vat_paid'], 2, ',', '.') }} €</div>
            <div style="font-size: 8px; color: #999; margin-top: 8px;">Aus {{ $data['raw_specials']->count() + $data['raw_fixed']->count() }} Ausgaben (Belege)</div>
        </td>
        <td class="kpi-box highlight">
            <div class="kpi-label" style="color: #C5A059;">Verrechnete Zahllast</div>
            <div class="kpi-value">{{ number_format($data['zahllast'], 2, ',', '.') }} €</div>
            <div style="font-size: 8px; color: #C5A059; margin-top: 8px; text-transform: uppercase; font-weight: bold;">{{ $data['zahllast'] > 0 ? 'Nachzahlung ans Finanzamt' : 'Erstattung vom Finanzamt' }}</div>
        </td>
    </tr>
</table>

<h2>Betriebswirtschaftliche Auswertung (EÜR & GuV)</h2>
<table class="bwa-table">
    <tr>
        <td><strong>Betriebseinnahmen (Umsatz Netto)</strong></td>
        <td class="text-right text-green">+ {{ number_format($data['revenue_net'], 2, ',', '.') }} €</td>
    </tr>
    <tr>
        <td class="indent">Abzüglich: Variable Ausgaben (Wareneinsatz etc.) Netto</td>
        @php
            $varNet = 0;
            foreach($data['raw_specials'] as $s) {
                $rate = $s->tax_rate ?? 19;
                $varNet += abs($s->amount) / (1 + ($rate/100));
            }
        @endphp
        <td class="text-right">- {{ number_format($varNet, 2, ',', '.') }} €</td>
    </tr>
    <tr>
        <td class="indent">Abzüglich: Fixkosten (Miete, Server, etc.) Netto</td>
        @php
            $fixNet = 0;
            foreach($data['raw_fixed'] as $f) {
                $fixNet += abs($f->amount) / 1.19;
            }
        @endphp
        <td class="text-right">- {{ number_format($fixNet, 2, ',', '.') }} €</td>
    </tr>
    <tr class="total-row">
        <td style="color: #C5A059;">Vorläufiger Gewinn (Monatsergebnis EÜR)</td>
        <td class="text-right" style="color: #C5A059;">{{ number_format($data['profit'], 2, ',', '.') }} €</td>
    </tr>
</table>

<div class="page-break"></div>

<h2>Einzelnachweis: Betriebsausgaben (Variabel)</h2>
<table class="list">
    <thead>
    <tr>
        <th>Datum</th>
        <th>Kategorie</th>
        <th>Verwendungszweck</th>
        <th class="text-center">Belegnr.</th>
        <th class="text-right">Brutto</th>
        <th class="text-right">Darin Vorsteuer</th>
    </tr>
    </thead>
    <tbody>
    @forelse($data['raw_specials'] as $sp)
        @php
            $rate = $sp->tax_rate ?? 19;
            $amt = abs($sp->amount);
            $vPaid = $amt - ($amt / (1 + ($rate / 100)));
        @endphp
        <tr>
            <td>{{ \Carbon\Carbon::parse($sp->execution_date)->format('d.m.Y') }}</td>
            <td>{{ $sp->category }}</td>
            <td>{{ $sp->title }}</td>
            <td class="text-center">{{ $sp->invoice_number ?? '-' }}</td>
            <td class="text-right">{{ number_format($amt, 2, ',', '.') }} €</td>
            <td class="text-right">{{ number_format($vPaid, 2, ',', '.') }} €</td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center" style="padding: 20px; color: #999; font-style: italic;">Keine Variablen Ausgaben in diesem Monat.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>Einzelnachweis: Betriebsausgaben (Fixkosten)</h2>
<table class="list">
    <thead>
    <tr>
        <th>Kostenstelle / Vertrag</th>
        <th>Intervall</th>
        <th class="text-right">Brutto (Abbuchung)</th>
        <th class="text-right">Darin Vorsteuer (19%)</th>
    </tr>
    </thead>
    <tbody>
    @forelse($data['raw_fixed'] as $fix)
        @php
            $amt = abs($fix->amount);
            $vPaid = $amt - ($amt / 1.19);
        @endphp
        <tr>
            <td>{{ $fix->name }}</td>
            <td>Alle {{ $fix->interval_months }} Monat(e)</td>
            <td class="text-right">{{ number_format($amt, 2, ',', '.') }} €</td>
            <td class="text-right">{{ number_format($vPaid, 2, ',', '.') }} €</td>
        </tr>
    @empty
        <tr><td colspan="4" class="text-center" style="padding: 20px; color: #999; font-style: italic;">Keine Fixkosten-Abbuchungen in diesem Monat.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="footer">
    Automatisiert generiert durch das Mein Seelenfunke ERP-System am {{ date('d.m.Y') }} • Interne Verarbeitung
</div>
</body>
</html>
