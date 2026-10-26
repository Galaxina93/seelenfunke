<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Arbeitsvertrag {{ $contract->contract_number }}</title>
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
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .parties-table {
            width: 100%;
            margin-bottom: 18px;
        }
        .party-box {
            width: 48%;
            vertical-align: top;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px 12px;
        }
        .party-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #6b7280;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #111827;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 3px;
            margin-top: 12px;
            margin-bottom: 6px;
        }
        .warning-box {
            background: #fffbeb;
            border: 1px solid #f59e0b;
            border-left: 4px solid #d97706;
            padding: 9px 12px;
            font-size: 9.5px;
            color: #92400e;
            margin-bottom: 14px;
            border-radius: 4px;
        }
        .legal-box {
            background: #f8fafc;
            border-left: 3px solid #c5a059;
            padding: 9px 12px;
            font-size: 9px;
            color: #4b5563;
            margin-top: 14px;
            margin-bottom: 14px;
        }
        .sig-table {
            width: 100%;
            margin-top: 18px;
        }
        .sig-box {
            width: 48%;
            vertical-align: bottom;
            border-top: 1px solid #9ca3af;
            padding-top: 6px;
            font-size: 9px;
            color: #4b5563;
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
                <div class="brand-subtitle">Manufaktur & Personalwesen</div>
                <div class="brand-title">{{ shop_setting('company_name', 'Mein Seelenfunke') }}</div>
            </td>
            <td style="text-align: right;">
                <span class="doc-badge">Arbeitsvertrag & Nachweis (§ 2 NachwG)</span>
                <div style="margin-top: 6px; font-size: 9.5px; color: #4b5563;">
                    Vertrags-Nr.: <strong>{{ $contract->contract_number }}</strong><br>
                    Stand: {{ ($contract->signed_at ?: now())->format('d.m.Y') }}
                </div>
            </td>
        </tr>
    </table>

    @if($contract->is_fixed_term)
        <div class="warning-box">
            <strong>Wichtiger gesetzlicher Formhinweis zur Befristung (§ 14 Abs. 4 TzBfG):</strong><br>
            Dieser Arbeitsvertrag sieht eine Befristung bis zum {{ $contract->end_date ? $contract->end_date->format('d.m.Y') : 'vereinbarten Enddatum' }} vor.
            Für die rechtliche Wirksamkeit der Befristung bedarf diese zwingend der <strong>Schriftform (§ 126 BGB – eigenhändige Originalunterschrift beider Parteien auf Papier)</strong> oder einer qualifizierten elektronischen Signatur (QES) <em>vor</em> Arbeitsaufnahme.
        </div>
    @endif

    <table class="parties-table">
        <tr>
            <td class="party-box">
                <div class="party-label">Arbeitgeber</div>
                <strong>{{ shop_setting('company_name', 'Mein Seelenfunke') }}</strong><br>
                Inh. {{ shop_setting('owner_name', 'Alina Steinhauer') }}<br>
                {{ shop_setting('company_street', 'Carl-Goerdeler-Ring') }} {{ shop_setting('company_street_number', '26') }}<br>
                {{ shop_setting('company_zip', '38518') }} {{ shop_setting('company_city', 'Gifhorn') }}
            </td>
            <td style="width: 4%;"></td>
            <td class="party-box">
                <div class="party-label">Arbeitnehmer/in</div>
                <strong>{{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: $contract->invitation_label }}</strong><br>
                {{ $profile->street ?? '' }} {{ $profile->house_number ?? '' }}<br>
                {{ $profile->postal ?? '' }} {{ $profile->city ?? '' }}<br>
                Geb.: {{ $contract->birth_date ? $contract->birth_date->format('d.m.Y') : '—' }} | E-Mail: {{ $employee->email ?? '—' }}
            </td>
        </tr>
    </table>

    <div class="section-title">§ 1 Beginn, Art der Tätigkeit & Arbeitsort</div>
    <div>
        (1) Das Arbeitsverhältnis beginnt am <strong>{{ $contract->start_date ? $contract->start_date->format('d.m.Y') : now()->format('d.m.Y') }}</strong>
        als <strong>{{ $contract->employment_type_label }}</strong>.<br>
        (2) Der/die Arbeitnehmer/in wird eingestellt als <strong>{{ $contract->job_title }}</strong> (Bereich: {{ $contract->department }}).<br>
        (3) Arbeitsort: {{ $contract->work_location }}.<br>
        (4) Probezeit: Die ersten <strong>{{ $contract->probation_months }} Monate</strong> gelten als Probezeit (Kündigungsfrist 2 Wochen gemäß § 622 Abs. 3 BGB).
    </div>

    <div class="section-title">§ 2 Arbeitszeit & Ruhepausen (§ 4 ArbZG / § 17 MiLoG)</div>
    <div>
        (1) Die regelmäßige wöchentliche Arbeitszeit beträgt <strong>{{ number_format((float) $contract->weekly_hours, 1, ',', '.') }} Stunden</strong> ({{ $contract->working_hours_schedule ?: 'flexibel nach betrieblicher Einteilung' }}).<br>
        (2) Beginn, Ende und Dauer der täglichen Arbeitszeit sowie die gesetzlichen Ruhepausen (ab 6 Std. mind. 30 Min., ab 9 Std. mind. 45 Min. gemäß § 4 ArbZG) sind im digitalen Mitarbeiter-Portal wahrheitsgemäß zu erfassen.
    </div>

    <div class="section-title">§ 3 Vergütung & Fälligkeit</div>
    <div>
        @if($contract->compensation_type === 'fixed_salary')
            (1) Der/die Arbeitnehmer/in erhält ein monatliches Bruttogehalt in Höhe von <strong>{{ number_format($contract->monthly_gross_cents / 100, 2, ',', '.') }} €</strong>.
        @else
            (1) Der/die Arbeitnehmer/in erhält einen Brutto-Stundenlohn in Höhe von <strong>{{ number_format($contract->hourly_rate_cents / 100, 2, ',', '.') }} € / Stunde</strong>
            (kalkulatorischer Monatsrichtwert: ca. {{ number_format($contract->monthly_gross_cents / 100, 2, ',', '.') }} € Brutto).
        @endif
        <br>
        (2) Die Auszahlung erfolgt jeweils zum Ende des Kalendermonats unbar auf das hinterlegte Konto ({{ $contract->masked_iban ?: 'SEPA-Konto' }}). Die Entgeltabrechnung wird gemäß § 108 GewO elektronisch im Mitarbeiter-Dashboard bereitgestellt.
    </div>

    <div class="section-title">§ 4 Erholungsurlaub & Entgeltfortzahlung</div>
    <div>
        Der jährliche Urlaubsanspruch beträgt <strong>{{ $contract->vacation_days_per_year }} Arbeitstage</strong> pro Kalenderjahr. Im Krankheitsfall ist der Arbeitgeber unverzüglich zu informieren (eAU-Abruf nach § 5 EFZG).
    </div>

    <div class="section-title">§ 5 Beendigung des Arbeitsverhältnisses & Schriftform (§ 623 BGB)</div>
    <div>
        Nach Ablauf der Probezeit gilt die Kündigungsfrist: <strong>{{ $contract->notice_period_text }}</strong>.
        <em>Gesetzlicher Hinweis (§ 623 BGB):</em> Jede Kündigung oder ein Aufhebungsvertrag bedarf zur Wirksamkeit zwingend der schriftlichen Papierform; die elektronische Form ist gesetzlich ausgeschlossen.
    </div>

    <div class="legal-box">
        <strong>Elektronischer Nachweis gemäß § 2 Abs. 1 Satz 2 NachwG (i.d.F. des BEG IV):</strong><br>
        Dieses Vertragsdokument wurde digital im Mitarbeiter-Onboarding von <strong>{{ $contract->signature_name ?: 'Arbeitnehmer/in' }}</strong>
        am {{ ($contract->signed_at ?: now())->format('d.m.Y \u\m H:i') }} Uhr bestätigt (Signatur-Prüfsumme: {{ substr((string) $contract->signed_ip_hash, 0, 16) ?: 'VERIFIED' }}) und steht im Mitarbeiter-Portal jederzeit zum Speichern und Ausdrucken bereit.
    </div>

    <table class="sig-table">
        <tr>
            <td class="sig-box">
                <div style="font-family: DejaVu Serif, serif; font-style: italic; font-size: 13px; color: #111827; margin-bottom: 4px;">
                    {{ shop_setting('owner_name', 'Alina Steinhauer') }}
                </div>
                Gifhorn, den {{ ($contract->signed_at ?: now())->format('d.m.Y') }} — Arbeitgeber ({{ shop_setting('company_name', 'Mein Seelenfunke') }})
            </td>
            <td style="width: 4%;"></td>
            <td class="sig-box">
                <div style="font-family: DejaVu Serif, serif; font-style: italic; font-size: 13px; color: #111827; margin-bottom: 4px;">
                    {{ $contract->signature_name ?: (trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: 'Unterschrift Arbeitnehmer/in') }}
                </div>
                Digital unterzeichnet am {{ ($contract->signed_at ?: now())->format('d.m.Y H:i') }} — Arbeitnehmer/in
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ shop_setting('company_name', 'Mein Seelenfunke') }} • Arbeitsvertrag {{ $contract->contract_number }} • Erstellt nach § 2 NachwG (BEG IV)
    </div>
</body>
</html>
