<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finanzbericht {{ $month }}/{{ $year }}</title>
    <style>
        body { font-family: 'Helvetica', Arial, sans-serif; color: #333; line-height: 1.4; padding: 20px; }
        .header { margin-bottom: 40px; border-bottom: 1px solid #ddd; padding-bottom: 20px; }
        .logo { width: 200px; margin-bottom: 10px; }
        h1 { font-size: 24px; margin: 0; color: #333; }
        .subtitle { font-size: 14px; color: #666; }

        .kpis { width: 100%; margin-bottom: 40px; border-collapse: collapse; }
        .kpis td { width: 33%; padding: 15px; border: 1px solid #eee; text-align: center; }
        .kpi-label { font-size: 10px; text-transform: uppercase; color: #888; font-weight: bold; }
        .kpi-value { font-size: 20px; font-weight: bold; margin-top: 5px; }
        .text-green { color: #10b981; } .text-red { color: #ef4444; } .text-orange { color: #f97316; }

        h2 { font-size: 16px; margin-bottom: 15px; border-bottom: 2px solid #eee; padding-bottom: 5px; }

        .table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 30px; }
        .table th { text-align: left; background: #f9fafb; padding: 8px; border-bottom: 1px solid #ddd; color: #666; font-weight: bold; text-transform: uppercase; }
        .table td { padding: 8px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }

        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
<div class="header">
    {{-- Logo falls vorhanden --}}
    <h1>Finanzbericht</h1>
    <div class="subtitle">Zeitraum: {{ \Carbon\Carbon::createFromDate($year, $month, 1)->locale('de')->monthName }} {{ $year }}</div>
    <div class="subtitle">Erstellt am: {{ date('d.m.Y') }}</div>
</div>

<table class="kpis">
    <tr>
        <td>
            <div class="kpi-label">Gesamtbudget (Einnahmen)</div>
            <div class="kpi-value text-green">+ {{ number_format($stats['total_budget'], 2, ',', '.') }} €</div>
        </td>
        <td>
            <div class="kpi-label">Gesamtausgaben (Fix + Variabel)</div>
            <div class="kpi-value text-red">{{ number_format($stats['total_spent'], 2, ',', '.') }} €</div>
        </td>
        <td>
            <div class="kpi-label">Ergebnis (Gewinn/Verlust)</div>
            <div class="kpi-value {{ $stats['available'] >= 0 ? 'text-green' : 'text-red' }}">
                {{ number_format($stats['available'], 2, ',', '.') }} €
            </div>
        </td>
    </tr>
</table>

{{-- Gewerbliche Ausgaben Liste --}}
<h2>Gewerbliche Ausgaben & Investitionen</h2>
<table class="table">
    <thead>
    <tr>
        <th>Datum</th>
        <th>Bezeichnung</th>
        <th>Kategorie</th>
        <th>Rechnungsnr.</th>
        <th class="text-right">Steuer</th>
        <th class="text-right">Betrag (Brutto)</th>
    </tr>
    </thead>
    <tbody>
    @forelse($specials as $s)
        <tr>
            <td>{{ $s->execution_date->format('d.m.Y') }}</td>
            <td>{{ $s->title }}</td>
            <td>{{ $s->category }}</td>
            <td>{{ $s->invoice_number ?? '-' }}</td>
            <td class="text-right">{{ $s->tax_rate }}%</td>
            <td class="text-right">{{ number_format($s->amount, 2, ',', '.') }} €</td>
        </tr>
    @empty
        <tr><td colspan="6" style="text-align: center; padding: 20px;">Keine gewerblichen Ausgaben in diesem Zeitraum.</td></tr>
    @endforelse
    </tbody>
    <tfoot>
    <tr style="background: #fdfdfd; font-weight: bold;">
        <td colspan="5" class="text-right">Gesamtsumme:</td>
        <td class="text-right">{{ number_format($specials->sum('amount'), 2, ',', '.') }} €</td>
    </tr>
    </tfoot>
</table>

<div class="footer">
    Dieses Dokument wurde automatisch erstellt von "Mein Seelenfunke - Finanzmanager".
</div>
</body>
</html>
