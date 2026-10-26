<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Lohnabrechnung {{ $payslip->payslip_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #1f2937;
            line-height: 1.48;
            margin: 0;
            padding: 26px 34px;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #c5a059;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .brand-title {
            font-size: 19px;
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
        .doc-badge {
            display: inline-block;
            background: #ecfeff;
            color: #0e7490;
            border: 1px solid #06b6d4;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .addresses-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .address-box {
            width: 48%;
            vertical-align: top;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px 12px;
        }
        .address-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #6b7280;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .items-table th {
            background: #111827;
            color: #ffffff;
            text-align: left;
            padding: 8px 10px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10px;
        }
        .totals-table {
            width: 56%;
            margin-left: auto;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .totals-table td {
            padding: 6px 10px;
            font-size: 10.5px;
        }
        .totals-table .grand-total td {
            border-top: 2px solid #111827;
            font-weight: bold;
            font-size: 12.5px;
            color: #111827;
            padding-top: 8px;
        }
        .legal-box {
            background: #f8fafc;
            border-left: 3px solid #c5a059;
            padding: 10px 12px;
            font-size: 9px;
            color: #4b5563;
            margin-top: 14px;
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
                <div class="brand-subtitle">Lohn- & Gehaltsabrechnung (§ 108 GewO)</div>
                <div class="brand-title">{{ shop_setting('company_name', 'Mein Seelenfunke') }}</div>
            </td>
            <td style="text-align: right;">
                <span class="doc-badge">{{ $payslip->period_label }}</span>
                <div style="margin-top: 6px; font-size: 9.5px; color: #4b5563;">
                    Beleg-Nr.: <strong>{{ $payslip->payslip_number }}</strong><br>
                    Datum: {{ ($payslip->paid_at ?: ($payslip->approved_at ?: now()))->format('d.m.Y') }}
                </div>
            </td>
        </tr>
    </table>

    <table class="addresses-table">
        <tr>
            <td class="address-box">
                <div class="address-label">Arbeitgeber</div>
                <strong>{{ shop_setting('company_name', 'Mein Seelenfunke') }}</strong><br>
                Inh. {{ shop_setting('owner_name', 'Alina Steinhauer') }}<br>
                {{ shop_setting('company_street', 'Carl-Goerdeler-Ring') }} {{ shop_setting('company_street_number', '26') }}<br>
                {{ shop_setting('company_zip', '38518') }} {{ shop_setting('company_city', 'Gifhorn') }}
            </td>
            <td style="width: 4%;"></td>
            <td class="address-box">
                <div class="address-label">Arbeitnehmer/in</div>
                <strong>{{ $payslip->recipient_snapshot['name'] ?? (trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''))) }}</strong><br>
                {{ $payslip->recipient_snapshot['street'] ?? (($profile->street ?? '') . ' ' . ($profile->house_number ?? '')) }}<br>
                {{ $payslip->recipient_snapshot['postal'] ?? ($profile->postal ?? '') }} {{ $payslip->recipient_snapshot['city'] ?? ($profile->city ?? '') }}<br>
                StKl.: {{ $payslip->tax_class_snapshot ?: ($contract->tax_class ?? 'I') }} | KK: {{ $contract->health_insurance_name ?: 'Minijob-Zentrale / GKV' }}
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Lohnart / Bezeichnung</th>
                <th>Zeitraum</th>
                <th>Stunden</th>
                <th>Stundensatz</th>
                <th style="text-align: right;">Brutto-Betrag</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $contract->job_title ?? 'Mitarbeitervergütung' }}</strong><br>
                    <span style="font-size: 8.5px; color: #6b7280;">{{ $contract->employment_type_label ?? 'Beschäftigung' }}</span>
                </td>
                <td>{{ $payslip->period_label }}</td>
                <td>{{ number_format(($payslip->worked_minutes ?? 0) / 60, 2, ',', '.') }} Std.</td>
                <td>{{ number_format(($payslip->hourly_rate_cents_snapshot ?? 0) / 100, 2, ',', '.') }} €</td>
                <td style="text-align: right;"><strong>{{ number_format(($payslip->gross_amount_cents ?? 0) / 100, 2, ',', '.') }} €</strong></td>
            </tr>
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>Steuer- / Gesamt-Brutto:</td>
            <td style="text-align: right;">{{ number_format(($payslip->gross_amount_cents ?? 0) / 100, 2, ',', '.') }} €</td>
        </tr>
        <tr>
            <td>Gesetzliche Abzüge (AN-Anteil SV / LSt):</td>
            <td style="text-align: right;">-{{ number_format(max(0, ($payslip->gross_amount_cents ?? 0) - ($payslip->net_payout_cents ?? 0)) / 100, 2, ',', '.') }} €</td>
        </tr>
        <tr class="grand-total">
            <td>Auszahlungsbetrag (Netto SEPA):</td>
            <td style="text-align: right;">{{ number_format(($payslip->net_payout_cents ?? 0) / 100, 2, ',', '.') }} €</td>
        </tr>
        <tr>
            <td style="font-size: 9px; color: #6b7280; padding-top: 8px;">Nachrichtlich: AG-Nebenkosten (Pauschale/SV):</td>
            <td style="text-align: right; font-size: 9px; color: #6b7280; padding-top: 8px;">+{{ number_format(($payslip->employer_charges_cents ?? 0) / 100, 2, ',', '.') }} €</td>
        </tr>
        <tr>
            <td style="font-size: 9px; color: #374151; font-weight: bold;">Arbeitgeber-Gesamtaufwand (SKR03 4120/4190):</td>
            <td style="text-align: right; font-size: 9px; color: #374151; font-weight: bold;">{{ number_format(($payslip->total_employer_cost_cents ?? 0) / 100, 2, ',', '.') }} €</td>
        </tr>
    </table>

    <div class="legal-box">
        <strong>Auszahlung & Buchhaltungs-Vermerk:</strong><br>
        Überweisung auf Konto: {{ $payslip->recipient_snapshot['masked_iban'] ?? ($contract->masked_iban ?? 'SEPA-Konto') }} ({{ $payslip->recipient_snapshot['account_holder'] ?? ($contract->account_holder ?? '') }}).
        Status: <strong>{{ $payslip->status === 'paid' ? 'Überwiesen am ' . ($payslip->paid_at ? $payslip->paid_at->format('d.m.Y') : '') : 'Zur Überweisung freigegeben' }}</strong>.
        @if(!empty($payslip->notes))
            <br>Hinweis: {{ $payslip->notes }}
        @endif
    </div>

    <div class="footer">
        {{ shop_setting('company_name', 'Mein Seelenfunke') }} • Lohnabrechnung {{ $payslip->payslip_number }} • Elektronisch bereitgestellt gemäß § 108 GewO
    </div>
</body>
</html>
