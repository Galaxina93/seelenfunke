{{-- resources/views/global/mails/partials/mail_price_list.blade.php --}}
<div class="totals" style="margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px;">
    <table width="100%" style="border-collapse: collapse;">

        @php
            // 1. Hilfsfunktion zum Parsen von deutschen Zahlen-Strings (z.B. "1.250,50") zu Floats
            $parseNum = function($val) {
                if(is_numeric($val)) return (float)$val;
                if(empty($val)) return 0.0;
                // Entferne Tausender-Punkt, ersetze Komma durch Punkt
                return (float)str_replace(['.', ','], ['', '.'], $val);
            };

            // 2. Werte holen
            $totalGross = $parseNum($data['total_gross']);
            $shippingGross = $parseNum($data['shipping_price']);

            // Rabatte (Wir schauen, ob sie im $order Objekt oder im $data Array stecken)
            $volDiscount = 0;
            $couponDiscount = 0;
            $couponCode = null;

            if(isset($order)) {
                $volDiscount = abs((float) ($order->volume_discount / 100)); // DB ist in Cents
                $couponDiscount = abs((float) ($order->discount_amount / 100));
                $couponCode = $order->coupon_code;
            } elseif(isset($data['volume_discount']) || isset($data['discount_amount'])) {
                // Fallback, falls $data die Werte direkt hat (als String)
                $volDiscount = abs($parseNum($data['volume_discount'] ?? 0));
                $couponDiscount = abs($parseNum($data['discount_amount'] ?? 0));
                $couponCode = $data['coupon_code'] ?? '';
            }

            // 3. Express Berechnung (Dynamisch aus berechneten FormatsECommerceData bezogen)
            $expressGross = $parseNum($data['express_price'] ?? 0);

            // 4. KORREKTE RÜCKWÄRTSRECHNUNG:
            // Bei regulären Belegen: Warenwert = Endsumme + Rabatte - Versand - Express
            // Bei Storno/Gutschrift ($totalGross < 0): Endsumme, Versand & Express sind negativ, Rabatte positiv (abs)
            $goodsValue = $totalGross < 0
                ? ($totalGross - $volDiscount - $couponDiscount - $shippingGross - $expressGross)
                : ($totalGross + $volDiscount + $couponDiscount - $shippingGross - $expressGross);

            // 5. Seelenfunken Treue-Bonus (Variante A = 100 % vom bezahlten Produktwert)
            if (isset($order) && $order instanceof \App\Models\Order\OrderOrder) {
                $mailBonusSparks = app(\App\Services\Gamification\GamificationService::class)->calculateOrderSparks($order);
            } else {
                $paidGoodsCents = max(0, (int) round(($goodsValue - $volDiscount - $couponDiscount) * 100));
                $mailBonusSparks = \App\Services\Gamification\GameConfig::calculateSparksForCents($paidGoodsCents);
            }
        @endphp

        {{-- Warenwert Brutto --}}
        <tr>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">Warenwert (Brutto):</td>
            <td width="120" class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">
                {{ number_format($goodsValue, 2, ',', '.') }} €
            </td>
        </tr>

        {{-- Mengenrabatt --}}
        @if($volDiscount > 0)
            <tr>
                <td class="text-right" style="padding-bottom: 5px; color: #16a34a; font-size: 13px; text-align: right;">Mengenrabatt:</td>
                <td class="text-right" style="padding-bottom: 5px; color: #16a34a; font-size: 13px; text-align: right;">
                    {{ $totalGross < 0 ? '+' : '-' }}{{ number_format($volDiscount, 2, ',', '.') }} €
                </td>
            </tr>
        @endif

        {{-- Gutschein --}}
        @if($couponDiscount > 0)
            <tr>
                <td class="text-right" style="padding-bottom: 5px; color: #16a34a; font-size: 13px; text-align: right;">
                    Gutschein {{ $couponCode ? "($couponCode)" : '' }}:
                </td>
                <td class="text-right" style="padding-bottom: 5px; color: #16a34a; font-size: 13px; text-align: right;">
                    {{ $totalGross < 0 ? '+' : '-' }}{{ number_format($couponDiscount, 2, ',', '.') }} €
                </td>
            </tr>
        @endif

        {{-- Express-Zuschlag --}}
        @if(abs($expressGross) > 0.004)
            @php
                $mailExpressPercent = number_format((float) shop_setting('express_surcharge_percent', 20.0), 0, ',', '.');
                $mailExpressMin = number_format(((int) shop_setting('express_surcharge_min', 500)) / 100, 2, ',', '.');
            @endphp
            <tr>
                <td class="text-right" style="padding-bottom: 5px; color: #555; font-size: 13px; text-align: right;" title="Vorteil: Sofortige Priorisierung in der Manufaktur vor regulären Bestellungen. Zusammensetzung: {{ $mailExpressPercent }} % vom Warenwert (mind. {{ $mailExpressMin }} € Basis-Pauschale).">
                    Express-Service <span style="font-family: 'DejaVu Sans', sans-serif; color: #C5A059; font-weight: bold;">&#10003;</span>:
                </td>
                <td class="text-right" style="padding-bottom: 5px; color: #333; font-weight: bold; font-size: 13px; text-align: right;">
                    {{ $expressGross > 0 ? '+' : '' }}{{ number_format($expressGross, 2, ',', '.') }} €
                </td>
            </tr>
        @endif

        {{-- Versandkosten --}}
        <tr>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">{{ $data['shipping_label'] ?? 'Versandkosten' }}:</td>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">
                @if(abs($shippingGross) < 0.005)
                    <span style="color: #16a34a; font-weight: bold;">Kostenlos</span>
                @else
                    {{ number_format($shippingGross, 2, ',', '.') }} €
                @endif
            </td>
        </tr>

        {{-- Gesamtsumme Brutto --}}
        <tr class="totals-final">
            <td class="text-right" style="padding-top: 15px; border-top: 2px solid #eee; font-size: 18px; font-weight: bold; color: #C5A059; text-align: right;">Gesamtsumme:</td>
            <td class="text-right" style="padding-top: 15px; border-top: 2px solid #eee; font-size: 18px; font-weight: bold; color: #C5A059; text-align: right;">
                {{ $data['total_gross'] }} €
            </td>
        </tr>

        {{-- Steuerrechtliche Aufschlüsselung --}}
        @if(!empty($data['is_small_business']))
            <tr>
                <td colspan="2" class="text-right" style="padding-top: 10px; padding-bottom: 10px; color: #6b7280; font-size: 11px; font-style: italic; text-align: right;">
                    Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.
                </td>
            </tr>
        @else
            <tr>
                <td class="text-right" style="padding-top: 15px; color: #9ca3af; font-size: 11px; font-style: italic; text-align: right;">
                    Nettobetrag:
                </td>
                <td class="text-right" style="padding-top: 15px; color: #9ca3af; font-size: 11px; font-style: italic; text-align: right;">
                    {{ $data['total_netto'] }} €
                </td>
            </tr>
            @if(!empty($data['tax_breakdown']))
                @foreach($data['tax_breakdown'] as $rate => $taxAmountStr)
                <tr>
                    <td class="text-right" style="padding-bottom: 5px; color: #9ca3af; font-size: 11px; font-style: italic; text-align: right;">
                        inkl. MwSt. ({{ $rate }}%):
                    </td>
                    <td class="text-right" style="padding-bottom: 5px; color: #9ca3af; font-size: 11px; font-style: italic; text-align: right;">
                        {{ $taxAmountStr }} €
                    </td>
                </tr>
                @endforeach
            @else
                <tr>
                    <td class="text-right" style="padding-bottom: 10px; color: #9ca3af; font-size: 11px; font-style: italic; text-align: right;">
                        {{ $data['tax_note'] ?? 'Enthaltene MwSt.:' }}
                    </td>
                    <td class="text-right" style="padding-bottom: 10px; color: #9ca3af; font-size: 11px; font-style: italic; text-align: right;">
                        {{ $data['total_vat'] }} €
                    </td>
                </tr>
            @endif
        @endif

        @if(($mailBonusSparks ?? 0) > 0)
            @php
                $isCancelledMail = (isset($order) && (in_array($order->status ?? '', ['cancelled', 'refunded'], true) || ($order->payment_status ?? '') === 'refunded'))
                    || (isset($invoice) && (in_array($invoice->type ?? '', ['cancellation', 'credit_note'], true) || ($invoice->status ?? '') === 'cancelled'))
                    || in_array($data['type'] ?? '', ['cancellation', 'credit_note'], true);
            @endphp
            <tr>
                <td class="text-right" style="padding-top: 8px; color: {{ $isCancelledMail ? '#dc2626' : '#92400e' }}; font-size: 12px; font-weight: bold; text-align: right;">
                    {{ !empty($isPdf) ? '' : '✨ ' }}{{ $isCancelledMail ? 'Seelenfunken (Stornierung / Widerruf):' : 'Seelenfunken Treue-Bonus (100%):' }}
                </td>
                <td class="text-right" style="padding-top: 8px; color: {{ $isCancelledMail ? '#dc2626' : '#92400e' }}; font-size: 12px; font-weight: bold; text-align: right;">
                    {{ $isCancelledMail ? '-' : '+' }}{{ number_format($mailBonusSparks, 0, ',', '.') }} Funken
                </td>
            </tr>
        @endif
    </table>

    {{-- ZAHLUNGSBUTTON --}}
    @if(isset($data['payment_url']) && $data['payment_url'])
        <div style="margin-top: 30px; margin-bottom: 10px; text-align: center; border-top: 1px dashed #eee; padding-top: 20px;">
            <p style="margin-bottom: 15px; color: #555; font-size: 14px;">
                Um Ihre Bestellung abzuschließen, können Sie hier sicher online bezahlen:
            </p>

            <a href="{{ $data['payment_url'] }}"
               style="background-color: #C5A059; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                {{ !empty($isPdf) ? '' : '🔒 ' }}Jetzt sicher bezahlen
            </a>

            <p style="font-size: 11px; color: #999; margin-top: 12px;">
                (Kreditkarte, Apple Pay, Google Pay, Klarna, SEPA-Lastschrift)
            </p>
        </div>
    @endif

</div>
