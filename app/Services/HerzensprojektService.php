<?php

namespace App\Services;

use App\Models\Marketing\MarketingHerzensprojektAction;
use App\Models\Marketing\MarketingHerzensprojektTransaction;
use App\Models\Order\OrderOrder;
use Illuminate\Support\Facades\Log;

class HerzensprojektService
{
    /**
     * Löst den konfigurierten Spendenbeitrag (Standard: 50 Cents = 0,50 €) in Cents auf.
     * Unterstützt sowohl ganzzahlige Cent-Werte (z. B. 50) als auch dezimale Euro-Strings (z. B. "0.50" oder "0,50").
     */
    public static function resolveCentsPerOrder(): int
    {
        $raw = shop_setting('herzensprojekt_cents_per_order', 50);
        if (is_string($raw) && (str_contains($raw, '.') || str_contains($raw, ','))) {
            return (int) round(((float) str_replace(',', '.', $raw)) * 100);
        }

        return (int) $raw;
    }

    /**
     * Gibt den formatierten Euro-Betrag zurück (z. B. "0,50").
     */
    public static function resolveFormattedEuro(): string
    {
        return number_format(self::resolveCentsPerOrder() / 100, 2, ',', '.');
    }

    /**
     * Löst den Berechnungsmodus auf: 'per_item' (pro Artikel, Standard) oder 'per_order' (pauschal pro Bestellung).
     */
    public static function resolveMode(): string
    {
        $mode = (string) shop_setting('herzensprojekt_mode', 'per_item');
        return in_array($mode, ['per_item', 'per_order'], true) ? $mode : 'per_item';
    }

    public static function isPerItem(): bool
    {
        return self::resolveMode() === 'per_item';
    }

    /**
     * Gibt die passende Einheit als Text zurück (z. B. "pro Artikel" oder "pro Bestellung").
     */
    public static function resolveUnitLabel(bool $short = true): string
    {
        if (self::isPerItem()) {
            return $short ? 'pro Artikel' : 'pro verkauftem Artikel';
        }

        return $short ? 'pro Bestellung' : 'pro bezahlter Bestellung';
    }

    /**
     * Berechnet den Spendenbeitrag in Cents für eine konkrete Bestellung anhand des aktiven Modus.
     */
    public static function calculateOrderDonationCents(OrderOrder $order): int
    {
        $baseCents = self::resolveCentsPerOrder();
        if ($baseCents <= 0) {
            return 0;
        }

        if (self::isPerItem()) {
            $itemCount = max(1, (int) $order->items()->sum('quantity'));
            return $itemCount * $baseCents;
        }

        return $baseCents;
    }

    /**
     * Records a contribution transaction for a paid order (idempotent).
     */
    public static function recordDonationForOrder(OrderOrder $order, ?int $amountCents = null): ?MarketingHerzensprojektTransaction
    {
        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($order, $amountCents) {
                // Idempotency check with row lock: Don't book twice for the same order
                $existing = MarketingHerzensprojektTransaction::where('order_id', $order->id)
                    ->where('type', 'order_contribution')
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $itemCount = max(1, (int) $order->items()->sum('quantity'));
                $amount = $amountCents ?? self::calculateOrderDonationCents($order);

                if ($amount <= 0) {
                    return null;
                }

                $orderRef = $order->order_number ?? '#' . substr((string)$order->id, 0, 8);
                $descSuffix = self::isPerItem()
                    ? ' (' . $itemCount . ' Artikel à ' . self::resolveFormattedEuro() . ' €)'
                    : '';

                return MarketingHerzensprojektTransaction::create([
                    'order_id' => $order->id,
                    'type' => 'order_contribution',
                    'amount_cents' => $amount,
                    'description' => 'Herzensbeitrag aus Bestellung ' . $orderRef . $descSuffix,
                ]);
            });
        } catch (\Exception $e) {
            Log::error("HerzensprojektService: Fehler beim Erfassen des Spendenbeitrags: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Returns key figures for the dashboard.
     */
    public static function getDashboardStats(): array
    {
        $currentPotCents = (int) MarketingHerzensprojektTransaction::sum('amount_cents');
        $totalCollectedCents = (int) MarketingHerzensprojektTransaction::where('amount_cents', '>', 0)->sum('amount_cents');
        $totalDisbursedCents = abs((int) MarketingHerzensprojektTransaction::where('amount_cents', '<', 0)->sum('amount_cents'));
        $ordersCount = MarketingHerzensprojektTransaction::where('type', 'order_contribution')->count();

        $actionsCount = MarketingHerzensprojektAction::count();
        $completedActionsCount = MarketingHerzensprojektAction::where('status', 'completed')->count();

        return [
            'current_pot_cents' => $currentPotCents,
            'current_pot_formatted' => number_format($currentPotCents / 100, 2, ',', '.') . ' €',
            'total_collected_cents' => $totalCollectedCents,
            'total_collected_formatted' => number_format($totalCollectedCents / 100, 2, ',', '.') . ' €',
            'total_disbursed_cents' => $totalDisbursedCents,
            'total_disbursed_formatted' => number_format($totalDisbursedCents / 100, 2, ',', '.') . ' €',
            'orders_count' => $ordersCount,
            'actions_count' => $actionsCount,
            'completed_actions_count' => $completedActionsCount,
            'cents_per_order' => self::resolveCentsPerOrder(),
            'mode' => self::resolveMode(),
            'unit_label' => self::resolveUnitLabel(true),
        ];
    }

    /**
     * Safely reverses a donation contribution when an order is cancelled or refunded.
     */
    public static function reverseDonationForOrder(OrderOrder $order, string $reason = 'Stornierung / Erstattung'): ?MarketingHerzensprojektTransaction
    {
        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($order, $reason) {
                $contribution = MarketingHerzensprojektTransaction::where('order_id', $order->id)
                    ->where('type', 'order_contribution')
                    ->lockForUpdate()
                    ->first();

                if (!$contribution) {
                    return null;
                }

                // Check if already reversed (any disbursement with negative amount for this order)
                $alreadyReversed = MarketingHerzensprojektTransaction::where('order_id', $order->id)
                    ->where('amount_cents', '<', 0)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyReversed) {
                    return null;
                }

                return MarketingHerzensprojektTransaction::create([
                    'order_id' => $order->id,
                    'type' => 'disbursement',
                    'amount_cents' => -abs($contribution->amount_cents),
                    'description' => "Rückbuchung ($reason) für Bestellung " . ($order->order_number ?? '#' . substr((string)$order->id, 0, 8)),
                ]);
            });
        } catch (\Exception $e) {
            Log::error("HerzensprojektService: Fehler bei Rückbuchung: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Returns the currently active campaign or latest active action, if any.
     */
    public static function getActiveAction(): ?MarketingHerzensprojektAction
    {
        return MarketingHerzensprojektAction::whereIn('status', ['in_progress', 'planned'])
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
