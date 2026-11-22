<?php

namespace App\Services\AI;

use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Ai\AiLearnedPreference;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Product\ProductPackagingItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProcurementSafetyGuard
{
    /**
     * Harter Auto-Kauf-Deckel: Maximal 25,00 € Brutto inklusive Versandkosten & MwSt.
     */
    public const AUTO_EXECUTION_MAX_GROSS_CENTS = 2500;

    /**
     * Anti-Loop-Wochendeckel: Maximal 50,00 € kumulierte Auto-Käufe innerhalb von 7 Tagen.
     */
    public const WEEKLY_AUTO_CAP_GROSS_CENTS = 5000;

    /**
     * Doppelkauf-Sperrfenster in Tagen für denselben Artikel.
     */
    public const DUPLICATE_ORDER_LOCK_DAYS = 14;

    /**
     * Standard-Versandpauschale netto in Cent, falls kein Lieferanten-Freibetrag hinterlegt ist (4,90 € Brutto ~ 412 ct Netto).
     */
    public const DEFAULT_SHIPPING_GROSS_CENTS = 490;

    /**
     * Bewertet einen Nachbestell-Wunsch zu 100 % deterministisch in 0 Tokens (PHP + MySQL):
     * 1) Stamm-Artikel Whitelist-Prüfung (HTTPS reorder_url & gepflegter Gebindepreis)
     * 2) 14-Tage Doppelkauf- & Zulauf-Sperre (AccountingSpecialIssue + Einkaufs-Tickets)
     * 3) Echter Verbrauchs-Abgleich aus versendeten/aktiven Shop-Bestellungen seit letztem Kauf
     * 4) Exakte Brutto-Endsummen-Kalkulation inkl. 19 % MwSt. & Versand gegen das 25,00 € Limit
     * 5) 7-Tage 50,00 € Anti-Loop-Deckel
     * 6) Umgebungs-Schranke: Vollautomatische Echtgeld-Ausführung nur auf LIVE!
     *
     * @return array<string, mixed>
     */
    public static function evaluateReorder(
        string $itemQuery,
        ?int $requestedPacks = null,
        ?string $environmentTier = null
    ): array {
        $cleanQuery = trim($itemQuery);
        $packs = max(1, min(20, (int) ($requestedPacks ?? 1)));
        $envTier = $environmentTier
            ? strtoupper($environmentTier)
            : FunkiraPresenceContext::resolveEnvironmentTier();

        if ($cleanQuery === '' || !Schema::hasTable('product_packaging_items')) {
            return [
                'found' => false,
                'is_whitelisted_stamm_artikel' => false,
                'auto_execute_allowed' => false,
                'requires_alina_confirmation' => true,
                'blocking_reasons' => ['Kein bekannter Stamm-Artikel gefunden. Erstmalige Klärung mit Alina erforderlich.'],
                'environment_tier' => $envTier,
            ];
        }

        // Prüfe zuerst gelernte Präferenzen (z. B. procurement:papierflachbeutel)
        $learned = null;
        if (Schema::hasTable('ai_learned_preferences')) {
            $slugKey = 'procurement:' . strtolower(preg_replace('/[^a-z0-9äöüß]+/iu', '_', $cleanQuery) ?? $cleanQuery);
            $learned = AiLearnedPreference::where('is_active', true)
                ->where(function ($q) use ($slugKey, $cleanQuery) {
                    $q->where('scope_key', $slugKey)
                        ->orWhere('subject_label', 'like', '%' . $cleanQuery . '%');
                })
                ->first();
        }

        $item = null;
        if ($learned && !empty($learned->structured_value['packaging_item_id'])) {
            $item = ProductPackagingItem::with('supplier')->find($learned->structured_value['packaging_item_id']);
        }

        if (!$item) {
            $item = ProductPackagingItem::with('supplier')
                ->where('is_active', true)
                ->where(function ($q) use ($cleanQuery) {
                    $q->where('name', 'like', '%' . $cleanQuery . '%')
                        ->orWhere('sku', 'like', '%' . $cleanQuery . '%')
                        ->orWhere('description', 'like', '%' . $cleanQuery . '%');
                })
                ->orderByDesc('updated_at')
                ->first();
        }

        if (!$item) {
            return [
                'found' => false,
                'query' => $cleanQuery,
                'is_whitelisted_stamm_artikel' => false,
                'auto_execute_allowed' => false,
                'requires_alina_confirmation' => true,
                'blocking_reasons' => [
                    "'{$cleanQuery}' ist noch nicht als verifizierter Stamm-Artikel in Verpackungen/Rohlingen hinterlegt. Bitte einmalig Link, Größe und Menge mit Alina festlegen (wird danach dauerhaft gespeichert).",
                ],
                'environment_tier' => $envTier,
            ];
        }

        if ($requestedPacks === null && $learned && !empty($learned->structured_value['preferred_packs'])) {
            $packs = max(1, min(20, (int) $learned->structured_value['preferred_packs']));
        }

        $reorderUrl = trim((string) ($item->reorder_url ?? ''));
        $hasValidHttpsUrl = str_starts_with(strtolower($reorderUrl), 'https://');
        $netPackCents = max(0, (int) $item->pack_price_cents);
        $isWhitelisted = (bool) $item->is_active && $hasValidHttpsUrl && $netPackCents > 0;

        // 2. Letzter Einkauf & 14-Tage Doppelkauf-Sperre
        $lastPurchaseDate = null;
        $lastPurchaseTitle = null;
        $recentDuplicateExpense = false;

        if (Schema::hasTable('accounting_special_issues')) {
            $hasDescCol = Schema::hasColumn('accounting_special_issues', 'description');
            $searchTerms = array_filter([
                $item->name,
                $item->sku,
            ]);

            $lastExpense = AccountingSpecialIssue::query()
                ->where(function ($q) use ($searchTerms, $cleanQuery, $hasDescCol) {
                    foreach ($searchTerms as $term) {
                        if ($term !== '') {
                            $q->orWhere('title', 'like', '%' . $term . '%');
                            if ($hasDescCol) {
                                $q->orWhere('description', 'like', '%' . $term . '%');
                            }
                        }
                    }
                    $q->orWhere('title', 'like', '%' . $cleanQuery . '%');
                })
                ->orderByDesc('execution_date')
                ->first();

            if ($lastExpense && $lastExpense->execution_date) {
                $lastPurchaseDate = $lastExpense->execution_date->copy();
                $lastPurchaseTitle = $lastExpense->title;
                if ($lastPurchaseDate->greaterThanOrEqualTo(now()->subDays(self::DUPLICATE_ORDER_LOCK_DAYS))) {
                    $recentDuplicateExpense = true;
                }
            }
        }

        $recentDuplicateTicket = false;
        if (Schema::hasTable('ai_universe_tickets')) {
            $recentTickets = AiUniverseTicket::query()
                ->where('ticket_type', 'procurement')
                ->whereIn('status', [
                    AiUniverseTicket::STATUS_WAITING_FOR_TRESOR,
                    AiUniverseTicket::STATUS_COMPLETED,
                    AiUniverseTicket::STATUS_IN_PROGRESS,
                ])
                ->where('created_at', '>=', now()->subDays(self::DUPLICATE_ORDER_LOCK_DAYS))
                ->get(['title', 'action_payload']);

            foreach ($recentTickets as $rt) {
                $p = is_array($rt->action_payload) ? $rt->action_payload : [];
                if (
                    (isset($p['item_id']) && (string) $p['item_id'] === (string) $item->id)
                    || stripos((string) $rt->title, (string) $item->name) !== false
                ) {
                    $recentDuplicateTicket = true;
                    break;
                }
            }
        }

        $duplicateLockTriggered = $recentDuplicateExpense || $recentDuplicateTicket;

        // 3. Echter Verbrauchs-Abgleich seit letztem Kauf (oder letzten 90 Tagen) in 0 Tokens per SQL
        $sinceDate = $lastPurchaseDate ? $lastPurchaseDate->copy()->startOfDay() : now()->subDays(90)->startOfDay();
        $consumedUnits = 0;

        if (
            Schema::hasTable('product_packagings')
            && Schema::hasTable('order_order_items')
            && Schema::hasTable('order_orders')
        ) {
            try {
                $fkCol = Schema::hasColumn('product_packagings', 'product_packaging_item_id')
                    ? 'product_packaging_item_id'
                    : 'packaging_item_id';

                $linkedProductIds = DB::table('product_packagings')
                    ->where($fkCol, $item->id)
                    ->pluck('product_id')
                    ->all();

                if (!empty($linkedProductIds)) {
                    $consumedUnits = (int) DB::table('order_order_items')
                        ->join('order_orders', 'order_orders.id', '=', 'order_order_items.order_id')
                        ->whereIn('order_order_items.product_id', $linkedProductIds)
                        ->whereNotIn('order_orders.status', ['cancelled', 'storniert', 'refunded'])
                        ->where('order_orders.created_at', '>=', $sinceDate)
                        ->sum('order_order_items.quantity');
                }
            } catch (\Throwable) {
            }
        }

        // 4. Exakte Brutto-Gesamtsumme inkl. 19 % USt. & Versand
        $netMerchandiseCents = $netPackCents * $packs;
        $grossMerchandiseCents = (int) round($netMerchandiseCents * 1.19);
        $shippingGrossCents = ($learned && isset($learned->structured_value['shipping_gross_cents']))
            ? max(0, (int) $learned->structured_value['shipping_gross_cents'])
            : self::DEFAULT_SHIPPING_GROSS_CENTS;

        $grossTotalCents = $grossMerchandiseCents + $shippingGrossCents;
        $totalUnitsToOrder = max(1, (int) $item->pack_quantity) * $packs;

        // 5. 7-Tage kumulierter Auto-Kauf-Deckel (max. 50,00 €)
        $weeklyAutoSpentCents = 0;
        if (Schema::hasTable('ai_universe_tickets')) {
            $recentAutoTickets = AiUniverseTicket::query()
                ->where('ticket_type', 'procurement')
                ->where('created_at', '>=', now()->subDays(7))
                ->get(['action_payload']);

            foreach ($recentAutoTickets as $rt) {
                $payload = is_array($rt->action_payload) ? $rt->action_payload : [];
                if (!empty($payload['auto_executed_under_25'])) {
                    $weeklyAutoSpentCents += (int) ($payload['gross_total_cents'] ?? 0);
                }
            }
        }

        $withinSingle25Limit = $grossTotalCents <= self::AUTO_EXECUTION_MAX_GROSS_CENTS;
        $withinWeekly50Cap = ($weeklyAutoSpentCents + $grossTotalCents) <= self::WEEKLY_AUTO_CAP_GROSS_CENTS;
        $isLiveEnv = ($envTier === FunkiraPresenceContext::ENV_LIVE);

        $blockingReasons = [];
        if (!$isWhitelisted) {
            $blockingReasons[] = 'Stamm-Artikel besitzt noch keine gültige HTTPS-Nachbestell-URL oder keinen Gebindepreis.';
        }
        if ($duplicateLockTriggered) {
            $dateNote = $lastPurchaseDate ? $lastPurchaseDate->format('d.m.Y') : 'innerhalb der letzten 14 Tage';
            $blockingReasons[] = "14-Tage Doppelkauf-Sperre aktiv: Artikel wurde bereits ({$dateNote}) bestellt bzw. befindet sich in Ausführung.";
        }
        if (!$withinSingle25Limit) {
            $blockingReasons[] = 'Gesamtsumme (' . self::formatEuro($grossTotalCents) . ' Brutto inkl. Versand) liegt über dem 25,00 € Auto-Limit -> 1-Klick-Freigabe durch Alina erforderlich.';
        }
        if (!$withinWeekly50Cap) {
            $blockingReasons[] = '7-Tage Auto-Kauf-Deckel (50,00 €) erreicht -> manuelle Freigabe durch Alina erforderlich.';
        }
        if (!$isLiveEnv) {
            $blockingReasons[] = "Aktive Umgebung ist {$envTier} (nicht LIVE) -> Echtgeld-Bestellungen erfordern zur Sicherheit Alinas expliziten Klick.";
        }

        $autoExecuteAllowed = $isWhitelisted
            && !$duplicateLockTriggered
            && $withinSingle25Limit
            && $withinWeekly50Cap
            && $isLiveEnv;

        $supplierName = $item->supplier?->name ?: ($item->supplier_name ?: 'Hinterlegter Händler');
        $host = parse_url($reorderUrl, PHP_URL_HOST) ?: '';

        return [
            'found' => true,
            'item_id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'supplier_name' => $supplierName,
            'reorder_url' => $reorderUrl,
            'reorder_domain' => $host,
            'packs' => $packs,
            'pack_quantity' => (int) $item->pack_quantity,
            'total_units' => $totalUnitsToOrder,
            'current_stock' => (int) $item->stock_quantity,
            'min_stock_threshold' => (int) $item->min_stock_threshold,
            'consumed_since_last_purchase' => $consumedUnits,
            'last_purchase_date' => $lastPurchaseDate?->format('Y-m-d'),
            'last_purchase_title' => $lastPurchaseTitle,
            'net_merchandise_cents' => $netMerchandiseCents,
            'gross_merchandise_cents' => $grossMerchandiseCents,
            'shipping_gross_cents' => $shippingGrossCents,
            'gross_total_cents' => $grossTotalCents,
            'gross_total_formatted' => self::formatEuro($grossTotalCents),
            'weekly_auto_spent_cents' => $weeklyAutoSpentCents,
            'is_whitelisted_stamm_artikel' => $isWhitelisted,
            'duplicate_lock_triggered' => $duplicateLockTriggered,
            'within_25_euro_limit' => $withinSingle25Limit,
            'within_50_euro_weekly_cap' => $withinWeekly50Cap,
            'environment_tier' => $envTier,
            'auto_execute_allowed' => $autoExecuteAllowed,
            'requires_alina_confirmation' => !$autoExecuteAllowed,
            'blocking_reasons' => $blockingReasons,
            'delivery_address_guard' => 'Alina Steinhauer, Mein-Seelenfunke, Carl-Goerdeler-Ring 26, 38518 Gifhorn',
            'max_allowed_checkout_cents' => (int) round($grossTotalCents * 1.05),
        ];
    }

    public static function formatEuro(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.') . ' €';
    }
}
