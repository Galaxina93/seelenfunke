<?php

namespace App\Services;

use App\Models\Marketing\MarketingHerzensprojektAction;
use App\Models\Marketing\MarketingHerzensprojektTransaction;
use App\Models\Order\OrderOrder;
use Illuminate\Support\Facades\Log;

class HerzensprojektService
{
    /**
     * Records a contribution transaction for a paid order (idempotent).
     */
    public static function recordDonationForOrder(OrderOrder $order, ?int $amountCents = null): ?MarketingHerzensprojektTransaction
    {
        try {
            // Idempotency check: Don't book twice for the same order
            $existing = MarketingHerzensprojektTransaction::where('order_id', $order->id)
                ->where('type', 'order_contribution')
                ->first();

            if ($existing) {
                return $existing;
            }

            // Default: 2,00 € (200 Cents) pro Bestellung, anpassbar über shop_setting
            $amount = $amountCents ?? (int) shop_setting('herzensprojekt_cents_per_order', 200);

            if ($amount <= 0) {
                return null;
            }

            return MarketingHerzensprojektTransaction::create([
                'order_id' => $order->id,
                'type' => 'order_contribution',
                'amount_cents' => $amount,
                'description' => 'Herzensbeitrag aus Bestellung ' . ($order->order_number ?? '#' . substr($order->id, 0, 8)),
            ]);
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
            'cents_per_order' => (int) shop_setting('herzensprojekt_cents_per_order', 200),
        ];
    }

    /**
     * Safely reverses a donation contribution when an order is cancelled or refunded.
     */
    public static function reverseDonationForOrder(OrderOrder $order, string $reason = 'Stornierung / Erstattung'): ?MarketingHerzensprojektTransaction
    {
        try {
            $contribution = MarketingHerzensprojektTransaction::where('order_id', $order->id)
                ->where('type', 'order_contribution')
                ->first();

            if (!$contribution) {
                return null;
            }

            // Check if already reversed
            $alreadyReversed = MarketingHerzensprojektTransaction::where('order_id', $order->id)
                ->where('amount_cents', '<', 0)
                ->where('description', 'like', '%Stornierung%')
                ->exists();

            if ($alreadyReversed) {
                return null;
            }

            return MarketingHerzensprojektTransaction::create([
                'order_id' => $order->id,
                'type' => 'disbursement',
                'amount_cents' => -$contribution->amount_cents,
                'description' => "Rückbuchung ($reason) für Bestellung " . ($order->order_number ?? '#' . substr($order->id, 0, 8)),
            ]);
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
        return MarketingHerzensprojektAction::where('status', 'in_progress')
            ->orWhere('status', 'planned')
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
