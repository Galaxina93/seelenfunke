<?php

namespace App\Services\Tax;

use App\Models\Order\OrderOrder;

class DigitalEuVatThresholdService
{
    /**
     * Gesetzliche EU-OSS-Umsatzschwelle gemäß § 3a Abs. 5 UStG: 10.000,00 EUR Netto pro Kalenderjahr.
     */
    public const OSS_THRESHOLD_NET_CENTS = 1000000;

    /**
     * Interne Frühwarnschwelle für das Finanz-Cockpit: 8.000,00 EUR Netto (80 %).
     */
    public const OSS_WARNING_NET_CENTS = 800000;

    /**
     * Übrige EU-Mitgliedstaaten außerhalb Deutschlands (ISO-3166-1 alpha-2).
     */
    public const EU_OTHER_MEMBER_STATES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'ES', 'FI',
        'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV',
        'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    ];

    /**
     * Berechnet den aktuellen Stand der 10.000-EUR-Netto-Schwelle (§ 3a Abs. 5 UStG)
     * für grenzüberschreitende digitale Leistungen in der EU.
     */
    public function evaluateAnnualThreshold(?int $year = null): array
    {
        $targetYear = $year ?? (int) now()->year;

        $orders = OrderOrder::query()
            ->with('items.product')
            ->whereYear('created_at', $targetYear)
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get();

        $netCrossBorderCents = 0;
        $countryBreakdown = [];

        foreach ($orders as $order) {
            $billing = is_array($order->billing_address) ? $order->billing_address : [];
            $shipping = is_array($order->shipping_address) ? $order->shipping_address : [];
            $country = strtoupper(trim((string) ($billing['country'] ?? $shipping['country'] ?? 'DE')));

            if (!in_array($country, self::EU_OTHER_MEMBER_STATES, true)) {
                continue;
            }

            $isSmallBusinessOrder = (bool) ($order->is_small_business ?? false);

            foreach ($order->items as $item) {
                $conf = is_array($item->configuration) ? $item->configuration : [];
                if (!empty($conf['is_gift_voucher'])) {
                    continue;
                }

                $product = $item->product;
                if (!$product || $product->type !== 'digital' || $product->slug === 'geschenkgutschein') {
                    continue;
                }

                $lineGrossCents = max(0, (int) $item->total_price);
                $rate = $isSmallBusinessOrder ? 0.0 : max(0.0, (float) ($item->tax_rate ?? 19.0));
                $lineNetCents = $rate > 0
                    ? (int) round($lineGrossCents / (1 + ($rate / 100)))
                    : $lineGrossCents;

                $netCrossBorderCents += $lineNetCents;
                $countryBreakdown[$country] = ($countryBreakdown[$country] ?? 0) + $lineNetCents;
            }
        }

        ksort($countryBreakdown);

        $usagePercent = round(($netCrossBorderCents / self::OSS_THRESHOLD_NET_CENTS) * 100, 2);

        return [
            'year' => $targetYear,
            'eu_cross_border_net_cents' => $netCrossBorderCents,
            'threshold_net_cents' => self::OSS_THRESHOLD_NET_CENTS,
            'warning_net_cents' => self::OSS_WARNING_NET_CENTS,
            'remaining_net_cents' => max(0, self::OSS_THRESHOLD_NET_CENTS - $netCrossBorderCents),
            'usage_percent' => $usagePercent,
            'is_warning_reached' => $netCrossBorderCents >= self::OSS_WARNING_NET_CENTS,
            'is_oss_threshold_exceeded' => $netCrossBorderCents >= self::OSS_THRESHOLD_NET_CENTS,
            'country_breakdown' => $countryBreakdown,
        ];
    }
}
