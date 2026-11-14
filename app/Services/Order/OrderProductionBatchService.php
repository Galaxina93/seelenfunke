<?php

namespace App\Services\Order;

use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use Illuminate\Support\Collection;

class OrderProductionBatchService
{
    public const SETUP_CRYSTAL_3D = 'crystal_3d';
    public const SETUP_ROTARY_RA3 = 'rotary_ra3';
    public const SETUP_CONVEYOR   = 'conveyor_batch';
    public const SETUP_FLATBED    = 'flatbed_laser';
    public const SETUP_PACK_ONLY  = 'pack_only';
    public const SETUP_DIGITAL    = 'digital_only';

    /**
     * Analysiert und sortiert alle offenen Bestellungen (pending / processing) nach dem
     * 5-stufigen Seelenfunke Smart-Fertigungs-Score (Rüstzeit-Optimierung + Fristschutz)
     * und erzeugt die exakte 1-Satz-Handlungsanweisung für Funki.
     *
     * @param Collection<int, OrderOrder> $openOrders
     * @param string|null $activeBatchProductId Zuletzt gelasertes oder aktiv angewähltes Produkt
     * @return array{
     *     ranked_orders: Collection<int, OrderOrder>,
     *     ranked_ids: array<int, string>,
     *     analyses: array<string, array<string, mixed>>,
     *     batch_groups: array<int, array<string, mixed>>,
     *     effective_focus_product_id: string|null,
     *     priority_order: OrderOrder|null,
     *     funki_directive: array{
     *         mood: string,
     *         action_html: string,
     *         action_plain: string,
     *         tip_html: string,
     *         badge_label: string|null
     *     }
     * }
     */
    public function buildSmartQueue(Collection $openOrders, ?string $activeBatchProductId = null): array
    {
        if ($openOrders->isEmpty()) {
            return [
                'ranked_orders' => collect(),
                'ranked_ids' => [],
                'analyses' => [],
                'batch_groups' => [],
                'effective_focus_product_id' => null,
                'priority_order' => null,
                'funki_directive' => [
                    'mood' => 'chill',
                    'action_html' => 'Im Moment liegen keine offenen Bestellungen vor. Genieße die freie Zeit oder bereite Rohlinge vor.',
                    'action_plain' => 'Im Moment liegen keine offenen Bestellungen vor.',
                    'tip_html' => 'Alle offenen Aufträge sind abgearbeitet und versendet.',
                    'badge_label' => null,
                ],
            ];
        }

        // 1. Einzelanalyse jeder offenen Bestellung
        $analyses = [];
        foreach ($openOrders as $order) {
            $analyses[(string) $order->id] = $this->analyzeOrder($order);
        }

        // 2. Artikel-Gruppen (Serien) über alle sofort fertigbaren Positionen aggregieren
        $productStats = [];
        foreach ($openOrders as $order) {
            $oid = (string) $order->id;
            $a = $analyses[$oid];

            foreach ($a['actionable_production_items'] as $itemInfo) {
                $pid = (string) $itemInfo['product_id'];
                if ($pid === '') {
                    continue;
                }

                if (!isset($productStats[$pid])) {
                    $productStats[$pid] = [
                        'product_id' => $pid,
                        'product_name' => $itemInfo['product_name'],
                        'sku' => $itemInfo['sku'],
                        'setup_group' => $itemInfo['setup_group'],
                        'setup_label' => $this->setupGroupShortLabel($itemInfo['setup_group']),
                        'thumb_url' => $itemInfo['thumb_url'],
                        'open_orders_count' => 0,
                        'open_units_count' => 0,
                        'ready_orders_count' => 0,
                        'ready_units_count' => 0,
                        'earliest_timestamp' => $a['created_ts'],
                        'order_ids_seen' => [],
                        'ready_order_ids_seen' => [],
                    ];
                }

                if (!isset($productStats[$pid]['order_ids_seen'][$oid])) {
                    $productStats[$pid]['order_ids_seen'][$oid] = true;
                    $productStats[$pid]['open_orders_count']++;
                }
                $productStats[$pid]['open_units_count'] += (int) $itemInfo['remaining_qty'];

                if (in_array($a['stage'], ['ready_laser', 'ready_pack'], true)) {
                    if (!isset($productStats[$pid]['ready_order_ids_seen'][$oid])) {
                        $productStats[$pid]['ready_order_ids_seen'][$oid] = true;
                        $productStats[$pid]['ready_orders_count']++;
                    }
                    $productStats[$pid]['ready_units_count'] += (int) $itemInfo['remaining_qty'];
                    if ($a['created_ts'] < $productStats[$pid]['earliest_timestamp']) {
                        $productStats[$pid]['earliest_timestamp'] = $a['created_ts'];
                    }
                }
            }
        }

        // Prüfen, ob das aktiv fokussierte Produkt überhaupt noch offene, sofort fertigbare Positionen hat
        $effectiveFocusProductId = null;
        $effectiveFocusSetupGroup = null;
        if ($activeBatchProductId !== null && $activeBatchProductId !== '') {
            if (!empty($productStats[$activeBatchProductId]) && $productStats[$activeBatchProductId]['ready_orders_count'] > 0) {
                $effectiveFocusProductId = $activeBatchProductId;
                $effectiveFocusSetupGroup = $productStats[$activeBatchProductId]['setup_group'];
            }
        }

        // Batch-Pills für das UI sortieren: Sofort fertigbare Serien zuerst, dann nach frühestem Auftrag
        $batchGroups = array_values(array_map(static function (array $stat): array {
            unset($stat['order_ids_seen'], $stat['ready_order_ids_seen']);
            return $stat;
        }, $productStats));

        usort($batchGroups, static function (array $a, array $b) use ($effectiveFocusProductId): int {
            $aFocus = ($a['product_id'] === $effectiveFocusProductId) ? 0 : 1;
            $bFocus = ($b['product_id'] === $effectiveFocusProductId) ? 0 : 1;
            if ($aFocus !== $bFocus) {
                return $aFocus <=> $bFocus;
            }
            $aHasReady = $a['ready_orders_count'] > 0 ? 0 : 1;
            $bHasReady = $b['ready_orders_count'] > 0 ? 0 : 1;
            if ($aHasReady !== $bHasReady) {
                return $aHasReady <=> $bHasReady;
            }
            if ($a['earliest_timestamp'] !== $b['earliest_timestamp']) {
                return $a['earliest_timestamp'] <=> $b['earliest_timestamp'];
            }
            return $b['ready_units_count'] <=> $a['ready_units_count'];
        });

        // 3. Smart-Score Sortierung über alle offenen Aufträge
        $sortedOrders = $openOrders->sort(function (OrderOrder $orderA, OrderOrder $orderB) use (&$analyses, $productStats, $effectiveFocusProductId, $effectiveFocusSetupGroup): int {
            $a = $analyses[(string) $orderA->id];
            $b = $analyses[(string) $orderB->id];

            $tupleA = $this->computeSortTuple($a, $productStats, $effectiveFocusProductId, $effectiveFocusSetupGroup);
            $tupleB = $this->computeSortTuple($b, $productStats, $effectiveFocusProductId, $effectiveFocusSetupGroup);

            return $tupleA <=> $tupleB;
        })->values();

        // Fokussiertes Ziel-Item pro Bestellung verfeinern (falls Kombi-Bestellung mit aktivem Serien-Produkt)
        foreach ($sortedOrders as $order) {
            $oid = (string) $order->id;
            if ($effectiveFocusProductId !== null) {
                foreach ($analyses[$oid]['actionable_production_items'] as $candidate) {
                    if ($candidate['product_id'] === $effectiveFocusProductId) {
                        $analyses[$oid]['recommended_item'] = $candidate;
                        break;
                    }
                }
            }
        }

        $priorityOrder = $sortedOrders->first();
        $funkiDirective = $priorityOrder
            ? $this->buildFunkiDirective($priorityOrder, $analyses[(string) $priorityOrder->id], $productStats, $effectiveFocusProductId)
            : [
                'mood' => 'chill',
                'action_html' => 'Keine offenen Aufgaben vorhanden.',
                'action_plain' => 'Keine offenen Aufgaben vorhanden.',
                'tip_html' => 'Alles erledigt.',
                'badge_label' => null,
            ];

        return [
            'ranked_orders' => $sortedOrders,
            'ranked_ids' => $sortedOrders->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'analyses' => $analyses,
            'batch_groups' => $batchGroups,
            'effective_focus_product_id' => $effectiveFocusProductId,
            'priority_order' => $priorityOrder,
            'funki_directive' => $funkiDirective,
        ];
    }

    /**
     * Berechnet das lexikografische Sortier-Tupel einer Bestellung:
     * 1. Express & Fristschutz (inkl. >= 48h wartende bezahlte Aufträge gegen Aushungern)
     * 2. Produktionsreife (Sofort fertigbar / Aktive Serie vor Rohling-Bestellung vor Wartend/Unbezahlt)
     * 3. Aktiver Laser-Fokus & Rüstgruppen-Affinität (gleiches Produkt -> gleiches xTool-Setup)
     * 4. Natürliche Artikel-Serien-Bündelung (orientiert am ältesten Auftrag der jeweiligen Artikelgruppe)
     * 5. Sortenreinheit (1-Artikel-Aufträge zuerst versandfertig) & FIFO (created_at ASC)
     */
    private function computeSortTuple(
        array $a,
        array $productStats,
        ?string $effectiveFocusProductId,
        ?string $effectiveFocusSetupGroup
    ): array {
        // Stufe 1: Dringlichkeit (0 = Express / Überfällig, 1 = Alter >= 48h Fristschutz bei bezahlter Lagerware, 2 = Normal)
        if ($a['is_express'] || $a['is_overdue']) {
            $urgencyTier = 0;
        } elseif ($a['is_aging_sla'] && in_array($a['stage'], ['ready_laser', 'ready_pack', 'ready_ship', 'action_supplier_order'], true)) {
            $urgencyTier = 1;
        } else {
            $urgencyTier = 2;
        }

        $hasFocusProduct = $effectiveFocusProductId !== null
            && in_array($effectiveFocusProductId, $a['actionable_product_ids'], true)
            && in_array($a['stage'], ['ready_laser', 'ready_pack'], true);

        $hasFocusSetup = !$hasFocusProduct
            && $effectiveFocusSetupGroup !== null
            && in_array($effectiveFocusSetupGroup, $a['actionable_setup_groups'], true)
            && in_array($a['stage'], ['ready_laser', 'ready_pack'], true);

        // Stufe 2: Produktions-Reifegrad
        $stageRank = match ($a['stage']) {
            'critical_stock'         => $a['is_express'] ? 5 : 48,
            'ready_laser'            => $hasFocusProduct ? 8 : 14,
            'ready_pack'             => $hasFocusProduct ? 9 : 16,
            'ready_ship'             => 12,
            'ready_digital'          => 18,
            'action_supplier_order'  => $a['waited_hours'] >= 12 ? 11 : 22,
            'blocked_supplier_wait'  => 40,
            'blocked_photo_issue'    => 44,
            'blocked_unpaid'         => 50,
            default                  => 60,
        };

        // Stufe 3: Fokus-Affinität
        $focusRank = $hasFocusProduct ? 0 : ($hasFocusSetup ? 1 : 2);

        // Stufe 4: Natürliche Serien-Bündelung über das älteste Bestelldatum der primären Artikelgruppe
        $primaryPid = $hasFocusProduct ? $effectiveFocusProductId : ($a['primary_product_id'] ?? '');
        $groupEarliestTs = ($primaryPid !== '' && isset($productStats[$primaryPid]))
            ? (int) $productStats[$primaryPid]['earliest_timestamp']
            : (int) $a['created_ts'];

        $setupOrderRank = match ($a['primary_setup_group']) {
            self::SETUP_FLATBED    => 1,
            self::SETUP_CRYSTAL_3D => 2,
            self::SETUP_ROTARY_RA3 => 3,
            self::SETUP_PACK_ONLY  => 4,
            self::SETUP_DIGITAL    => 5,
            default                => 9,
        };

        // Stufe 5: Sortenreine Aufträge (nur 1 offene Position -> sofort versandfertig) vor Misch-Warenkörben, danach FIFO
        $purityRank = $a['incomplete_items_count'] <= 1 ? 0 : 1;

        return [
            $urgencyTier,
            $stageRank,
            $focusRank,
            $groupEarliestTs,
            $setupOrderRank,
            (string) $primaryPid,
            $purityRank,
            (int) $a['created_ts'],
            (string) $a['order_number'],
        ];
    }

    /**
     * Untersucht eine einzelne Bestellung auf alle Produktions-, Zahlungs-, Foto-, Lager- und Versand-Zustände.
     */
    public function analyzeOrder(OrderOrder $order): array
    {
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->with('product.supplier')->get();
        $shipments = $order->relationLoaded('shipments') ? $order->shipments : $order->shipments()->get();

        $isPaid = $order->payment_status === 'paid';
        $isExpress = (bool) $order->is_express;
        $isOverdue = (bool) ($order->deadline && $order->deadline->isPast() && !in_array($order->status, ['shipped', 'completed', 'cancelled'], true));
        $createdTs = $order->created_at ? (int) $order->created_at->getTimestamp() : 0;
        $waitedHours = $order->created_at ? (int) $order->created_at->diffInHours(now()) : 0;
        $isAgingSla = $waitedHours >= 48;

        $isOnlyDigital = $items->isNotEmpty() && $order->isOnlyDigital();
        $hasPrintableLabel = !$isOnlyDigital && $shipments->contains(fn ($s) => !empty($s->shipping_label_path));

        $incompleteItems = [];
        $actionableProductionItems = [];
        $pendingSupplierItems = [];
        $waitingSupplierItems = [];
        $photoIssueItems = [];
        $criticalStockItems = [];

        foreach ($items as $item) {
            if ($item->is_completed) {
                continue;
            }

            $conf = is_array($item->configuration) ? $item->configuration : [];
            $product = $item->product;
            $remainingQty = max(1, (int) $item->quantity - (int) ($item->completed_quantity ?? 0));
            $setupGroup = $this->resolveSetupGroup($product, $item);
            $isDualSided = $this->isDualSidedItem($item);
            $thumbUrl = $this->resolveItemThumbUrl($item);

            $hasPhotoIssue = isset($conf['photo_issue'])
                && is_array($conf['photo_issue'])
                && empty($conf['photo_issue']['resolved']);

            $needsSupplier = $item->requiresSupplierProcurement();
            $supplierOrdered = $needsSupplier && $item->isSupplierOrderPlaced();

            // Echter Lagerfehlbestand (ohne erlaubten Überverkauf)
            $hasStockShortage = false;
            if ($product && $product->type !== 'digital' && $product->track_quantity && !$product->continue_selling_when_out_of_stock) {
                $currentStock = (int) $product->quantity;
                if ((!$isPaid && $remainingQty > max(0, $currentStock)) || ($isPaid && $currentStock < 0)) {
                    $hasStockShortage = true;
                }
            }

            $itemSummary = [
                'item_id' => (string) $item->id,
                'product_id' => (string) ($item->product_id ?? ''),
                'product_name' => (string) ($item->product_name ?: ($product->name ?? 'Artikel')),
                'sku' => (string) ($product->sku ?? ''),
                'quantity' => (int) $item->quantity,
                'remaining_qty' => $remainingQty,
                'setup_group' => $setupGroup,
                'is_dual_sided' => $isDualSided,
                'thumb_url' => $thumbUrl,
                'supplier_name' => $item->getSupplierName() ?: 'Lieferant',
                'missing_supplier_qty' => $item->getMissingSupplierQuantity(),
                'stock_available' => (int) ($product->quantity ?? 0),
                'photo_issue_reason' => $hasPhotoIssue ? (string) ($conf['photo_issue']['reason'] ?? 'Foto-Rückfrage') : null,
            ];

            $incompleteItems[] = $itemSummary;

            if ($hasStockShortage) {
                $criticalStockItems[] = $itemSummary;
            }

            if ($hasPhotoIssue) {
                $photoIssueItems[] = $itemSummary;
                continue;
            }

            if ($needsSupplier && !$supplierOrdered) {
                $pendingSupplierItems[] = $itemSummary;
                continue;
            }

            if ($needsSupplier && $supplierOrdered) {
                $waitingSupplierItems[] = $itemSummary;
                continue;
            }

            if ($setupGroup !== self::SETUP_DIGITAL) {
                $actionableProductionItems[] = $itemSummary;
            }
        }

        // Primären Produktions-Zustand (Stage) bestimmen
        if (!empty($criticalStockItems)) {
            $stage = 'critical_stock';
        } elseif (!$isPaid) {
            $stage = 'blocked_unpaid';
        } elseif ($isOnlyDigital) {
            $stage = 'ready_digital';
        } elseif ($items->isNotEmpty() && empty($incompleteItems)) {
            $stage = 'ready_ship';
        } elseif (!empty($actionableProductionItems)) {
            $hasLaser = collect($actionableProductionItems)->contains(
                fn (array $i) => in_array($i['setup_group'], [self::SETUP_CRYSTAL_3D, self::SETUP_ROTARY_RA3, self::SETUP_FLATBED], true)
            );
            $stage = $hasLaser ? 'ready_laser' : 'ready_pack';
        } elseif (!empty($pendingSupplierItems)) {
            $stage = 'action_supplier_order';
        } elseif (!empty($photoIssueItems)) {
            $stage = 'blocked_photo_issue';
        } elseif (!empty($waitingSupplierItems)) {
            $stage = 'blocked_supplier_wait';
        } else {
            $stage = $items->isEmpty() ? 'empty_order' : 'ready_ship';
        }

        // Empfohlene nächste Position innerhalb dieser Bestellung (Laser vor Packware)
        $recommendedItem = null;
        foreach ($actionableProductionItems as $cand) {
            if (in_array($cand['setup_group'], [self::SETUP_CRYSTAL_3D, self::SETUP_ROTARY_RA3, self::SETUP_FLATBED], true)) {
                $recommendedItem = $cand;
                break;
            }
        }
        if ($recommendedItem === null && !empty($actionableProductionItems)) {
            $recommendedItem = $actionableProductionItems[0];
        }

        $primaryProductId = $recommendedItem['product_id'] ?? ($incompleteItems[0]['product_id'] ?? '');
        $primarySetupGroup = $recommendedItem['setup_group'] ?? ($isOnlyDigital ? self::SETUP_DIGITAL : self::SETUP_PACK_ONLY);

        // Verpackungs-Kurzinfo (Tasche vs. Karton) nur bei physischen Aufträgen
        $canFitEnvelope = false;
        if (!$isOnlyDigital && $items->isNotEmpty()) {
            $dhlDetails = $order->getDhlWeightDetails(1, 'box');
            $canFitEnvelope = !empty($dhlDetails['can_fit_in_envelope']);
        }

        return [
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'customer_name' => (string) $order->customer_name,
            'is_paid' => $isPaid,
            'is_express' => $isExpress,
            'is_overdue' => $isOverdue,
            'is_aging_sla' => $isAgingSla,
            'waited_hours' => $waitedHours,
            'created_ts' => $createdTs,
            'is_only_digital' => $isOnlyDigital,
            'has_printable_label' => $hasPrintableLabel,
            'can_fit_envelope' => $canFitEnvelope,
            'stage' => $stage,
            'total_items_count' => $items->count(),
            'incomplete_items_count' => count($incompleteItems),
            'incomplete_items' => $incompleteItems,
            'actionable_production_items' => $actionableProductionItems,
            'actionable_product_ids' => array_values(array_unique(array_filter(array_column($actionableProductionItems, 'product_id')))),
            'actionable_setup_groups' => array_values(array_unique(array_filter(array_column($actionableProductionItems, 'setup_group')))),
            'pending_supplier_items' => $pendingSupplierItems,
            'waiting_supplier_items' => $waitingSupplierItems,
            'photo_issue_items' => $photoIssueItems,
            'critical_stock_items' => $criticalStockItems,
            'recommended_item' => $recommendedItem,
            'primary_product_id' => $primaryProductId,
            'primary_setup_group' => $primarySetupGroup,
        ];
    }

    /**
     * Erzeugt den präzisen 1-Satz-Handlungsbefehl ("Was ist jetzt exakt zu tun?") für jede denkbare Konstellation.
     */
    private function buildFunkiDirective(
        OrderOrder $order,
        array $a,
        array $productStats,
        ?string $effectiveFocusProductId
    ): array {
        $ordNo = e('#' . $order->order_number);
        $cust = e($order->customer_name);
        $packWord = $a['can_fit_envelope'] ? 'Luftpolstertasche' : 'Versandkarton';

        $mood = ($a['is_express'] || $a['is_overdue'] || $a['stage'] === 'critical_stock') ? 'alarm' : 'work';
        $badgeLabel = null;

        // 1. Rein digitale Bestellung (Kompatibel mit bestehendem Test '⚡ DIGITALE BEREITSTELLUNG')
        if ($a['is_only_digital']) {
            if ($a['is_paid']) {
                $actionHtml = "Keine Handarbeit nötig: Digitale Bestellung <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong> ({$cust}) ist bereitgestellt, setze den Status auf <strong class=\"text-emerald-600 dark:text-emerald-400 font-bold\">„Abgeschlossen“</strong>.";
                $tipHtml = '<span class="text-blue-600 dark:text-blue-400 font-black">⚡ DIGITALE BEREITSTELLUNG:</span> Ausschließlich digitale Dateien, die Auslieferung erfolgt automatisch über das Kundenportal.';
            } else {
                $actionHtml = "Zahlung abwarten: Prüfe den Zahlungseingang für die digitale Bestellung <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong> ({$cust}) und buche sie auf <strong class=\"text-gray-900 dark:text-white font-bold\">„Bezahlt“</strong>.";
                $tipHtml = '<span class="text-blue-600 dark:text-blue-400 font-black">⚡ DIGITALE BEREITSTELLUNG:</span> Nach Zahlungsfreigabe werden alle Downloads vollautomatisch im Kundenkonto freigeschaltet.';
            }

            return [
                'mood' => $mood,
                'action_html' => $actionHtml,
                'action_plain' => strip_tags($actionHtml),
                'tip_html' => $tipHtml,
                'badge_label' => 'Digital-Auftrag',
            ];
        }

        // 2. Kritischer Lagerfehlbestand (ohne erlaubten Überverkauf)
        if ($a['stage'] === 'critical_stock' && !empty($a['critical_stock_items'])) {
            $item = $a['critical_stock_items'][0];
            $pName = e($item['product_name']);
            $qty = (int) $item['remaining_qty'];
            $actionHtml = "Lagerbestand sofort prüfen: Für <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong> ({$cust}) fehlen physisch <strong class=\"text-red-500 font-bold\">{$qty}× „{$pName}“</strong> im Bestand.";
            $tipHtml = '<span class="text-red-500 font-bold">Lagerbestand kritisch:</span> Prüfe den realen Regalbestand oder bestelle den Rohling vor Produktionsbeginn nach.';

            return [
                'mood' => 'alarm',
                'action_html' => $actionHtml,
                'action_plain' => strip_tags($actionHtml),
                'tip_html' => $tipHtml,
                'badge_label' => 'Bestand prüfen',
            ];
        }

        // 3. Noch unbezahlte Bestellung (Vorkasse / Zahlung offen)
        if ($a['stage'] === 'blocked_unpaid') {
            $prefix = $a['is_express'] ? 'Express-Zahlung prüfen' : 'Noch nicht lasern (Zahlung offen)';
            $actionHtml = "{$prefix}: Kontrolliere den Geldeingang für <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong> ({$cust}) und klicke erst bei Eingang auf <strong class=\"text-emerald-600 dark:text-emerald-400 font-bold\">„Bezahlt“</strong>.";
            $tipHtml = $a['is_express']
                ? '<span class="text-amber-600 dark:text-amber-400 font-bold">Express wartet auf Zahlung:</span> Sobald als bezahlt markiert, startet sofort die priorisierte Fertigung.'
                : 'Alle bezahlten Lager-Aufträge sind bereits abgearbeitet, dieser Auftrag wartet aktuell nur noch auf den Zahlungseingang.';

            return [
                'mood' => $mood,
                'action_html' => $actionHtml,
                'action_plain' => strip_tags($actionHtml),
                'tip_html' => $tipHtml,
                'badge_label' => 'Zahlung offen',
            ];
        }

        // 4. Alle Positionen bereits fertig gelasert/gepackt -> Nur noch Versand / Label!
        if ($a['stage'] === 'ready_ship') {
            if ($a['has_printable_label']) {
                $actionHtml = "Fertig produziert, jetzt versenden: Drucke das fertige DHL-Label für <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong> ({$cust}) auf dem MUNBYN, klebe es auf die {$packWord} und stelle auf <strong class=\"text-purple-600 dark:text-purple-400 font-bold\">„Versendet“</strong>.";
                $tipHtml = '<span class="text-emerald-600 dark:text-emerald-400 font-bold">Versandbereit:</span> Alle Positionen sind abgehakt und das DHL-Etikett liegt druckbereit für Funkenwerk vor.';
                $badgeLabel = 'Label drucken';
            } else {
                $actionHtml = "Fertig gelasert, Label erstellen: Öffne <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong> ({$cust}), verpacke die Ware ({$packWord}) und klicke oben rechts auf <strong class=\"text-gray-900 dark:text-white font-bold\">„DHL Label“</strong>.";
                $tipHtml = '<span class="text-emerald-600 dark:text-emerald-400 font-bold">Produktion erledigt:</span> Alle Artikel in diesem Auftrag sind bereits fertiggestellt, es fehlt nur noch das Versandlabel.';
                $badgeLabel = 'Versandfertig';
            }

            return [
                'mood' => $mood,
                'action_html' => $actionHtml,
                'action_plain' => strip_tags($actionHtml),
                'tip_html' => $tipHtml,
                'badge_label' => $badgeLabel,
            ];
        }

        // 5. Sofort fertigbare Laser-Position (inkl. Serien-/Rüstzeit-Intelligenz!)
        if ($a['stage'] === 'ready_laser' && $a['recommended_item'] !== null) {
            $item = $a['recommended_item'];
            $pid = (string) $item['product_id'];
            $pName = e($item['product_name']);
            $qty = (int) $item['remaining_qty'];
            $setupInstruction = $this->buildLaserSetupInstruction($item);

            $readyOrdersForProduct = (int) ($productStats[$pid]['ready_orders_count'] ?? 1);
            $readyUnitsForProduct = (int) ($productStats[$pid]['ready_units_count'] ?? $qty);
            $remainingOtherOrders = max(0, $readyOrdersForProduct - 1);
            $isActiveFocus = ($effectiveFocusProductId !== null && $effectiveFocusProductId === $pid);

            $followStep = $a['incomplete_items_count'] <= 1
                ? 'und erstelle direkt das DHL-Label'
                : 'und hake die Position ab';

            if ($a['is_express']) {
                $actionHtml = "Express sofort lasern: Öffne <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong> ({$cust}), graviere <strong class=\"text-gray-900 dark:text-white font-bold\">{$qty}× „{$pName}“</strong> ({$setupInstruction}) {$followStep}.";
                $tipHtml = "<span class=\"text-emerald-600 dark:text-emerald-400 font-bold\">Lagerbestand gesichert:</span> Priorisierter Express-Auftrag · Verpackung danach: {$packWord}.";
                $badgeLabel = 'Express-Laser';
            } elseif ($isActiveFocus && $remainingOtherOrders > 0) {
                $weiterWort = $remainingOtherOrders === 1 ? 'wartet noch 1 weiterer Auftrag' : "warten noch {$remainingOtherOrders} weitere Aufträge";
                $actionHtml = "Laser-Serie ohne Umbau fortsetzen: Graviere direkt <strong class=\"text-gray-900 dark:text-white font-bold\">{$qty}× „{$pName}“</strong> ({$setupInstruction}) für <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong>, danach {$weiterWort} mit demselben Artikel.";
                $tipHtml = "<span class=\"text-emerald-600 dark:text-emerald-400 font-bold\">Rüstzeit-Vorteil aktiv:</span> Dein Laser bleibt unverändert eingerichtet ({$readyUnitsForProduct}× „{$pName}“ über {$readyOrdersForProduct} Aufträge gebündelt).";
                $badgeLabel = "Serie: {$readyUnitsForProduct}× {$pName}";
            } elseif ($isActiveFocus && $remainingOtherOrders === 0) {
                $actionHtml = "Letztes Stück der Serie: Graviere jetzt <strong class=\"text-gray-900 dark:text-white font-bold\">{$qty}× „{$pName}“</strong> ({$setupInstruction}) für <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong> {$followStep}.";
                $tipHtml = "<span class=\"text-emerald-600 dark:text-emerald-400 font-bold\">Serien-Abschluss:</span> Nach dieser Bestellung sind alle offenen „{$pName}“-Gravuren komplett erledigt.";
                $badgeLabel = 'Letztes Serien-Stück';
            } elseif ($remainingOtherOrders > 0) {
                $actionHtml = "Serie starten ({$readyOrdersForProduct} Aufträge gebündelt): Rüste auf <strong class=\"text-gray-900 dark:text-white font-bold\">{$setupInstruction}</strong> und lasere zuerst <strong class=\"text-gray-900 dark:text-white font-bold\">{$qty}× „{$pName}“</strong> für <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong> ({$cust}).";
                $tipHtml = "<span class=\"text-emerald-600 dark:text-emerald-400 font-bold\">Smart-Sortierung aktiv:</span> {$readyOrdersForProduct} Bestellungen mit insgesamt {$readyUnitsForProduct}× „{$pName}“ liegen direkt untereinander, damit du nur einmal einrichten musst.";
                $badgeLabel = "Serie: {$readyUnitsForProduct}× {$pName}";
            } else {
                $actionHtml = "Jetzt lasern: Öffne <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong> ({$cust}), graviere <strong class=\"text-gray-900 dark:text-white font-bold\">{$qty}× „{$pName}“</strong> ({$setupInstruction}) {$followStep}.";
                $tipHtml = $a['is_aging_sla']
                    ? "<span class=\"text-amber-600 dark:text-amber-400 font-bold\">Frist-Priorität:</span> Auftrag wartet seit über 48 Std. und wurde zum Schutz der Lieferzeit nach oben gezogen · Verpackung: {$packWord}."
                    : "<span class=\"text-emerald-600 dark:text-emerald-400 font-bold\">Sofort startklar:</span> Bezahlt & auf Lager · Nach dem Lasern direkt in {$packWord} verpacken.";
                $badgeLabel = $this->setupGroupShortLabel($item['setup_group']);
            }

            return [
                'mood' => $mood,
                'action_html' => $actionHtml,
                'action_plain' => strip_tags($actionHtml),
                'tip_html' => $tipHtml,
                'badge_label' => $badgeLabel,
            ];
        }

        // 6. Reine physische Lagerware ohne Laser-Arbeit (ready_pack)
        if ($a['stage'] === 'ready_pack' && $a['recommended_item'] !== null) {
            $item = $a['recommended_item'];
            $pName = e($item['product_name']);
            $qty = (int) $item['remaining_qty'];
            $actionHtml = "Kein Laser nötig: Nimm <strong class=\"text-gray-900 dark:text-white font-bold\">{$qty}× „{$pName}“</strong> für <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong> ({$cust}) aus dem Lagerregal, verpacke in {$packWord} und klicke auf <strong class=\"text-gray-900 dark:text-white font-bold\">„DHL Label“</strong>.";
            $tipHtml = '<span class="text-emerald-600 dark:text-emerald-400 font-bold">Schnell-Versand (Lagerware):</span> Keine Gravur erforderlich, direkt am Packtisch abwickeln.';

            return [
                'mood' => $mood,
                'action_html' => $actionHtml,
                'action_plain' => strip_tags($actionHtml),
                'tip_html' => $tipHtml,
                'badge_label' => 'Nur verpacken',
            ];
        }

        // 7. Rohling muss beim Lieferanten bestellt werden (Auftragsfertigung)
        if ($a['stage'] === 'action_supplier_order' && !empty($a['pending_supplier_items'])) {
            $item = $a['pending_supplier_items'][0];
            $pName = e($item['product_name']);
            $mQty = (int) $item['missing_supplier_qty'];
            $supName = e($item['supplier_name']);
            $actionHtml = "Jetzt Rohling nachbestellen: Öffne <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong> ({$cust}), bestelle <strong class=\"text-amber-600 dark:text-amber-400 font-bold\">{$mQty}× „{$pName}“</strong> per 1-Klick-Kauflink bei <strong class=\"text-gray-900 dark:text-white font-bold\">{$supName}</strong> und setze den Haken „Rohling bestellt“.";
            $tipHtml = '<span class="text-amber-600 dark:text-amber-400 font-bold">Auftragsfertigung (Bestand 0):</span> Nach dem Setzen des Hakens rückt der Auftrag sauber in den Wartebereich, bis die Lieferung eintrifft.';

            return [
                'mood' => $a['is_express'] ? 'alarm' : 'work',
                'action_html' => $actionHtml,
                'action_plain' => strip_tags($actionHtml),
                'tip_html' => $tipHtml,
                'badge_label' => 'Rohling bestellen',
            ];
        }

        // 8. Foto-Beanstandung offen (Warten auf Kunden-Upload)
        if ($a['stage'] === 'blocked_photo_issue' && !empty($a['photo_issue_items'])) {
            $item = $a['photo_issue_items'][0];
            $pName = e($item['product_name']);
            $actionHtml = "Pausiert wegen Kundenfoto: Prüfe in <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong> ({$cust}), ob für <strong class=\"text-gray-900 dark:text-white font-bold\">„{$pName}“</strong> ein neues Foto vorliegt, oder warte auf die Rückmeldung.";
            $tipHtml = '<span class="text-amber-600 dark:text-amber-400 font-bold">Wartet auf Kunden-Korrektur:</span> Alle sofort fertigbaren Aufträge sind bereits erledigt.';

            return [
                'mood' => $mood,
                'action_html' => $actionHtml,
                'action_plain' => strip_tags($actionHtml),
                'tip_html' => $tipHtml,
                'badge_label' => 'Foto-Klärung',
            ];
        }

        // 9. Rohling bereits beim Lieferanten bestellt (im Zulauf)
        if ($a['stage'] === 'blocked_supplier_wait' && !empty($a['waiting_supplier_items'])) {
            $item = $a['waiting_supplier_items'][0];
            $pName = e($item['product_name']);
            $supName = e($item['supplier_name']);
            $actionHtml = "Warten auf Wareneingang: Für <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong> ({$cust}) ist <strong class=\"text-gray-900 dark:text-white font-bold\">„{$pName}“</strong> bei {$supName} im Zulauf, erst nach Anlieferung lasern.";
            $tipHtml = '<span class="text-sky-600 dark:text-sky-400 font-bold">Rohling unterwegs:</span> Alle sofort verfügbaren Lager-Aufträge sind bereits abgearbeitet.';

            return [
                'mood' => $mood,
                'action_html' => $actionHtml,
                'action_plain' => strip_tags($actionHtml),
                'tip_html' => $tipHtml,
                'badge_label' => 'Rohling im Zulauf',
            ];
        }

        // 10. Fallback (z. B. Bestellung ohne Positionen)
        $actionHtml = "Jetzt Auftrag prüfen: Öffne <strong class=\"text-gray-900 dark:text-white font-bold\">{$ordNo}</strong> ({$cust}), kontrolliere die Details und schließe den Versand ab.";
        $tipHtml = $a['is_express']
            ? '<span class="text-emerald-600 dark:text-emerald-400 font-bold">Lagerbestand gesichert!</span> Dieser Express-Versand kann sofort abgewickelt werden.'
            : 'Dieser Auftrag liegt aktuell an oberster Stelle deiner Bearbeitungsliste.';

        return [
            'mood' => $mood,
            'action_html' => $actionHtml,
            'action_plain' => strip_tags($actionHtml),
            'tip_html' => $tipHtml,
            'badge_label' => null,
        ];
    }

    /**
     * Klassifiziert ein Produkt/Bestellitem in die physische xTool-Rüstgruppe.
     */
    public function resolveSetupGroup(?Product $product, ?OrderOrderItem $item = null): string
    {
        $conf = ($item && is_array($item->configuration)) ? $item->configuration : [];

        if (!empty($conf['is_gift_voucher'])) {
            return (($conf['delivery_method'] ?? 'email') === 'post')
                ? self::SETUP_PACK_ONLY
                : self::SETUP_DIGITAL;
        }

        if ($product && $product->type === 'digital') {
            return self::SETUP_DIGITAL;
        }

        $hasCustomInput = !empty($conf['texts'])
            || !empty($conf['text'])
            || !empty($conf['logos'])
            || !empty($conf['texts_back'])
            || !empty($conf['logos_back'])
            || !empty($conf['custom_fields_data'])
            || !empty($conf['uploaded_files']);

        if ($product && !$product->isPersonalizable() && !$hasCustomInput) {
            return self::SETUP_PACK_ONLY;
        }

        $slug = mb_strtolower((string) ($product->slug ?? ''));
        $sku = strtoupper((string) ($product->sku ?? ''));
        $name = mb_strtolower((string) ($item?->product_name ?: ($product->name ?? '')));
        $attrs = ($product && is_array($product->attributes)) ? $product->attributes : [];
        $material = mb_strtolower((string) ($attrs['Material'] ?? $attrs['material'] ?? $product->material ?? ''));
        $cfgSettings = ($product && is_array($product->configurator_settings)) ? $product->configurator_settings : [];
        $equipmentKey = trim((string) ($cfgSettings['xtool_equipment_key'] ?? ''));

        if ($equipmentKey === 'rotary_ra3') {
            return self::SETUP_ROTARY_RA3;
        }
        if ($equipmentKey === 'conveyor_batch') {
            return self::SETUP_CONVEYOR;
        }
        if ($equipmentKey === 'inner_3d') {
            return self::SETUP_CRYSTAL_3D;
        }

        $areaShape = mb_strtolower((string) ($cfgSettings['area_shape'] ?? ''));
        $overlayType = mb_strtolower((string) ($cfgSettings['overlay_type'] ?? ''));

        // RA3 Rotationsaufsatz (Gläser, Becher, Tassen, Flaschen, Zylinder)
        if (
            $overlayType === 'cylinder'
            || $areaShape === 'cylinder'
            || str_starts_with($sku, 'GLASS-')
            || str_contains($slug, 'glas')
            || str_contains($slug, 'becher')
            || str_contains($slug, 'tasse')
            || str_contains($slug, 'flasche')
            || str_contains($name, 'weizenglas')
        ) {
            return self::SETUP_ROTARY_RA3;
        }

        // 3D-Kristallfokus (Trophäen / K9-Kristallblöcke)
        if (
            str_starts_with($sku, 'CRYSTAL-')
            || str_contains($slug, 'kristall')
            || str_contains($name, 'kristall')
            || str_contains($material, 'kristall')
        ) {
            return self::SETUP_CRYSTAL_3D;
        }

        return self::SETUP_FLATBED;
    }

    /**
     * Prüft, ob für eine Position sowohl Vorder- als auch Rückseite graviert werden müssen.
     */
    public function isDualSidedItem(OrderOrderItem $item): bool
    {
        $conf = is_array($item->configuration) ? $item->configuration : [];

        if (!empty($conf['logos_back']) && is_array($conf['logos_back'])) {
            return true;
        }

        if (!empty($conf['texts_back']) && is_array($conf['texts_back'])) {
            foreach ($conf['texts_back'] as $row) {
                if (is_array($row) && trim((string) ($row['text'] ?? '')) !== '') {
                    return true;
                }
            }
        }

        if (!empty($conf['custom_fields_data']) && is_array($conf['custom_fields_data'])) {
            foreach ($conf['custom_fields_data'] as $key => $val) {
                $k = mb_strtolower((string) $key);
                if ((str_contains($k, 'back') || str_contains($k, 'rueck') || str_contains($k, 'rück')) && trim((string) $val) !== '') {
                    return true;
                }
            }
        }

        if (!empty($conf['addons_summary']) && is_array($conf['addons_summary'])) {
            foreach ($conf['addons_summary'] as $addon) {
                $label = mb_strtolower(is_array($addon) ? (string) ($addon['label'] ?? $addon['name'] ?? '') : (string) $addon);
                if (str_contains($label, 'rückseite') || str_contains($label, 'beidseitig')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function buildLaserSetupInstruction(array $itemSummary): string
    {
        $base = match ($itemSummary['setup_group'] ?? self::SETUP_FLATBED) {
            self::SETUP_ROTARY_RA3 => 'RA3-Rotationsaufsatz',
            self::SETUP_CONVEYOR   => 'Laufband-Serie',
            self::SETUP_CRYSTAL_3D => '3D-Kristallfokus',
            default                => 'Flachbett',
        };

        if (!empty($itemSummary['is_dual_sided'])) {
            return $base . ' · Vorder- und Rückseite';
        }

        return $base;
    }

    public function setupGroupShortLabel(string $setupGroup): string
    {
        return match ($setupGroup) {
            self::SETUP_ROTARY_RA3 => 'RA3-Rotation',
            self::SETUP_CONVEYOR   => 'Laufband',
            self::SETUP_CRYSTAL_3D => '3D-Kristall',
            self::SETUP_FLATBED    => 'Flachbett',
            self::SETUP_PACK_ONLY  => 'Lagerware',
            self::SETUP_DIGITAL    => 'Digital',
            default                => 'Fertigung',
        };
    }

    private function resolveItemThumbUrl(OrderOrderItem $item): ?string
    {
        $conf = is_array($item->configuration) ? $item->configuration : [];
        if (!empty($conf['snapshot_path'])) {
            $snap = is_array($conf['snapshot_path']) ? ($conf['snapshot_path']['front'] ?? null) : $conf['snapshot_path'];
            if (is_string($snap) && $snap !== '') {
                return asset('storage/' . ltrim($snap, '/'));
            }
        }

        return $item->resolveDisplayImageUrl();
    }

    public static function statusLabelDe(string $status): string
    {
        return match ($status) {
            'pending' => 'Wartend',
            'processing' => 'In Bearbeitung',
            'shipped' => 'Versendet',
            'completed' => 'Abgeschlossen',
            'cancelled' => 'Storniert',
            'refunded' => 'Erstattet',
            'failed' => 'Fehlgeschlagen',
            default => $status,
        };
    }

    /**
     * Berechnet den nächsten Wiedervorlage-Zeitpunkt für den Werkstatt-Check-in (100% identisch für Manuell & KI).
     *
     * @return array{next_check_at: \Carbon\Carbon|null, paused: bool, mode_label: string}
     */
    public static function resolveNextWorkshopCheckinAt(
        string $mode = '2h',
        ?\Carbon\CarbonInterface $now = null,
        ?int $customMinutes = null
    ): array {
        $base = ($now ? \Carbon\Carbon::instance($now) : now())->copy()->timezone('Europe/Berlin');
        $normalized = strtolower(trim($mode));

        if (in_array($normalized, ['pause', 'paused', 'none', 'off'], true)) {
            return [
                'next_check_at' => null,
                'paused' => true,
                'mode_label' => 'Check-in pausiert',
            ];
        }

        if (in_array($normalized, ['tomorrow', 'waiting_customer', 'waiting_supplier', 'next_workday'], true)) {
            $next = $base->copy()->addDay()->setTime(9, 30, 0);
            while ($next->isWeekend()) {
                $next->addDay();
            }
            return [
                'next_check_at' => $next->timezone(config('app.timezone', 'Europe/Berlin')),
                'paused' => false,
                'mode_label' => 'Nächster Werktag 09:30 Uhr',
            ];
        }

        if ($normalized === '1h' || $normalized === '60m') {
            return [
                'next_check_at' => $base->copy()->addMinutes(60)->timezone(config('app.timezone', 'Europe/Berlin')),
                'paused' => false,
                'mode_label' => 'In 1 Std.',
            ];
        }

        if ($normalized === 'custom_minutes' && $customMinutes !== null) {
            $mins = max(15, min(4320, $customMinutes));
            return [
                'next_check_at' => $base->copy()->addMinutes($mins)->timezone(config('app.timezone', 'Europe/Berlin')),
                'paused' => false,
                'mode_label' => 'In ' . $mins . ' Min.',
            ];
        }

        return [
            'next_check_at' => $base->copy()->addMinutes(120)->timezone(config('app.timezone', 'Europe/Berlin')),
            'paused' => false,
            'mode_label' => 'In 2 Std.',
        ];
    }

    /**
     * Erkennt Status-Abweichungen zwischen realem Werkstatt-Stand / Fertigungsfortschritt und dem Bestellstatus.
     * Ändert NIEMALS eigenmächtig den Status, sondern liefert einen klaren Vorschlag für Alina (Manuell & KI).
     *
     * @return array{
     *     has_discrepancy: bool,
     *     discrepancy_code: string|null,
     *     suggested_status: string|null,
     *     suggested_status_label: string|null,
     *     ui_hint: string|null,
     *     ask_alina_prompt: string|null
     * }
     */
    public function evaluateOrderStatusDiscrepancy(
        OrderOrder $order,
        ?array $analysis = null,
        ?string $latestNoteOverride = null
    ): array {
        $empty = [
            'has_discrepancy' => false,
            'discrepancy_code' => null,
            'suggested_status' => null,
            'suggested_status_label' => null,
            'ui_hint' => null,
            'ask_alina_prompt' => null,
        ];

        $status = (string) $order->status;
        if (!in_array($status, ['pending', 'processing'], true)) {
            return $empty;
        }

        $a = $analysis ?? $this->analyzeOrder($order);
        if (empty($a['is_paid'])) {
            return $empty;
        }

        $note = trim((string) ($latestNoteOverride ?? $order->workshop_note ?? ''));
        $noteLower = mb_strtolower($note);
        $totalItems = (int) ($a['total_items_count'] ?? 0);
        $completedItems = max(0, $totalItems - (int) ($a['incomplete_items_count'] ?? 0));
        $hasShipmentAny = $order->relationLoaded('shipments')
            ? $order->shipments->isNotEmpty()
            : $order->shipments()->exists();
        $hasDhlLabel = !empty($a['has_printable_label']) || $hasShipmentAny;
        $statusLabel = self::statusLabelDe($status);
        $ordNo = '#' . $order->order_number;

        // 1. Alle physischen Positionen abgehakt + DHL-Label vorhanden, Status aber noch pending/processing
        if ($totalItems > 0 && $completedItems >= $totalItems && empty($a['is_only_digital']) && $hasDhlLabel) {
            return [
                'has_discrepancy' => true,
                'discrepancy_code' => 'ready_for_shipped',
                'suggested_status' => 'shipped',
                'suggested_status_label' => 'Versendet',
                'ui_hint' => "Alle Positionen abgehakt und DHL-Label erstellt, Status steht noch auf „{$statusLabel}“.",
                'ask_alina_prompt' => "Mir ist bei {$ordNo} aufgefallen: Alle Positionen sind abgehakt und das DHL-Label ist bereits erstellt, der Status steht aber noch auf '{$statusLabel}'. Soll ich die Bestellung jetzt für dich auf 'Versendet' umstellen (dann geht auch die Versandmail an den Kunden raus), oder liegt das Paket noch am Packtisch?",
            ];
        }

        // 2. Rein digitale bezahlte Bestellung steht noch auf pending/processing
        if (!empty($a['is_only_digital']) && $status !== 'completed') {
            return [
                'has_discrepancy' => true,
                'discrepancy_code' => 'digital_ready_for_completed',
                'suggested_status' => 'completed',
                'suggested_status_label' => 'Abgeschlossen',
                'ui_hint' => "Bezahlte Digital-Bestellung steht noch auf „{$statusLabel}“.",
                'ask_alina_prompt' => "Mir ist bei {$ordNo} aufgefallen: Es ist eine bezahlte Digital-Bestellung, der Status steht aber noch auf '{$statusLabel}'. Soll ich den Status für dich auf 'Abgeschlossen' stellen?",
            ];
        }

        $isWaitingNote = $note !== '' && (bool) preg_match(
            '/(warte\s+auf|rückfrage|rueckfrage|kundenfoto|foto\s+fehlt|kunde\s+meldet|lieferant|rohling\s+bestellt|rohling\s+fehlt|nachbestell)/iu',
            $noteLower
        );

        // 3. Aktive Bearbeitung läuft (Positionen angearbeitet oder aktiver Werkstatt-Stand), Status steht noch auf 'pending' (Wartend)
        if ($status === 'pending' && ($completedItems > 0 || ($note !== '' && !$isWaitingNote))) {
            return [
                'has_discrepancy' => true,
                'discrepancy_code' => 'active_work_while_pending',
                'suggested_status' => 'processing',
                'suggested_status_label' => 'In Bearbeitung',
                'ui_hint' => 'Fertigung gestartet, Status steht noch auf „Wartend“.',
                'ask_alina_prompt' => "Mir ist aufgefallen, {$ordNo} steht im System noch auf 'Wartend', obwohl du daran arbeitest. Soll ich den Status für dich auf 'In Bearbeitung' anpassen?",
            ];
        }

        // 4. Auftrag wartet auf Kunden-Rückmeldung / Foto / Lieferant, Status steht aber auf 'processing' (In Bearbeitung)
        if ($status === 'processing' && (!empty($a['photo_issue_items']) || $isWaitingNote)) {
            return [
                'has_discrepancy' => true,
                'discrepancy_code' => 'waiting_block_while_processing',
                'suggested_status' => 'pending',
                'suggested_status_label' => 'Wartend',
                'ui_hint' => 'Wartet auf Rückmeldung oder Zulieferung, Status steht aktuell auf „In Bearbeitung“.',
                'ask_alina_prompt' => "Mir ist aufgefallen, bei {$ordNo} wartest du aktuell auf eine Rückmeldung oder Zulieferung, der Status steht aber auf 'In Bearbeitung'. Soll ich ihn solange zurück auf 'Wartend' stellen oder auf 'In Bearbeitung' lassen?",
            ];
        }

        return $empty;
    }

    /**
     * Erzeugt einen Zero-PII Check-in-Snapshot für die aktuell wichtigste offene Bestellung (oder einen gezielten Auftrag).
     * Dient sowohl dem manuellen Werkstatt-Überblick als auch Funkiras 2-Stunden-Rückfrage auf dem Mini-PC.
     *
     * @return array<string, mixed>|null
     */
    public function buildWorkshopCheckinSnapshot(
        ?OrderOrder $specificOrder = null,
        ?\Carbon\CarbonInterface $now = null
    ): ?array {
        $nowRef = ($now ? \Carbon\Carbon::instance($now) : now())->copy();

        $order = $specificOrder;
        $analysis = null;

        if (!$order) {
            $openOrders = OrderOrder::with(['items.product.supplier', 'shipments'])
                ->whereIn('status', ['pending', 'processing'])
                ->get();

            if ($openOrders->isEmpty()) {
                return null;
            }

            $queue = $this->buildSmartQueue($openOrders);
            /** @var OrderOrder|null $order */
            $order = $queue['priority_order'] ?? null;
            if (!$order) {
                return null;
            }
            $analysis = $queue['analyses'][(string) $order->id] ?? null;
        } else {
            $order->loadMissing(['items.product.supplier', 'shipments']);
        }

        $a = $analysis ?? $this->analyzeOrder($order);
        $totalItems = (int) ($a['total_items_count'] ?? 0);
        $completedItems = max(0, $totalItems - (int) ($a['incomplete_items_count'] ?? 0));
        $hasShipmentAny = $order->relationLoaded('shipments')
            ? $order->shipments->isNotEmpty()
            : $order->shipments()->exists();
        $hasDhlLabel = !empty($a['has_printable_label']) || $hasShipmentAny;

        $productParts = [];
        foreach ($order->items->take(3) as $item) {
            $pName = trim((string) ($item->product_name ?: ($item->product->name ?? 'Artikel')));
            $productParts[] = ((int) $item->quantity) . 'x ' . $pName;
        }
        if ($order->items->count() > 3) {
            $productParts[] = '+' . ($order->items->count() - 3) . ' weitere';
        }
        $productSummary = implode(', ', $productParts);

        $note = trim((string) ($order->workshop_note ?? ''));
        $paused = (bool) ($order->workshop_checkin_paused ?? false);
        $discrepancy = $this->evaluateOrderStatusDiscrepancy($order, $a, $note);

        $isOpen = in_array((string) $order->status, ['pending', 'processing'], true);
        $isPaid = !empty($a['is_paid']);
        $ageMinutes = $order->created_at ? (int) $order->created_at->diffInMinutes($nowRef) : 0;
        $initialThresholdMin = $order->is_express ? 10 : 30;

        $isCheckinDue = false;
        $checkinReason = 'none';

        if ($isOpen && $isPaid && !$paused) {
            if ($order->workshop_next_check_at !== null && $nowRef->gte($order->workshop_next_check_at)) {
                $isCheckinDue = true;
                $checkinReason = 'followup_due';
            } elseif ($discrepancy['has_discrepancy'] && ($order->workshop_next_check_at === null || $nowRef->gte($order->workshop_next_check_at))) {
                $isCheckinDue = true;
                $checkinReason = 'status_discrepancy';
            } elseif ($note === '' && $order->workshop_next_check_at === null && $ageMinutes >= $initialThresholdMin) {
                $isCheckinDue = true;
                $checkinReason = 'initial_open';
            }
        }

        $updatedKey = $order->workshop_note_updated_at ? $order->workshop_note_updated_at->format('YmdHis') : 'none';
        $nextKey = $order->workshop_next_check_at ? $order->workshop_next_check_at->format('YmdHis') : 'none';
        $discKey = $discrepancy['discrepancy_code'] ?? 'ok';
        $stateKey = "{$order->order_number}|{$order->status}|{$updatedKey}|{$nextKey}|{$discKey}";

        return [
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'status' => (string) $order->status,
            'status_label_de' => self::statusLabelDe((string) $order->status),
            'payment_status' => (string) $order->payment_status,
            'is_express' => (bool) $order->is_express,
            'product_summary' => $productSummary,
            'items_total' => $totalItems,
            'items_completed' => $completedItems,
            'has_dhl_label' => $hasDhlLabel,
            'stage' => (string) ($a['stage'] ?? ''),
            'workshop_note' => $note,
            'workshop_note_updated_at' => $order->workshop_note_updated_at
                ? $order->workshop_note_updated_at->copy()->timezone('Europe/Berlin')->format('d.m. H:i')
                : null,
            'workshop_next_check_at' => $order->workshop_next_check_at
                ? $order->workshop_next_check_at->copy()->timezone('Europe/Berlin')->format('d.m. H:i')
                : null,
            'workshop_next_check_iso' => $order->workshop_next_check_at
                ? $order->workshop_next_check_at->toIso8601String()
                : null,
            'workshop_checkin_paused' => $paused,
            'is_checkin_due' => $isCheckinDue,
            'checkin_reason' => $checkinReason,
            'has_discrepancy' => (bool) $discrepancy['has_discrepancy'],
            'discrepancy_code' => $discrepancy['discrepancy_code'],
            'suggested_status' => $discrepancy['suggested_status'],
            'suggested_status_label' => $discrepancy['suggested_status_label'],
            'discrepancy_ui_hint' => $discrepancy['ui_hint'],
            'ask_alina_prompt' => $discrepancy['ask_alina_prompt'],
            'checkin_state_key' => $stateKey,
        ];
    }
}
