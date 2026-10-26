<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Arbeitszeitnachweis {{ $month }}/{{ $year }} - {{ $contract->contract_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1f2937;
            line-height: 1.45;
            margin: 0;
            padding: 26px 34px;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #c5a059;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .brand-title {
            font-size: 18px;
            font-weight: bold;
            color: #111827;
        }
        .brand-subtitle {
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #c5a059;
            font-weight: bold;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            margin-bottom: 18px;
        }
        .items-table th {
            background: #111827;
            color: #ffffff;
            text-align: left;
            padding: 7px 9px;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .items-table td {
            padding: 6px 9px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9.5px;
        }
        .summary-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 16px;
        }
        .footer {
            position: fixed;
            bottom: 14px;
            left: 34px;
            right: 34px;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            font-size: 8px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <div class="brand-subtitle">Gesetzlicher Arbeitszeitnachweis (§ 17 MiLoG / § 4 ArbZG)</div>
                <div class="brand-title">Stundenzettel {{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) }}/{{ $year }}</div>
            </td>
            <td style="text-align: right; font-size: 9.5px; color: #4b5563;">
                Mitarbeiter: <strong>{{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: $contract->invitation_label }}</strong><br>
                Personal-Nr.: {{ $contract->contract_number }} ({{ $contract->employment_type_label }})
            </td>
        </tr>
    </table>

    <div class="summary-box">
        <strong>Monatszusammenfassung ({{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) }}/{{ $year }}):</strong><br>
        Erfasste Schichten: <strong>{{ $stats['entries_count'] }}</strong> |
        Netto-Arbeitszeit: <strong>{{ $stats['worked_hours_formatted'] }} ({{ number_format($stats['worked_hours_decimal'], 2, ',', '.') }} Std.)</strong> |
        Ruhepausen gesamt: <strong>{{ $stats['break_minutes'] }} Min.</strong> |
        Stundenlohn: <strong>{{ number_format($contract->hourly_rate_cents / 100, 2, ',', '.') }} €</strong> |
        Brutto-Entgeltanspruch: <strong>{{ number_format($stats['estimated_gross_cents'] / 100, 2, ',', '.') }} €</strong>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Beginn</th>
                <th>Ende</th>
                <th>Pause (§ 4 ArbZG)</th>
                <th>Netto-Dauer</th>
                <th>Art / Tätigkeit</th>
            </tr>
        </thead>
        <tbody>
            @forelse($entries as $entry)
                <tr>
                    <td><strong>{{ $entry->work_date ? $entry->work_date->format('d.m.Y') : '—' }}</strong></td>
                    <td>{{ $entry->started_at ? $entry->started_at->format('H:i') : '—' }} Uhr</td>
                    <td>{{ $entry->ended_at ? $entry->ended_at->format('H:i') : '—' }} Uhr</td>
                    <td>{{ $entry->break_minutes }} Min.</td>
                    <td><strong>{{ $entry->formatted_hours }}</strong></td>
                    <td>{{ $entry->activity_note ?: 'Manufaktur & Versand' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #6b7280; padding: 14px;">Keine Zeiteinträge im gewählten Zeitraum vorhanden.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="font-size: 9px; color: #4b5563; margin-top: 12px;">
        Aufbewahrungspflicht nach § 17 Abs. 1 Mindestlohngesetz (MiLoG): Mindestens 2 Jahre für Prüfungen der Zollverwaltung / Deutschen Rentenversicherung.
    </div>

    <div class="footer">
        {{ shop_setting('company_name', 'Mein Seelenfunke') }} • Arbeitszeitnachweis {{ $contract->contract_number }} • {{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) }}/{{ $year }}
    </div>
</body>
</html>
