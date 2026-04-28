<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finanz- & Liquiditätsbericht {{ $month }}/{{ $year }}</title>
    <style>
        @page { margin: 40px; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #222; line-height: 1.5; font-size: 10px; }
        .header { border-bottom: 2px solid #111; padding-bottom: 15px; margin-bottom: 25px; display: table; width: 100%; }
        .header-left { display: table-cell; vertical-align: bottom; }
        .header-right { display: table-cell; vertical-align: bottom; text-align: right; }
        h1 { font-size: 22px; margin: 0 0 5px 0; color: #111; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .subtitle { font-size: 12px; color: #555; }
        
        .section-title { font-size: 14px; margin-top: 30px; margin-bottom: 15px; padding-bottom: 5px; border-bottom: 1px solid #ddd; color: #333; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px; }
        
        .kpis { width: 100%; margin-bottom: 30px; border-collapse: separate; border-spacing: 10px 0; }
        .kpis td { width: 25%; padding: 15px; background: #f8f9fa; border: 1px solid #eaeaea; text-align: center; border-radius: 4px; }
        .kpi-label { font-size: 9px; text-transform: uppercase; color: #777; font-weight: bold; letter-spacing: 0.5px; }
        .kpi-value { font-size: 16px; font-weight: bold; margin-top: 8px; color: #111; }
        .text-green { color: #059669; } .text-red { color: #dc2626; } .text-orange { color: #ea580c; }
        
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9px; }
        table.data-table th { text-align: left; background: #f3f4f6; padding: 10px 8px; border-bottom: 2px solid #ccc; color: #444; font-weight: bold; text-transform: uppercase; }
        table.data-table td { padding: 8px; border-bottom: 1px solid #eee; }
        table.data-table .text-right { text-align: right; }
        table.data-table .text-center { text-align: center; }
        table.data-table tbody tr:nth-child(even) { background-color: #fafafa; }
        table.data-table tfoot td { font-weight: bold; background: #f3f4f6; border-top: 2px solid #ccc; }

        .summary-box { background: #f8f9fa; padding: 15px; border-left: 4px solid #111; margin-bottom: 30px; }
        .summary-box p { margin: 5px 0; font-size: 11px; }
        .summary-box .bold { font-weight: bold; }

        .footer { position: fixed; bottom: -10px; left: 0; right: 0; text-align: center; font-size: 8px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>

<div class="header">
    <div class="header-left">
        <h1>Management Report</h1>
        <div class="subtitle">{{ \Carbon\Carbon::createFromDate($year, $month, 1)->locale('de')->monthName }} {{ $year }}</div>
    </div>
    <div class="header-right">
        <div class="subtitle">Erstellt am: {{ date('d.m.Y') }}</div>
        <div class="subtitle">Währung: EUR (€)</div>
    </div>
</div>

<div class="section-title">1. Executive Summary</div>
<table class="kpis">
    <tr>
        <td>
            <div class="kpi-label">Umsatz (Netto)</div>
            <div class="kpi-value text-green">{{ number_format($shopStats['net'], 2, ',', '.') }}</div>
        </td>
        <td>
            <div class="kpi-label">Umsatz (Brutto)</div>
            <div class="kpi-value">{{ number_format($shopStats['gross'], 2, ',', '.') }}</div>
        </td>
        <td>
            <div class="kpi-label">Betriebsausgaben (Netto)</div>
            <div class="kpi-value text-red">
                @php
                    $netExpenses = abs($statsNetto['fixed_expenses'] + $statsNetto['special_expenses']);
                @endphp
                {{ number_format($netExpenses, 2, ',', '.') }}
            </div>
        </td>
        <td>
            <div class="kpi-label">Liquiditätssaldo (Netto)</div>
            <div class="kpi-value {{ $statsNetto['available'] >= 0 ? 'text-green' : 'text-red' }}">
                {{ number_format($statsNetto['available'], 2, ',', '.') }}
            </div>
        </td>
    </tr>
</table>

<div class="section-title">2. E-Commerce Performance</div>
<div class="summary-box">
    <table style="width: 100%; border: none; font-size: 11px;">
        <tr>
            <td style="width: 50%;">
                <p><span class="bold">Anzahl Bestellungen:</span> {{ $shopStats['count'] }}</p>
                <p><span class="bold">Anzeigen / Retouren:</span> {{ $shopStats['returns'] }}</p>
                <p><span class="bold">AOV (Average Order Value):</span> {{ number_format($shopStats['aov'], 2, ',', '.') }} €</p>
            </td>
            <td style="width: 50%;">
                <p><span class="bold">Shop Umsatz Brutto:</span> {{ number_format($shopStats['gross'], 2, ',', '.') }} €</p>
                <p><span class="bold">Abgeführte USt.:</span> {{ number_format($shopStats['tax'], 2, ',', '.') }} €</p>
                <p><span class="bold">Shop Umsatz Netto:</span> <span class="text-green">{{ number_format($shopStats['net'], 2, ',', '.') }} €</span></p>
            </td>
        </tr>
    </table>
</div>

<div class="section-title">3. Liquiditätsvorschau (Nächste 3 Monate)</div>
<table class="data-table">
    <thead>
        <tr>
            <th>Monat</th>
            <th class="text-right">Erwartete Einnahmen (Netto)*</th>
            <th class="text-right">Fällige Fixkosten (Netto)</th>
            <th class="text-right">Vorauss. Cashflow</th>
        </tr>
    </thead>
    <tbody>
        @foreach($liquidityPreview as $lp)
            @php $cashflow = $lp['expected_income'] - $lp['expected_fixed_costs']; @endphp
            <tr>
                <td><span class="bold">{{ $lp['month'] }} {{ $lp['year'] }}</span></td>
                <td class="text-right text-green">+ {{ number_format($lp['expected_income'], 2, ',', '.') }} €</td>
                <td class="text-right text-red">- {{ number_format($lp['expected_fixed_costs'], 2, ',', '.') }} €</td>
                <td class="text-right {{ $cashflow >= 0 ? 'text-green' : 'text-red' }} bold">{{ number_format($cashflow, 2, ',', '.') }} €</td>
            </tr>
        @endforeach
    </tbody>
</table>
<p style="font-size: 8px; color: #777; margin-top: -15px; margin-bottom: 20px;">* Einnahmen basieren auf dem durchschnittlichen Netto-Shopumsatz der letzten 3 Monate.</p>

<div class="page-break"></div>

<div class="header">
    <div class="header-left">
        <h1>Kostenaufstellung</h1>
        <div class="subtitle">Detaillierte Übersicht aller Ausgaben inkl. Steuer</div>
    </div>
</div>

<div class="section-title">4. Fixkosten ({{ \Carbon\Carbon::createFromDate($year, $month, 1)->locale('de')->monthName }})</div>
<table class="data-table">
    <thead>
        <tr>
            <th>Bezeichnung</th>
            <th>Kategorie</th>
            <th class="text-center">Typ</th>
            <th class="text-right">Steuersatz</th>
            <th class="text-right">Steuer</th>
            <th class="text-right">Netto</th>
            <th class="text-right">Brutto</th>
        </tr>
    </thead>
    <tbody>
        @php $fNet = 0; $fTax = 0; $fGross = 0; @endphp
        @forelse($fixedCosts as $f)
            @php
                $gross = $f->amount;
                $net = $gross / (1 + ($f->tax_rate / 100));
                $tax = $gross - $net;
                $fGross += $gross; $fNet += $net; $fTax += $tax;
            @endphp
            <tr>
                <td>{{ $f->name }}</td>
                <td>{{ $f->category }}</td>
                <td class="text-center">{{ $f->is_business ? 'Gewerbe' : 'Privat' }}</td>
                <td class="text-right">{{ $f->tax_rate }} %</td>
                <td class="text-right">{{ number_format($tax, 2, ',', '.') }} €</td>
                <td class="text-right">{{ number_format($net, 2, ',', '.') }} €</td>
                <td class="text-right">{{ number_format($gross, 2, ',', '.') }} €</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center">Keine Fixkosten in diesem Zeitraum fällig.</td></tr>
        @endforelse
    </tbody>
    @if($fixedCosts->count() > 0)
    <tfoot>
        <tr>
            <td colspan="4" class="text-right">Gesamtsumme Fixkosten:</td>
            <td class="text-right">{{ number_format($fTax, 2, ',', '.') }} €</td>
            <td class="text-right">{{ number_format($fNet, 2, ',', '.') }} €</td>
            <td class="text-right text-red">{{ number_format($fGross, 2, ',', '.') }} €</td>
        </tr>
    </tfoot>
    @endif
</table>

<div class="section-title">5. Variable Kosten / Sonderausgaben</div>
<table class="data-table">
    <thead>
        <tr>
            <th>Datum</th>
            <th>Bezeichnung</th>
            <th>Kategorie</th>
            <th class="text-center">Typ</th>
            <th class="text-right">Steuersatz</th>
            <th class="text-right">Steuer</th>
            <th class="text-right">Netto</th>
            <th class="text-right">Brutto</th>
        </tr>
    </thead>
    <tbody>
        @php $sNet = 0; $sTax = 0; $sGross = 0; @endphp
        @forelse($specials as $s)
            @php
                $gross = $s->amount;
                // Positive Beträge sind Einnahmen/Rückerstattungen
                $sign = $gross < 0 ? -1 : 1; 
                $taxStr = $s->tax_rate ?? 0;
                $net = $gross / (1 + ($taxStr / 100));
                $tax = $gross - $net;
                $sGross += $gross; $sNet += $net; $sTax += $tax;
            @endphp
            <tr>
                <td>{{ $s->execution_date->format('d.m.Y') }}</td>
                <td>{{ $s->title }} <br><span style="font-size: 7px; color: #888;">{{ $s->invoice_number }}</span></td>
                <td>{{ $s->category }}</td>
                <td class="text-center">{{ $s->is_business ? 'Gewerbe' : 'Privat' }}</td>
                <td class="text-right">{{ $taxStr }} %</td>
                <td class="text-right">{{ number_format($tax, 2, ',', '.') }} €</td>
                <td class="text-right {{ $sign > 0 ? 'text-green' : '' }}">{{ number_format($net, 2, ',', '.') }} €</td>
                <td class="text-right {{ $sign > 0 ? 'text-green' : '' }}">{{ number_format($gross, 2, ',', '.') }} €</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center">Keine variablen Sonderausgaben in diesem Monat gebucht.</td></tr>
        @endforelse
    </tbody>
    @if($specials->count() > 0)
    <tfoot>
        <tr>
            <td colspan="5" class="text-right">Gesamtsumme Sonderausgaben:</td>
            <td class="text-right">{{ number_format($sTax, 2, ',', '.') }} €</td>
            <td class="text-right">{{ number_format($sNet, 2, ',', '.') }} €</td>
            <td class="text-right text-orange">{{ number_format($sGross, 2, ',', '.') }} €</td>
        </tr>
    </tfoot>
    @endif
</table>

<div class="footer">
    Dieser Bericht wurde automatisch generiert von "Mein Seelenfunke" | {{ date('d.m.Y H:i:s') }}
</div>
</body>
</html>
