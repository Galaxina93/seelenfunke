<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Personalfragebogen {{ $contract->contract_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
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
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .data-table th {
            background: #111827;
            color: #ffffff;
            text-align: left;
            padding: 6px 10px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .data-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10px;
        }
        .data-table td.label {
            width: 38%;
            font-weight: bold;
            color: #4b5563;
            background: #f9fafb;
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
                <div class="brand-subtitle">Lohnbuchhaltung & Steuerkanzlei-Stammblatt</div>
                <div class="brand-title">Personalfragebogen (ELStAM & SV-Anmeldung)</div>
            </td>
            <td style="text-align: right; font-size: 9.5px; color: #4b5563;">
                Personalakte: <strong>{{ $contract->contract_number }}</strong><br>
                Erfasst am: {{ ($contract->signed_at ?: now())->format('d.m.Y') }}
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr><th colspan="2">1. Persönliche Angaben (Arbeitnehmer/in)</th></tr>
        </thead>
        <tbody>
            <tr>
                <td class="label">Vor- und Nachname</td>
                <td><strong>{{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: $contract->invitation_label }}</strong></td>
            </tr>
            <tr>
                <td class="label">Anschrift</td>
                <td>{{ $profile->street ?? '' }} {{ $profile->house_number ?? '' }}, {{ $profile->postal ?? '' }} {{ $profile->city ?? '' }} ({{ $profile->country ?? 'DE' }})</td>
            </tr>
            <tr>
                <td class="label">Geburtsdatum & Geburtsort</td>
                <td>{{ $contract->birth_date ? $contract->birth_date->format('d.m.Y') : '—' }} / {{ $contract->birth_place ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">Staatsangehörigkeit</td>
                <td>{{ $contract->nationality ?: 'Deutsch' }}</td>
            </tr>
            <tr>
                <td class="label">E-Mail & Telefon</td>
                <td>{{ $employee->email ?? '—' }} | {{ $profile->phone_number ?? '—' }}</td>
            </tr>
        </tbody>
    </table>

    <table class="data-table">
        <thead>
            <tr><th colspan="2">2. Beschäftigungsdaten & Vergütung</th></tr>
        </thead>
        <tbody>
            <tr>
                <td class="label">Eintrittsdatum & Tätigkeitsbezeichnung</td>
                <td>{{ $contract->start_date ? $contract->start_date->format('d.m.Y') : '—' }} — {{ $contract->job_title }} ({{ $contract->department }})</td>
            </tr>
            <tr>
                <td class="label">Beschäftigungsart</td>
                <td><strong>{{ $contract->employment_type_label }}</strong> ({{ $contract->is_fixed_term ? 'Befristet bis ' . ($contract->end_date ? $contract->end_date->format('d.m.Y') : '') : 'Unbefristet' }})</td>
            </tr>
            <tr>
                <td class="label">Wochenarbeitszeit & Urlaub</td>
                <td>{{ number_format((float) $contract->weekly_hours, 1, ',', '.') }} Std. / Woche | {{ $contract->vacation_days_per_year }} Urlaubstage / Jahr</td>
            </tr>
            <tr>
                <td class="label">Vergütung (Brutto)</td>
                <td>
                    @if($contract->compensation_type === 'fixed_salary')
                        Festgehalt: {{ number_format($contract->monthly_gross_cents / 100, 2, ',', '.') }} € / Monat
                    @else
                        Stundenlohn: {{ number_format($contract->hourly_rate_cents / 100, 2, ',', '.') }} € / Std. (Richtwert: {{ number_format($contract->monthly_gross_cents / 100, 2, ',', '.') }} € / Monat)
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <table class="data-table">
        <thead>
            <tr><th colspan="2">3. Steuerliche Angaben (ELStAM) & Sozialversicherung (DEÜV)</th></tr>
        </thead>
        <tbody>
            <tr>
                <td class="label">Steuer-Identifikationsnummer (11-stellig)</td>
                <td><strong>{{ $contract->tax_id ?: '—' }}</strong></td>
            </tr>
            <tr>
                <td class="label">Steuerklasse / Kinderfreibeträge / Kirchensteuer</td>
                <td>Steuerklasse {{ $contract->tax_class ?: 'I' }} | Kinderfreibeträge: {{ number_format((float) $contract->children_allowances, 1, ',', '.') }} | Kirchensteuer: {{ $contract->church_tax ? 'Ja' : 'Nein' }}</td>
            </tr>
            <tr>
                <td class="label">Sozialversicherungsnummer (RV-Nr.)</td>
                <td><strong>{{ $contract->social_security_number ?: '—' }}</strong></td>
            </tr>
            <tr>
                <td class="label">Krankenkasse & Versicherungsart</td>
                <td>{{ $contract->health_insurance_name ?: '—' }} ({{ $contract->health_insurance_type === 'private' ? 'Privat' : ($contract->health_insurance_type === 'family' ? 'Familienversichert' : 'Gesetzlich') }})</td>
            </tr>
            <tr>
                <td class="label">Minijob: Befreiung von der RV-Pflicht (§ 6 Abs. 1b SGB VI)</td>
                <td>{{ $contract->rv_exemption_requested ? 'Ja (Befreiungsantrag gestellt)' : 'Nein (Standard RV-Pflicht mit Eigenanteil)' }}</td>
            </tr>
            <tr>
                <td class="label">Weitere Beschäftigungen vorhanden?</td>
                <td>{{ $contract->has_other_employment ? 'Ja (Prüfung Zusammenrechnung erforderlich)' : 'Nein (Keine weiteren Beschäftigungen)' }}</td>
            </tr>
        </tbody>
    </table>

    <table class="data-table">
        <thead>
            <tr><th colspan="2">4. Bankverbindung (SEPA-Gehaltskonto)</th></tr>
        </thead>
        <tbody>
            <tr>
                <td class="label">Kontoinhaber & Kreditinstitut</td>
                <td>{{ $contract->account_holder ?: '—' }} ({{ $contract->bank_name ?: 'SEPA-Bank' }})</td>
            </tr>
            <tr>
                <td class="label">IBAN & BIC</td>
                <td><strong>{{ $contract->iban ?: '—' }}</strong> {{ $contract->bic ? ('(BIC: ' . $contract->bic . ')') : '' }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 18px; font-size: 9.5px; color: #4b5563;">
        Ich versichere die Vollständigkeit und Richtigkeit der obigen Angaben für die steuer- und sozialversicherungsrechtliche Anmeldung.<br><br>
        <strong>Digital bestätigt durch:</strong> {{ $contract->signature_name ?: 'Arbeitnehmer/in' }} am {{ ($contract->signed_at ?: now())->format('d.m.Y H:i') }} Uhr.
    </div>

    <div class="footer">
        {{ shop_setting('company_name', 'Mein Seelenfunke') }} • Personalfragebogen {{ $contract->contract_number }} • Vertrauliches Personaldokument für Lohnbüro & Steuerberater
    </div>
</body>
</html>
