<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Steuerprotokoll & UStVA - {{ $data['month_number'] }}/{{ $data['year'] }}</title>
    <style>
        @page { margin: 40px 40px 100px 40px; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #222; line-height: 1.5; font-size: 11px; background: #fff; }

        /* HEADER */
        .header { width: 100%; border-bottom: 2px solid #C5A059; padding-bottom: 15px; margin-bottom: 25px; }
        .header td { vertical-align: bottom; }
        .logo { width: 180px; }
        h1 { font-size: 18px; margin: 0; color: #111; text-transform: uppercase; letter-spacing: 1px; }
        .subtitle { font-size: 11px; color: #666; margin-top: 5px; }
        .report-meta { text-align: right; }

        /* STAMMDATEN BOX */
        .info-box { background: #f9f9f9; border: 1px solid #eee; border-radius: 6px; padding: 15px; margin-bottom: 25px; width: 100%; }
        .info-box td { vertical-align: top; width: 50%; }
        .info-title { font-size: 9px; font-weight: bold; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; display: block; }
        .info-value { font-size: 12px; font-weight: bold; color: #111; margin-bottom: 10px; }

        /* KPI ZUSAMMENFASSUNG */
        .kpi-table { width: 100%; margin-bottom: 30px; border-collapse: separate; border-spacing: 10px 0; }
        .kpi-cell { background: #fff; border: 1px solid #ddd; border-top: 3px solid #C5A059; padding: 15px; text-align: center; border-radius: 4px; width: 33%; }
        .kpi-label { font-size: 9px; text-transform: uppercase; color: #888; font-weight: bold; letter-spacing: 1px; }
        .kpi-val { font-size: 18px; font-weight: bold; margin-top: 8px; color: #222; }

        h2 { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #C5A059; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-top: 30px; margin-bottom: 15px; }

        /* CALCULATION TABLE (UStVA Schema) */
        .calc-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 20px; border: 1px solid #ddd; }
        .calc-table td { padding: 8px 12px; border-bottom: 1px solid #eee; }
        .calc-table .label { color: #555; }
        .calc-table .amount { text-align: right; font-family: monospace; font-size: 12px; }
        .calc-table .sum-row { background: #fdfdfd; font-weight: bold; border-top: 1px solid #aaa; border-bottom: 1px solid #aaa; }
        .calc-table .final-row { background: #fcfbf9; font-weight: bold; font-size: 14px; color: #111; border-top: 2px solid #C5A059; }

        /* LIST TABLES (Belege) */
        table.list { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10px; }
        table.list th { text-align: left; background: #f4f4f4; padding: 8px; border-bottom: 2px solid #ccc; color: #444; font-weight: bold; }
        table.list td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: middle; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .page-break { page-break-after: always; }
        .disclaimer { font-size: 9px; color: #777; line-height: 1.4; padding: 10px; background: #fdfdfd; border: 1px solid #eee; margin-top: 20px; text-align: justify; }

        /* FOOTER POSITIONING */
        .footer-wrapper { position: fixed; bottom: -80px; left: 0; right: 0; width: 100%; }
    </style>
</head>
<body>

<table class="header">
    <tr>
        <td style="width: 50%;">
            <img src="{{ public_path('images/projekt/logo/mein-seelenfunke-logo.png') }}" class="logo" alt="Logo">
        </td>
        <td class="report-meta" style="width: 50%;">
            <h1>Steuer- & Umsatzprotokoll</h1>
            <div class="subtitle">Veranlagungszeitraum: <strong>{{ $data['month_name'] }} {{ $data['year'] }}</strong></div>
            <div class="subtitle">Datum der Erstellung: {{ date('d.m.Y - H:i') }} Uhr</div>
        </td>
    </tr>
</table>

<table class="info-box">
    <tr>
        <td>
            <span class="info-title">Mandant / Unternehmen</span>
            <div class="info-value">
                {{ $data['company']['name'] ?? 'Mein Seelenfunke' }}<br>
                <span style="font-weight: normal; font-size: 11px;">
                    Inh. {{ $data['company']['owner'] ?? '' }}<br>
                    {{ $data['company']['street'] ?? '' }}<br>
                    {{ $data['company']['city'] ?? '' }}
                </span>
            </div>
            <span class="info-title">Art der Anmeldung</span>
            <div class="info-value" style="color: #C5A059;">{{ $data['submission_type'] ?? 'Erstübermittlung' }}</div>
        </td>
        <td>
            <span class="info-title">Finanzamt Daten</span>
            <div class="info-value">
                <span style="font-weight: normal; font-size: 11px; display: inline-block; width: 80px;">Steuernummer:</span> {{ $data['company']['tax_id'] ?? 'n.a.' }}<br>
                <span style="font-weight: normal; font-size: 11px; display: inline-block; width: 80px;">USt-IdNr.:</span> {{ $data['company']['ust_id'] ?? 'n.a.' }}
            </div>
            <span class="info-title">System-Validierung</span>
            <div class="info-value">
                <span style="font-weight: normal; font-size: 11px;">Belege digital hinterlegt: {{ $data['progress'] == 100 ? 'Ja (100%)' : 'Nein (Unvollständig)' }}</span>
            </div>
        </td>
    </tr>
</table>

<h2>Umsatzsteuer-Voranmeldung (Berechnungsschema)</h2>
<p style="font-size: 10px; color: #555; margin-bottom: 10px;">Dieses Schema bildet die Grundlage für die Übermittlung der Zahllast an das zuständige Finanzamt gemäß UStG.</p>

<table class="calc-table">
    <tr>
        <td class="label">Umsatzsteuer (aus steuerpflichtigen Verkäufen)</td>
        <td class="amount">+ {{ number_format($data['vat_collected'], 2, ',', '.') }} €</td>
    </tr>
    <tr>
        <td class="label">Steuer aus innergemeinschaftlichem Erwerb (§ 1a UStG)</td>
        <td class="amount">+ {{ number_format($data['ig_erwerb_tax'], 2, ',', '.') }} €</td>
    </tr>
    <tr>
        <td class="label">Steuer nach § 13b UStG (Reverse-Charge-Verfahren)</td>
        <td class="amount">+ {{ number_format($data['paragraph_13b_tax'], 2, ',', '.') }} €</td>
    </tr>
    <tr class="sum-row">
        <td class="label" style="text-align: right;">Zwischensumme Gesamtsteuer:</td>
        <td class="amount">{{ number_format($data['total_tax'], 2, ',', '.') }} €</td>
    </tr>
    <tr>
        <td class="label">Abziehbare Vorsteuer (aus Eingangsrechnungen / Fixkosten)</td>
        <td class="amount" style="color: #dc2626;">- {{ number_format($data['vat_paid'], 2, ',', '.') }} €</td>
    </tr>
    <tr class="final-row">
        <td class="label">{{ $data['zahllast'] > 0 ? 'Verbleibende Umsatzsteuer-Vorauszahlung (Zahllast)' : 'Verbleibender Erstattungsanspruch' }}</td>
        <td class="amount" style="color: #C5A059;">{{ number_format($data['zahllast'], 2, ',', '.') }} €</td>
    </tr>
</table>

<div class="disclaimer">
    <strong>Rechtlicher Hinweis:</strong> Dieses Dokument wurde maschinell durch das ERP-System "Mein Seelenfunke" erstellt. Die zugrundeliegenden CSV-Daten (EXTF-Format) für DATEV-Schnittstellen sowie die digitalen Kopien der Eingangsbelege befinden sich im zugehörigen ZIP-Exportarchiv. Die Aufbewahrungsfrist für dieses Dokument und die digitalen Belege beträgt 10 Jahre nach Ablauf des Kalenderjahres (vgl. § 147 AO, § 14b UStG).
</div>

<div class="page-break"></div>

<h2>Betriebswirtschaftliche Auswertung (EÜR Übersicht)</h2>
<table class="kpi-table">
    <tr>
        <td class="kpi-cell">
            <div class="kpi-label">Umsatz (Netto)</div>
            <div class="kpi-val" style="color: #16a34a;">+ {{ number_format($data['revenue_net'], 2, ',', '.') }} €</div>
            <div style="font-size: 8px; color: #999; margin-top: 5px;">Aus {{ $data['order_count'] }} Bestellungen</div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-label">Kosten (Netto)</div>
            <div class="kpi-val" style="color: #dc2626;">- {{ number_format($data['expenses_net'], 2, ',', '.') }} €</div>
            <div style="font-size: 8px; color: #999; margin-top: 5px;">Aus {{ $data['expense_count'] }} Positionen</div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-label">Vorl. Gewinn (EÜR)</div>
            <div class="kpi-val">{{ number_format($data['profit'], 2, ',', '.') }} €</div>
            <div style="font-size: 8px; color: #999; margin-top: 5px;">Monatsergebnis</div>
        </td>
    </tr>
</table>

<h2>Einzelnachweis: Eingangsrechnungen & Ausgaben</h2>
<table class="list">
    <thead>
    <tr>
        <th>Datum</th>
        <th>Typ / Kategorie</th>
        <th>Bezeichnung / Verwendungszweck</th>
        <th class="text-center">Belegnr.</th>
        <th class="text-right">Brutto</th>
        <th class="text-right">Darin Vorsteuer</th>
    </tr>
    </thead>
    <tbody>
    @foreach($data['raw_specials'] as $sp)
        @php
            $rate = $sp->tax_rate ?? 19;
            $amt = abs($sp->amount);
            $vPaid = $amt - ($amt / (1 + ($rate / 100)));
        @endphp
        <tr>
            <td>{{ \Carbon\Carbon::parse($sp->execution_date)->format('d.m.Y') }}</td>
            <td>Variabel ({{ $sp->category }})</td>
            <td>{{ Str::limit($sp->title, 40) }}</td>
            <td class="text-center">{{ $sp->invoice_number ?? '-' }}</td>
            <td class="text-right">{{ number_format($amt, 2, ',', '.') }} €</td>
            <td class="text-right">{{ number_format($vPaid, 2, ',', '.') }} €</td>
        </tr>
    @endforeach

    @foreach($data['raw_fixed'] as $fix)
        @php
            $amt = abs($fix->amount);
            $vPaid = $amt - ($amt / 1.19);
        @endphp
        <tr>
            <td>Wiederkehrend</td>
            <td>Fixkosten</td>
            <td>{{ Str::limit($fix->name, 40) }}</td>
            <td class="text-center">-</td>
            <td class="text-right">{{ number_format($amt, 2, ',', '.') }} €</td>
            <td class="text-right">{{ number_format($vPaid, 2, ',', '.') }} €</td>
        </tr>
    @endforeach

    @if($data['expense_count'] == 0)
        <tr><td colspan="6" class="text-center" style="padding: 20px; color: #999; font-style: italic;">Keine Ausgaben in diesem Zeitraum verbucht.</td></tr>
    @endif
    </tbody>
</table>

<div class="footer-wrapper">
    @include('global.mails.partials.mail_footer')
</div>

</body>
</html>
