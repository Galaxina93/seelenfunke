<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Provisions-Gutschrift {{ $payout->credit_note_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.5;
            margin: 0;
            padding: 28px 36px;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #c5a059;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .brand-title {
            font-size: 20px;
            font-weight: bold;
            color: #111827;
            letter-spacing: 0.5px;
        }
        .brand-subtitle {
            font-size: 9px;
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
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .addresses-table {
            width: 100%;
            margin-bottom: 24px;
        }
        .address-box {
            width: 48%;
            vertical-align: top;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px 14px;
        }
        .address-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #6b7280;
            font-weight: bold;
            margin-bottom: 6px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            margin-bottom: 20px;
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
            width: 52%;
            margin-left: auto;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .totals-table td {
            padding: 6px 10px;
            font-size: 11px;
        }
        .totals-table .grand-total td {
            border-top: 2px solid #111827;
            font-weight: bold;
            font-size: 13px;
            color: #111827;
            padding-top: 10px;
        }
        .legal-box {
            background: #f8fafc;
            border-left: 3px solid #c5a059;
            padding: 10px 14px;
            font-size: 9.5px;
            color: #4b5563;
            margin-bottom: 18px;
        }
        .footer {
            position: fixed;
            bottom: 18px;
            left: 36px;
            right: 36px;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            font-size: 8.5px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $snap = is_array($payout->recipient_snapshot) ? $payout->recipient_snapshot : [];
        $companyName = shop_setting('company_name', 'Mein Seelenfunke');
        $companyOwner = shop_setting('owner_name', 'alisanoonte');
        $companyStreet = trim(shop_setting('company_street', 'Auf den sieben Stücken') . ' ' . shop_setting('company_street_number', '4'));
        $companyCity = trim(shop_setting('company_zip', '38518') . ' ' . shop_setting('company_city', 'Gifhorn'));
        $companyTax = shop_setting('company_tax_id') ?: shop_setting('company_vat_id', '');
    @endphp

    <table class="header-table">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="brand-title">{{ $companyName }}</div>
                <div class="brand-subtitle">Manufaktur für personalisierte Unikate</div>
                <div style="margin-top: 6px; font-size: 9.5px; color: #6b7280;">
                    Inh. {{ $companyOwner }} &bull; {{ $companyStreet }} &bull; {{ $companyCity }}
                </div>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top;">
                <span class="doc-badge">Gutschrift gem. § 14 Abs. 2 S. 2 UStG</span>
                <div style="margin-top: 8px; font-size: 13px; font-weight: bold;">Nr. {{ $payout->credit_note_number }}</div>
                <div style="font-size: 9.5px; color: #6b7280;">
                    Ausstellungsdatum: {{ ($payout->paid_at ?: ($payout->requested_at ?: now()))->format('d.m.Y') }}
                </div>
            </td>
        </tr>
    </table>

    <table class="addresses-table">
        <tr>
            <td class="address-box">
                <div class="address-label">Leistender / Zahlungsempfänger (Creator)</div>
                <strong>{{ $snap['full_name'] ?? ($influencer->employee->first_name . ' ' . $influencer->employee->last_name) }}</strong>
                @if(!empty($snap['company_name']))
                    <br>{{ $snap['company_name'] }}
                @endif
                <br>Handle: {{ $snap['handle'] ?? $influencer->handle }}
                @if(!empty($snap['street']))
                    <br>{{ $snap['street'] }} {{ $snap['house_number'] ?? '' }}
                    <br>{{ $snap['postal'] ?? '' }} {{ $snap['city'] ?? '' }} ({{ $snap['country'] ?? 'DE' }})
                @endif
                <br><span style="font-size: 9.5px; color: #374151;">Steuernr. / USt-IdNr.: <strong>{{ $snap['tax_number'] ?? ($influencer->tax_number ?: 'Nicht angegeben') }}</strong></span>
            </td>
            <td style="width: 4%;"></td>
            <td class="address-box">
                <div class="address-label">Leistungsempfänger / Aussteller der Gutschrift</div>
                <strong>{{ $companyName }}</strong>
                <br>Inh. {{ $companyOwner }}
                <br>{{ $companyStreet }}
                <br>{{ $companyCity }}
                @if($companyTax)
                    <br><span style="font-size: 9.5px; color: #374151;">Steuernr. / USt-IdNr.: {{ $companyTax }}</span>
                @endif
            </td>
        </tr>
    </table>

    <div style="margin-bottom: 8px;">
        <strong style="font-size: 13px;">Abrechnung über Affiliate- & Influencer-Vermittlungsprovisionen</strong>
        <p style="margin: 4px 0 0 0; font-size: 10px; color: #4b5563;">
            Hiermit rechnen wir im Gutschriftsverfahren gem. § 14 Abs. 2 Satz 2 UStG die nachfolgenden, erfolgreich vermittelten und bezahlten Shop-Bestellungen ab:
        </p>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Bestell-Nr.</th>
                <th>Datum</th>
                <th>Zuordnung</th>
                <th style="text-align: right;">Netto-Warenwert</th>
                <th style="text-align: right;">Satz</th>
                <th style="text-align: right;">Provision (Netto)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($commissions as $comm)
                <tr>
                    <td><strong>{{ $comm->order_number }}</strong></td>
                    <td>{{ $comm->created_at ? $comm->created_at->format('d.m.Y') : '-' }}</td>
                    <td>
                        @if($comm->attribution_source === 'both')
                            Tracking-Link & Code ({{ $comm->voucher_code }})
                        @elseif($comm->attribution_source === 'coupon')
                            Rabattcode ({{ $comm->voucher_code }})
                        @else
                            Tracking-Link (/t/{{ $influencer->slug }})
                        @endif
                    </td>
                    <td style="text-align: right;">{{ number_format($comm->order_net_amount_cents / 100, 2, ',', '.') }} €</td>
                    <td style="text-align: right;">
                        {{ $influencer->commission_type === 'fixed_cents' ? number_format($comm->commission_rate_snapshot / 100, 2, ',', '.') . ' €' : $comm->commission_rate_snapshot . ' %' }}
                    </td>
                    <td style="text-align: right; font-weight: bold;">{{ number_format($comm->commission_amount_cents / 100, 2, ',', '.') }} €</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Pauschale Affiliate-Provisionsabrechnung ({{ $influencer->handle }})</td>
                    <td style="text-align: right; font-weight: bold;">{{ number_format($payout->net_amount_cents / 100, 2, ',', '.') }} €</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>Summe Provisionen (Netto):</td>
            <td style="text-align: right;">{{ number_format($payout->net_amount_cents / 100, 2, ',', '.') }} €</td>
        </tr>
        @if($payout->bonus_amount_cents > 0)
            <tr>
                <td style="color: #047857;">+ 20 % Seelenfunke Sachprämien-Boost:</td>
                <td style="text-align: right; color: #047857;">+ {{ number_format($payout->bonus_amount_cents / 100, 2, ',', '.') }} €</td>
            </tr>
        @endif
        @if($payout->tax_rate > 0)
            <tr>
                <td>zzgl. {{ rtrim(rtrim(number_format((float) $payout->tax_rate, 2, ',', '.'), '0'), ',') }} % Umsatzsteuer:</td>
                <td style="text-align: right;">{{ number_format($payout->tax_amount_cents / 100, 2, ',', '.') }} €</td>
            </tr>
        @else
            <tr>
                <td colspan="2" style="font-size: 9px; color: #6b7280; text-align: right;">
                    Kein Umsatzsteuerausweis gem. § 19 UStG (Kleinunternehmerregelung) bzw. Gutschein-Prämie.
                </td>
            </tr>
        @endif
        <tr class="grand-total">
            <td>Auszahlungsbetrag (Gesamt):</td>
            <td style="text-align: right;">{{ number_format($payout->gross_amount_cents / 100, 2, ',', '.') }} €</td>
        </tr>
    </table>

    <div class="legal-box">
        <strong>Auszahlungsweg & Steuerlicher Hinweis:</strong><br>
        @if($payout->payout_method === 'bank_transfer')
            Überweisung auf IBAN: <strong>{{ $snap['masked_iban'] ?? $influencer->masked_iban }}</strong> (Kontoinhaber: {{ $snap['account_holder'] ?? $influencer->account_holder }})
        @elseif($payout->payout_method === 'paypal')
            PayPal-Auszahlung an: <strong>{{ $snap['paypal_email'] ?? $influencer->paypal_email }}</strong>
        @elseif($payout->payout_method === 'shop_voucher')
            Ausgestellt als Seelenfunke Shop-Wertgutschein (inkl. 20 % Bonus): <strong>{{ $payout->giftVoucher?->code ?? 'Gutschein' }}</strong>
        @endif
        <br>
        Der Zahlungsempfänger hat bestätigt, als selbstständiger Kooperationspartner auf eigene Rechnung zu handeln und die Einnahmen eigenverantwortlich zu versteuern.
    </div>

    <div class="footer">
        {{ $companyName }} &bull; Inh. {{ $companyOwner }} &bull; {{ $companyStreet }}, {{ $companyCity }} &bull; Beleg-Nr. {{ $payout->credit_note_number }}
    </div>
</body>
</html>
