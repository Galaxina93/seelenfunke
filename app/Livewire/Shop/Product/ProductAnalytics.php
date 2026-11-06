<?php

namespace App\Livewire\Shop\Product;

use Livewire\Attributes\Layout;

use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductAnalyticsEvent;
use App\Models\Product\ProductLoss;
use App\Models\Product\ProductReview;
use App\Models\Product\ProductSupplier;
use App\Models\Product\ProductTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;


#[Layout('components.layouts.backend_layout')]
class ProductAnalytics extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Produkte';
    protected $listeners = ['refreshProductAnalytics' => '$refresh'];

    #[Url]
    public $dateRange = '30';

    #[Url]
    public string $activeTab = 'configurator';

    public $dateFrom;
    public $dateTo;

    // Analytics State (Wirtschaftlichkeit)
    public array $lossData = [];
    public array $topLossData = [];
    public array $reviewData = [];
    public array $supplierData = [];

    // Analytics State (Konfigurator)
    public array $configuratorStats = [];
    public array $configuratorModeData = [];
    public array $topTemplates = [];
    public array $topSymbols = [];
    public array $uploadStats = [];
    public array $expressStats = [];
    public array $addonStats = [];
    public array $addonLeaderboard = [];
    public array $productAddonBreakdown = [];

    // Analytics State (Mengenrechner /calculator)
    public array $calculatorFunnel = [];
    public array $calculatorDropOffs = [];
    public array $calculatorStats = [];

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['economy', 'lucid'], true)) {
            $tab = 'economics';
        }
        if (!in_array($tab, ['configurator', 'calculator', 'economics'], true)) {
            return;
        }
        $this->activeTab = $tab;
        $this->dispatch('analytics-updated');
    }


    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren haben Zugriff auf das Produkt Analytics.');
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren haben Zugriff auf das Produkt Analytics.');
        }

        if (in_array($this->activeTab, ['economy', 'lucid'], true)) {
            $this->activeTab = 'economics';
        }
        if (!in_array($this->activeTab, ['configurator', 'calculator', 'economics'], true)) {
            $this->activeTab = 'configurator';
        }

        $this->updateDateRange();

    }

    public function updatedDateRange()
    {
        $this->updateDateRange();
    }

    private function updateDateRange()
    {
        $range = is_scalar($this->dateRange) ? (string) $this->dateRange : '30';
        if (!in_array($range, ['7', '30', '90', '365', 'all'], true)) {
            $range = '30';
        }
        $this->dateRange = $range;

        if ($this->dateRange === '7') {
            $this->dateFrom = Carbon::now()->subDays(7)->startOfDay();
        } elseif ($this->dateRange === '30') {
            $this->dateFrom = Carbon::now()->subDays(30)->startOfDay();
        } elseif ($this->dateRange === '90') {
            $this->dateFrom = Carbon::now()->subDays(90)->startOfDay();
        } elseif ($this->dateRange === '365') {
            $this->dateFrom = Carbon::now()->subDays(365)->startOfDay();
        } else {
            $this->dateFrom = Carbon::now()->subYears(5)->startOfDay();
        }
        $this->dateTo = Carbon::now()->endOfDay();
    }


    private function computeAnalytics()
    {
        $groupByFormat = in_array($this->dateRange, ['365', 'all']) ? 'Y-m' : 'Y-m-d';

        // 1. Schadensmeldungen Historie (Bar Chart - grouped by date)
        $losses = ProductLoss::whereBetween('created_at', [$this->dateFrom, $this->dateTo])->get();
            
        $lossGrouped = $losses->groupBy(fn($l) => $l->created_at->format($groupByFormat));
        $lossLabels = [];
        $lossData = [];
        foreach ($lossGrouped->sortKeys() as $gDate => $items) {
            $lossLabels[] = $groupByFormat === 'Y-m' 
                ? Carbon::createFromFormat('!Y-m', $gDate)->locale('de')->shortMonthName . ' ' . substr($gDate, 0, 4)
                : Carbon::createFromFormat('!Y-m-d', $gDate)->format('d.m.y');
            $lossData[] = $items->sum('quantity');
        }
        $this->lossData = ['labels' => $lossLabels, 'data' => $lossData];

        // 2. Top 5 Problem Produkte (Bar Chart - grouped by product, sum of cost_value)
        $lossByProduct = $losses->groupBy('product_id')->map(function ($rows) {
            return [
                'name' => $rows->first()->product->name ?? 'Gelöschtes Produkt',
                'cost' => $rows->sum('cost_value') / 100 // Euro
            ];
        })->sortByDesc('cost')->take(5);

        $this->topLossData = [
            'labels' => $lossByProduct->pluck('name')->values()->toArray(),
            'data' => $lossByProduct->pluck('cost')->values()->toArray()
        ];

        // 3. Kundenbewertungen (Doughnut - Stars distribution)
        // Assume ProductReview has an integer rating
        $reviews = ProductReview::whereBetween('created_at', [$this->dateFrom, $this->dateTo])->get();
        $revGrouped = $reviews->groupBy('rating');
        $revLabels = [];
        $revData = [];
        foreach ($revGrouped->sortKeysDesc() as $rating => $items) { // Sort 5,4,3,2,1
            $revLabels[] = ($rating ?: '?') . ' Sterne';
            $revData[] = $items->count();
        }
        $this->reviewData = ['labels' => $revLabels, 'data' => $revData];

        // 4. Lieferanten Risiko (Doughnut - Active Products count by supplier)
        $products = Product::where('status', 'active')->where('type', 'physical')->with('supplier')->get();
        $suppGrouped = $products->groupBy('product_supplier_id');
        $suppLabels = [];
        $suppData = [];
        foreach ($suppGrouped as $sId => $items) {
            $suppName = $items->first()->supplier?->name ?? 'Ohne Lieferant';
            $suppLabels[] = $suppName;
            $suppData[] = $items->count();
        }
        $this->supplierData = ['labels' => $suppLabels, 'data' => $suppData];
    }

    private function computeConfiguratorAnalytics(): void
    {
        // 1. Modus-Verhältnis: Vorlage vs. Selbst konfigurieren
        $modes = ProductAnalyticsEvent::where('source', 'configurator')
            ->where('event_name', 'mode_chosen')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->select('item_identifier', DB::raw('count(*) as count'))
            ->groupBy('item_identifier')
            ->pluck('count', 'item_identifier')
            ->toArray();

        $tplCount = (int) ($modes['template'] ?? 0);
        $customCount = (int) ($modes['custom'] ?? 0);
        $totalModes = $tplCount + $customCount;
        $tplPercent = $totalModes > 0 ? round(($tplCount / $totalModes) * 100, 1) : 0;
        $customPercent = $totalModes > 0 ? round(($customCount / $totalModes) * 100, 1) : 0;

        $this->configuratorModeData = [
            'labels' => ['Vorlage nutzen', 'Selbst konfigurieren'],
            'data' => [$tplCount, $customCount],
            'template_count' => $tplCount,
            'custom_count' => $customCount,
            'template_percent' => $tplPercent,
            'custom_percent' => $customPercent,
            'total' => $totalModes,
        ];

        // 2. Top-Vorlagen (Welche Vorlage wie oft gewählt)
        $topTplRows = ProductAnalyticsEvent::where('source', 'configurator')
            ->where('event_name', 'template_selected')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->select('template_id', 'item_identifier', DB::raw('count(*) as count'))
            ->groupBy('template_id', 'item_identifier')
            ->orderByDesc('count')
            ->take(10)
            ->get();

        $templateIds = $topTplRows->pluck('template_id')->filter()->unique()->toArray();
        $dbTemplates = !empty($templateIds) ? ProductTemplate::with('product')->whereIn('id', $templateIds)->get()->keyBy('id') : collect();

        $this->topTemplates = $topTplRows->map(function ($row) use ($dbTemplates) {
            $tpl = $dbTemplates[$row->template_id] ?? null;
            return [
                'id' => $row->template_id,
                'name' => $tpl?->name ?? $row->item_identifier ?? 'Unbenannte Vorlage',
                'product_name' => $tpl?->product?->name ?? '-',
                'preview_image' => $tpl?->preview_image_url ?? null,
                'count' => (int) $row->count,
            ];
        })->toArray();

        // 3. Top-Symbole & Gravurmotive
        $topSymbolRows = ProductAnalyticsEvent::where('source', 'configurator')
            ->where('event_name', 'symbol_added')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->select('item_identifier', DB::raw('count(*) as count'))
            ->groupBy('item_identifier')
            ->orderByDesc('count')
            ->take(12)
            ->get();

        $this->topSymbols = $topSymbolRows->map(function ($row) {
            $filename = (string) $row->item_identifier;
            $cleanName = pathinfo($filename, PATHINFO_FILENAME);
            $cleanName = str_replace(['_black', '_', '-'], [' ', ' ', ' '], $cleanName);
            return [
                'file' => $filename,
                'name' => ucwords($cleanName),
                'count' => (int) $row->count,
            ];
        })->toArray();

        $totalSymbolsCount = ProductAnalyticsEvent::where('source', 'configurator')
            ->where('event_name', 'symbol_added')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->count();

        // 4. Datei-Uploads & Dateiformate
        $uploadRows = ProductAnalyticsEvent::where('source', 'configurator')
            ->where('event_name', 'file_uploaded')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->select('item_identifier', DB::raw('count(*) as count'))
            ->groupBy('item_identifier')
            ->pluck('count', 'item_identifier')
            ->toArray();

        $uploadErrorCount = ProductAnalyticsEvent::where('source', 'configurator')
            ->where('event_name', 'file_upload_error')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->count();

        $totalUploads = array_sum($uploadRows);

        $this->uploadStats = [
            'total' => $totalUploads,
            'errors' => $uploadErrorCount,
            'by_format' => [
                'png' => (int) ($uploadRows['png'] ?? 0),
                'svg' => (int) ($uploadRows['svg'] ?? 0),
                'jpg' => (int) (($uploadRows['jpg'] ?? 0) + ($uploadRows['jpeg'] ?? 0)),
                'pdf' => (int) ($uploadRows['pdf'] ?? 0),
                'webp' => (int) ($uploadRows['webp'] ?? 0),
            ],
            'chart_labels' => ['PNG', 'SVG', 'JPG/JPEG', 'PDF', 'WEBP'],
            'chart_data' => [
                (int) ($uploadRows['png'] ?? 0),
                (int) ($uploadRows['svg'] ?? 0),
                (int) (($uploadRows['jpg'] ?? 0) + ($uploadRows['jpeg'] ?? 0)),
                (int) ($uploadRows['pdf'] ?? 0),
                (int) ($uploadRows['webp'] ?? 0),
            ],
        ];

        // 5. Express-Zuschlag "Eiliges Geschenk"
        $expressToggledCount = ProductAnalyticsEvent::where('source', 'configurator')
            ->where('event_name', 'express_toggled')
            ->where('item_identifier', 'enabled')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->count();

        $totalConfigsSaved = ProductAnalyticsEvent::where('source', 'configurator')
            ->where('event_name', 'configurator_saved')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->count();

        $expressSavedEvents = ProductAnalyticsEvent::where('source', 'configurator')
            ->where('event_name', 'configurator_saved')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->get();

        $expressSavedCount = $expressSavedEvents->filter(function ($e) {
            return !empty($e->meta_data['is_express']);
        })->count();

        $expressRevenueCents = ProductAnalyticsEvent::where('source', 'configurator')
            ->where('event_name', 'express_toggled')
            ->where('item_identifier', 'enabled')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->sum('value_cents');

        $expressAttachRate = $totalConfigsSaved > 0 ? round(($expressSavedCount / $totalConfigsSaved) * 100, 1) : 0;

        $this->expressStats = [
            'enabled_clicks' => $expressToggledCount,
            'completed_with_express' => $expressSavedCount,
            'total_completed' => $totalConfigsSaved,
            'attach_rate' => $expressAttachRate,
            'revenue_euros' => round($expressRevenueCents / 100, 2),
        ];

        // 6. Aufpreis-Extras, Verpackungs-Upgrades & Bundle-Optionen (Universelle Auswertung)
        $this->computeAddonAnalytics($expressSavedEvents, $totalConfigsSaved);

        // 7. Gesamt-Performance & Conversion Funnel
        $totalViews = ProductAnalyticsEvent::where('source', 'configurator')
            ->where('event_name', 'configurator_view')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->count();

        $totalAbandoned = ProductAnalyticsEvent::where('source', 'configurator')
            ->where('event_name', 'configurator_abandoned')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->count();

        $conversionRate = $totalViews > 0 ? round(($totalConfigsSaved / $totalViews) * 100, 1) : 0;

        $this->configuratorStats = [
            'views' => $totalViews,
            'modes_chosen' => $totalModes,
            'symbols_used' => $totalSymbolsCount,
            'uploads' => $totalUploads,
            'saved' => $totalConfigsSaved,
            'abandoned' => $totalAbandoned,
            'conversion_rate' => $conversionRate,
        ];
    }

    /**
     * Kanonisiert den Schlüssel eines beliebigen Aufpreis-Extras / Verpackungs-Upgrades / Bundle-Artikels,
     * sodass identische Extras produktübergreifend zusammengefasst werden können, während auch jedes
     * individuell neu angelegte Produkt-Extra automatisch und universell erfasst wird.
     */
    private static function resolveCanonicalAddonKey(array $addon): string
    {
        $rawId = strtolower(trim((string) ($addon['id'] ?? '')));
        $rawName = mb_strtolower(trim((string) ($addon['name'] ?? '')));

        if (
            $rawId === 'gift_box_westpack'
            || str_contains($rawName, 'schmuckbox')
            || str_contains($rawName, 'geschenkbox')
            || str_contains($rawName, 'schmuckschachtel')
            || str_contains($rawName, 'juwelierschachtel')
            || str_contains($rawName, 'edle box')
        ) {
            return 'gift_box_westpack';
        }

        if (
            $rawId === 'gift_wrap_black_gold'
            || str_contains($rawName, 'geschenkpapier')
            || str_contains($rawName, 'geschenkverpackung')
        ) {
            return 'gift_wrap_black_gold';
        }

        if ($rawName !== '') {
            return 'custom_' . md5($rawName);
        }

        return $rawId !== '' ? ('id_' . $rawId) : ('unknown_' . md5(json_encode($addon)));
    }

    /**
     * Universelle Auswertung aller Aufpreis-Extras, Verpackungs-Upgrades & Bundle-Optionen
     * über alle physischen Produkte, Konfigurator-Interaktionen und echten Bestellungen hinweg.
     */
    private function computeAddonAnalytics($savedConfiguratorEvents, int $totalConfigsSaved): void
    {
        $physicalProducts = Product::where('status', 'active')
            ->where('type', 'physical')
            ->get();

        $productsById = $physicalProducts->keyBy('id');
        $globalAddons = [];
        $productMap = [];

        $initAddonEntry = function (array $addon, string $key): array {
            $priceCents = isset($addon['price_cents'])
                ? max(0, (int) $addon['price_cents'])
                : max(0, (int) round(((float) str_replace(',', '.', (string) ($addon['price'] ?? 0))) * 100));

            $imagePath = $addon['image_path'] ?? Product::resolveAddonImagePath($addon);
            $imageUrl = $addon['image_url'] ?? Product::resolveAddonImageUrl($addon);

            return [
                'key' => $key,
                'id' => (string) ($addon['id'] ?? $key),
                'name' => trim((string) ($addon['name'] ?? 'Zusatz-Option')),
                'description' => trim((string) ($addon['description'] ?? '')),
                'price_cents' => $priceCents,
                'price_euros' => round($priceCents / 100, 2),
                'image_path' => $imagePath,
                'image_url' => $imageUrl,
                'is_active' => (bool) ($addon['is_active'] ?? true),
                'products' => [],
                'enabled_clicks' => 0,
                'cart_saves' => 0,
                'cart_units' => 0,
                'cart_revenue_cents' => 0,
                'ordered_positions' => 0,
                'ordered_units' => 0,
                'ordered_revenue_cents' => 0,
            ];
        };

        // 1. Alle aktiv konfigurierten Extras sämtlicher physischer Produkte registrieren
        foreach ($physicalProducts as $product) {
            $effectiveAddons = $product->getEffectiveAddons(false);
            $productMap[$product->id] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'orders_count' => 0,
                'ordered_units' => 0,
                'configs_saved' => 0,
                'addons' => [],
            ];

            foreach ($effectiveAddons as $addon) {
                if (!is_array($addon) || empty($addon['name'])) {
                    continue;
                }
                $key = self::resolveCanonicalAddonKey($addon);

                if (!isset($globalAddons[$key])) {
                    $globalAddons[$key] = $initAddonEntry($addon, $key);
                }
                if (!in_array($product->name, $globalAddons[$key]['products'], true)) {
                    $globalAddons[$key]['products'][] = $product->name;
                }

                $productMap[$product->id]['addons'][$key] = $initAddonEntry($addon, $key);
            }
        }

        // 2. Echte Bestellpositionen (OrderOrderItem) im gewählten Zeitraum auswerten
        $orderItems = OrderOrderItem::with(['order', 'product'])
            ->whereHas('order', function ($q) {
                $q->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
                  ->whereNotIn('status', ['cancelled', 'refunded', 'draft']);
            })
            ->get();

        $totalPhysicalOrderPositions = 0;
        $orderPositionsWithAddons = 0;

        foreach ($orderItems as $item) {
            $conf = is_array($item->configuration) ? $item->configuration : [];
            $isPhysicalItem = ($item->product && $item->product->type === 'physical')
                || (($conf['type'] ?? 'physical') === 'physical' && empty($conf['is_digital']));

            if (!$isPhysicalItem) {
                continue;
            }

            $totalPhysicalOrderPositions++;
            $qty = max(1, (int) ($item->quantity ?? 1));
            $pid = $item->product_id;

            if ($pid && isset($productMap[$pid])) {
                $productMap[$pid]['orders_count']++;
                $productMap[$pid]['ordered_units'] += $qty;
            }

            $bookedAddons = [];
            if (!empty($conf['addons_summary']) && is_array($conf['addons_summary'])) {
                $bookedAddons = $conf['addons_summary'];
            } elseif (!empty($conf['selected_addons']) && is_array($conf['selected_addons']) && $item->product) {
                $selectedIds = array_map(fn ($x) => is_array($x) ? (string) ($x['id'] ?? '') : (string) $x, $conf['selected_addons']);
                foreach ($item->product->getEffectiveAddons(false) as $eff) {
                    if (in_array((string) ($eff['id'] ?? ''), $selectedIds, true)) {
                        $bookedAddons[] = $eff;
                    }
                }
            }

            if (!empty($bookedAddons)) {
                $orderPositionsWithAddons++;
                foreach ($bookedAddons as $bAddon) {
                    if (!is_array($bAddon) || empty($bAddon['name'])) {
                        continue;
                    }
                    $key = self::resolveCanonicalAddonKey($bAddon);
                    $unitCents = max(0, (int) ($bAddon['price_cents'] ?? 0));

                    if (!isset($globalAddons[$key])) {
                        $globalAddons[$key] = $initAddonEntry($bAddon, $key);
                    }
                    if ($item->product && !in_array($item->product->name, $globalAddons[$key]['products'], true)) {
                        $globalAddons[$key]['products'][] = $item->product->name;
                    }

                    $globalAddons[$key]['ordered_positions']++;
                    $globalAddons[$key]['ordered_units'] += $qty;
                    $globalAddons[$key]['ordered_revenue_cents'] += ($unitCents * $qty);

                    if ($pid && isset($productMap[$pid])) {
                        if (!isset($productMap[$pid]['addons'][$key])) {
                            $productMap[$pid]['addons'][$key] = $initAddonEntry($bAddon, $key);
                        }
                        $productMap[$pid]['addons'][$key]['ordered_positions']++;
                        $productMap[$pid]['addons'][$key]['ordered_units'] += $qty;
                        $productMap[$pid]['addons'][$key]['ordered_revenue_cents'] += ($unitCents * $qty);
                    }
                }
            }
        }

        // 3. Konfigurator- & Rechner-Klicks (addon_toggled) im gewählten Zeitraum auswerten
        $toggleEvents = ProductAnalyticsEvent::whereIn('source', ['configurator', 'calculator'])
            ->where('event_name', 'addon_toggled')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->get();

        foreach ($toggleEvents as $ev) {
            $meta = is_array($ev->meta_data) ? $ev->meta_data : [];
            $isEnabled = filter_var($meta['enabled'] ?? ($ev->value_cents > 0), FILTER_VALIDATE_BOOLEAN);
            if (!$isEnabled) {
                continue;
            }

            $addonStub = [
                'id' => (string) ($meta['addon_id'] ?? $ev->item_identifier ?? ''),
                'name' => (string) ($meta['addon_name'] ?? $ev->item_identifier ?? 'Zusatz-Extra'),
                'price_cents' => max(0, (int) ($meta['price_cents'] ?? $ev->value_cents ?? 0)),
                'image_path' => $meta['image_path'] ?? null,
            ];

            $key = self::resolveCanonicalAddonKey($addonStub);
            if (!isset($globalAddons[$key])) {
                $globalAddons[$key] = $initAddonEntry($addonStub, $key);
            }

            $globalAddons[$key]['enabled_clicks']++;

            $pid = $ev->product_id;
            if ($pid && isset($productMap[$pid])) {
                if (!isset($productMap[$pid]['addons'][$key])) {
                    $productMap[$pid]['addons'][$key] = $initAddonEntry($addonStub, $key);
                }
                $productMap[$pid]['addons'][$key]['enabled_clicks']++;
            }
        }

        // 4. Konfigurator-Abschlüsse (configurator_saved -> Warenkorb-Übergaben) auswerten
        $configsWithAddons = 0;
        foreach ($savedConfiguratorEvents as $ev) {
            $meta = is_array($ev->meta_data) ? $ev->meta_data : [];
            $pid = $ev->product_id;
            $qty = max(1, (int) ($meta['qty'] ?? 1));

            if ($pid && isset($productMap[$pid])) {
                $productMap[$pid]['configs_saved']++;
            }

            $savedAddons = [];
            if (!empty($meta['addons_summary']) && is_array($meta['addons_summary'])) {
                $savedAddons = $meta['addons_summary'];
            } elseif (!empty($meta['selected_addons']) && is_array($meta['selected_addons']) && $pid && isset($productsById[$pid])) {
                $selectedIds = array_map(fn ($x) => is_array($x) ? (string) ($x['id'] ?? '') : (string) $x, $meta['selected_addons']);
                foreach ($productsById[$pid]->getEffectiveAddons(false) as $eff) {
                    if (in_array((string) ($eff['id'] ?? ''), $selectedIds, true)) {
                        $savedAddons[] = $eff;
                    }
                }
            }

            if (!empty($savedAddons)) {
                $configsWithAddons++;
                foreach ($savedAddons as $sAddon) {
                    if (!is_array($sAddon) || empty($sAddon['name'])) {
                        continue;
                    }
                    $key = self::resolveCanonicalAddonKey($sAddon);
                    $unitCents = max(0, (int) ($sAddon['price_cents'] ?? 0));

                    if (!isset($globalAddons[$key])) {
                        $globalAddons[$key] = $initAddonEntry($sAddon, $key);
                    }

                    $globalAddons[$key]['cart_saves']++;
                    $globalAddons[$key]['cart_units'] += $qty;
                    $globalAddons[$key]['cart_revenue_cents'] += ($unitCents * $qty);

                    if ($pid && isset($productMap[$pid])) {
                        if (!isset($productMap[$pid]['addons'][$key])) {
                            $productMap[$pid]['addons'][$key] = $initAddonEntry($sAddon, $key);
                        }
                        $productMap[$pid]['addons'][$key]['cart_saves']++;
                        $productMap[$pid]['addons'][$key]['cart_units'] += $qty;
                        $productMap[$pid]['addons'][$key]['cart_revenue_cents'] += ($unitCents * $qty);
                    }
                }
            }
        }

        // 5. Kennzahlen & Quoten berechnen
        $totalOrderedUnits = array_sum(array_column($globalAddons, 'ordered_units'));
        $totalOrderedRevenueCents = array_sum(array_column($globalAddons, 'ordered_revenue_cents'));
        $totalCartUnits = array_sum(array_column($globalAddons, 'cart_units'));
        $totalCartRevenueCents = array_sum(array_column($globalAddons, 'cart_revenue_cents'));
        $totalEnabledClicks = array_sum(array_column($globalAddons, 'enabled_clicks'));

        $totalEffectiveBookings = $totalOrderedUnits > 0 ? $totalOrderedUnits : ($totalCartUnits > 0 ? $totalCartUnits : $totalEnabledClicks);
        $baseDenominator = $totalPhysicalOrderPositions > 0 ? $totalPhysicalOrderPositions : $totalConfigsSaved;

        $leaderboard = [];
        foreach ($globalAddons as $key => $row) {
            $effectiveUnits = $row['ordered_units'] > 0
                ? $row['ordered_units']
                : ($row['cart_units'] > 0 ? $row['cart_units'] : $row['enabled_clicks']);
            $effectiveRevenueCents = $row['ordered_revenue_cents'] > 0
                ? $row['ordered_revenue_cents']
                : $row['cart_revenue_cents'];
            $positionsBooked = $row['ordered_positions'] > 0
                ? $row['ordered_positions']
                : $row['cart_saves'];

            $row['products_count'] = count($row['products']);
            $row['effective_units'] = $effectiveUnits;
            $row['effective_revenue_euros'] = round($effectiveRevenueCents / 100, 2);
            $row['ordered_revenue_euros'] = round($row['ordered_revenue_cents'] / 100, 2);
            $row['cart_revenue_euros'] = round($row['cart_revenue_cents'] / 100, 2);
            $row['share_percent'] = $totalEffectiveBookings > 0
                ? round(($effectiveUnits / $totalEffectiveBookings) * 100, 1)
                : 0;
            $row['attach_rate'] = $baseDenominator > 0
                ? round(($positionsBooked / $baseDenominator) * 100, 1)
                : 0;

            $leaderboard[] = $row;
        }

        usort($leaderboard, function (array $a, array $b) {
            return ($b['ordered_units'] <=> $a['ordered_units'])
                ?: ($b['cart_units'] <=> $a['cart_units'])
                ?: ($b['enabled_clicks'] <=> $a['enabled_clicks'])
                ?: ($b['price_cents'] <=> $a['price_cents']);
        });

        $this->addonLeaderboard = $leaderboard;

        // 6. Produkt-spezifische Aufschlüsselung aufbereiten
        $productBreakdown = [];
        foreach ($productMap as $pid => $pRow) {
            $pDenominator = $pRow['orders_count'] > 0 ? $pRow['orders_count'] : $pRow['configs_saved'];
            $pAddons = [];
            $pTotalBooked = 0;
            $pTotalRevenueCents = 0;

            foreach ($pRow['addons'] as $aKey => $aRow) {
                $effUnits = $aRow['ordered_units'] > 0
                    ? $aRow['ordered_units']
                    : ($aRow['cart_units'] > 0 ? $aRow['cart_units'] : $aRow['enabled_clicks']);
                $effRevCents = $aRow['ordered_revenue_cents'] > 0
                    ? $aRow['ordered_revenue_cents']
                    : $aRow['cart_revenue_cents'];
                $posBooked = $aRow['ordered_positions'] > 0
                    ? $aRow['ordered_positions']
                    : $aRow['cart_saves'];

                $pTotalBooked += $effUnits;
                $pTotalRevenueCents += $effRevCents;

                $aRow['effective_units'] = $effUnits;
                $aRow['effective_revenue_euros'] = round($effRevCents / 100, 2);
                $aRow['ordered_revenue_euros'] = round($aRow['ordered_revenue_cents'] / 100, 2);
                $aRow['cart_revenue_euros'] = round($aRow['cart_revenue_cents'] / 100, 2);
                $aRow['attach_rate'] = $pDenominator > 0 ? round(($posBooked / $pDenominator) * 100, 1) : 0;
                $pAddons[] = $aRow;
            }

            usort($pAddons, fn (array $a, array $b) => ($b['effective_units'] <=> $a['effective_units']) ?: ($b['price_cents'] <=> $a['price_cents']));

            $pRow['addons'] = $pAddons;
            $pRow['total_booked_units'] = $pTotalBooked;
            $pRow['total_revenue_euros'] = round($pTotalRevenueCents / 100, 2);
            $productBreakdown[] = $pRow;
        }

        usort($productBreakdown, fn (array $a, array $b) => ($b['total_booked_units'] <=> $a['total_booked_units']) ?: strcmp($a['product_name'], $b['product_name']));
        $this->productAddonBreakdown = $productBreakdown;

        $orderAttachRate = $totalPhysicalOrderPositions > 0
            ? round(($orderPositionsWithAddons / $totalPhysicalOrderPositions) * 100, 1)
            : 0;
        $configAttachRate = $totalConfigsSaved > 0
            ? round(($configsWithAddons / $totalConfigsSaved) * 100, 1)
            : 0;

        $effectiveAttachRate = $totalPhysicalOrderPositions > 0 && ($orderPositionsWithAddons > 0 || $totalConfigsSaved === 0)
            ? $orderAttachRate
            : $configAttachRate;

        $effectiveRevenueCents = $totalOrderedRevenueCents > 0 ? $totalOrderedRevenueCents : $totalCartRevenueCents;
        $effectiveBookedCount = $totalOrderedUnits > 0 ? $totalOrderedUnits : $totalCartUnits;

        $this->addonStats = [
            'active_options_count' => count($globalAddons),
            'products_with_addons_count' => count($productBreakdown),
            'enabled_clicks' => $totalEnabledClicks,
            'configs_with_addons' => $configsWithAddons,
            'cart_units' => $totalCartUnits,
            'cart_revenue_euros' => round($totalCartRevenueCents / 100, 2),
            'orders_with_addons' => $orderPositionsWithAddons,
            'total_physical_orders' => $totalPhysicalOrderPositions,
            'ordered_units' => $totalOrderedUnits,
            'ordered_revenue_euros' => round($totalOrderedRevenueCents / 100, 2),
            'total_booked_count' => $effectiveBookedCount,
            'total_revenue_euros' => round($effectiveRevenueCents / 100, 2),
            'attach_rate' => $effectiveAttachRate,
            'order_attach_rate' => $orderAttachRate,
            'config_attach_rate' => $configAttachRate,
        ];
    }

    private function computeCalculatorAnalytics(): void
    {
        $views = ProductAnalyticsEvent::where('source', 'calculator')
            ->where('event_name', 'calc_view')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->count();

        $started = ProductAnalyticsEvent::where('source', 'calculator')
            ->where('event_name', 'calc_started')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->count();

        $itemsSaved = ProductAnalyticsEvent::where('source', 'calculator')
            ->where('event_name', 'calc_item_saved')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->count();

        $formReached = ProductAnalyticsEvent::where('source', 'calculator')
            ->where('event_name', 'calc_form_reached')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->count();

        $quotes = ProductAnalyticsEvent::where('source', 'calculator')
            ->where('event_name', 'calc_quote_submitted')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->get();

        $quotesCount = $quotes->count();
        $quotesTotalGrossCents = $quotes->sum('value_cents');
        $avgQuoteValue = $quotesCount > 0 ? round(($quotesTotalGrossCents / 100) / $quotesCount, 2) : 0;

        // Drop-off rates between stages
        $dropOff0to1 = $views > 0 ? round(max(0, ($views - $started) / $views) * 100, 1) : 0;
        $dropOff1to2 = $started > 0 ? round(max(0, ($started - $itemsSaved) / $started) * 100, 1) : 0;
        $dropOff2to3 = $itemsSaved > 0 ? round(max(0, ($itemsSaved - $formReached) / $itemsSaved) * 100, 1) : 0;
        $dropOff3to4 = $formReached > 0 ? round(max(0, ($formReached - $quotesCount) / $formReached) * 100, 1) : 0;

        $validationErrors = ProductAnalyticsEvent::where('source', 'calculator')
            ->where('event_name', 'calc_validation_failed')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->count();

        $canceledItems = ProductAnalyticsEvent::where('source', 'calculator')
            ->where('event_name', 'calc_item_canceled')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->count();

        $this->calculatorFunnel = [
            'labels' => ['1. Aufruf', '2. Gestartet (AGB)', '3. Artikel fertig', '4. Formular erreicht', '5. Anfrage versendet'],
            'data' => [$views, $started, $itemsSaved, $formReached, $quotesCount],
            'views' => $views,
            'started' => $started,
            'items_saved' => $itemsSaved,
            'form_reached' => $formReached,
            'quotes_count' => $quotesCount,
        ];

        $this->calculatorDropOffs = [
            'step0_to_1' => $dropOff0to1,
            'step1_to_2' => $dropOff1to2,
            'step2_to_3' => $dropOff2to3,
            'step3_to_4' => $dropOff3to4,
            'validation_errors' => $validationErrors,
            'canceled_items' => $canceledItems,
        ];

        $this->calculatorStats = [
            'quotes_count' => $quotesCount,
            'total_volume_euros' => round($quotesTotalGrossCents / 100, 2),
            'avg_quote_euros' => $avgQuoteValue,
            'overall_conversion' => $views > 0 ? round(($quotesCount / $views) * 100, 1) : 0,
        ];
    }

    public static function getCombinedAnalyticsData()
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        // Sales der letzten 30 Tage pro Produkt holen
        $recentSales = OrderOrderItem::whereHas('order', function ($query) use ($thirtyDaysAgo) {
            $query->where('created_at', '>=', $thirtyDaysAgo)
                  ->whereNotIn('status', ['cancelled', 'refunded', 'draft']);
        })
        ->select('product_id', DB::raw('SUM(quantity) as total_sold'))
        ->groupBy('product_id')
        ->pluck('total_sold', 'product_id')
        ->toArray();

        $products = Product::with('supplier')
            ->where('status', 'active')
            ->where('type', 'physical')
            ->get()
            ->map(function ($product) use ($recentSales) {
                // Marge & Costs logic
                $netPrice = $product->net_price;
                $purchase = $product->purchase_price ?? 0;
                $laserRuntime = $product->laser_runtime_minutes ?? 0;
                $wearFactor = $product->electricity_wear_factor > 0 ? $product->electricity_wear_factor : 1;
                $laserCost = $laserRuntime * $wearFactor;
                $packaging = $product->packaging_cost ?? 0;
                $shipping = $product->shipping_cost > 0 ? $product->shipping_cost : (int) shop_setting('shipping_cost', 490);
                
                $marketingPercent = $product->marketing_cost_percent ?? 15.00;
                $marketingCost = $netPrice > 0 ? (int) round($netPrice * ($marketingPercent / 100)) : 0;

                $rawStripePercent = str_replace(',', '.', trim((string) shop_setting('stripe_fee_percent', 2.50)));
                $stripeFeePercent = max(0.0, (float) $rawStripePercent);
                $rawStripeFixed = str_replace(',', '.', trim((string) shop_setting('stripe_fee_fixed_cents', 30)));
                $stripeFixedFloat = (float) $rawStripeFixed;
                $stripeFeeFixedCents = str_contains($rawStripeFixed, '.')
                    ? max(0, (int) round($stripeFixedFloat * 100))
                    : max(0, (int) round($stripeFixedFloat));
                $paymentCost = $product->calculatePaymentFeeCents();

                $totalCost = $purchase + $laserCost + $packaging + $shipping + $marketingCost + $paymentCost;
                $netMargin = $netPrice - $totalCost;
                $marginPercent = $netPrice > 0 ? round(($netMargin / $netPrice) * 100, 1) : 0;

                // Forecasting logic
                $soldLast30 = $recentSales[$product->id] ?? 0;
                $velocityPerDay = $soldLast30 / 30;
                $stock = $product->quantity;
                
                // Fetch dynamic lead time from the attached supplier if present, fallback to product setting, fallback to 14
                if ($product->supplier) {
                    $shipMethod = in_array($product->supplier->shipping_method, ['land', 'air', 'sea', 'train'], true)
                        ? $product->supplier->shipping_method
                        : 'land';
                    $daysField = "lead_time_{$shipMethod}_days";
                    $deliveryDays = $product->supplier->{$daysField} ?? ($product->delivery_time_days ?? 14);
                } else {
                    $deliveryDays = $product->delivery_time_days ?? 14;
                }
                
                $reachDays = $velocityPerDay > 0 ? round($stock / $velocityPerDay) : '∞';
                
                $lowStockThreshold = (int) shop_setting('inventory_low_stock_threshold', 5);
                $status = 'ok';
                if ($stock <= 0) {
                    $status = 'out_of_stock';
                } elseif ($reachDays !== '∞' && $reachDays <= $deliveryDays) {
                    $status = 'critical';
                } elseif (($reachDays !== '∞' && $reachDays <= ($deliveryDays + 7)) || $stock <= $lowStockThreshold) {
                    $status = 'warning';
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'type' => $product->type,
                    'brand' => $product->brand,
                    'product_status' => $product->status,
                    'short_description' => $product->short_description,
                    'price' => $product->gross_price / 100,
                    'net_price' => $netPrice / 100,
                    'purchase_price' => $purchase / 100,
                    'laser_cost' => $laserCost / 100,
                    'packaging_cost' => $packaging / 100,
                    'shipping_cost' => $shipping / 100,
                    'marketing_cost' => $marketingCost / 100,
                    'marketing_percent' => $marketingPercent,
                    'payment_cost' => $paymentCost / 100,
                    'payment_fee_percent' => $stripeFeePercent,
                    'payment_fee_fixed' => $stripeFeeFixedCents / 100,
                    'total_cost' => $totalCost / 100,
                    'net_margin' => $netMargin / 100,
                    'margin_percent' => $marginPercent,
                    
                    'stock' => $stock,
                    'sold_last_30' => $soldLast30,
                    'velocity' => round($velocityPerDay, 2),
                    'reach_days' => $reachDays,
                    'delivery_days' => $deliveryDays,
                    'status' => $status,
                    'supplier_name' => $product->supplier ? $product->supplier->name : '-',
                    'supplier' => $product->supplier ? collect($product->supplier->toArray())->except(['created_at', 'updated_at'])->toArray() : null,
                    'weight' => $product->weight,
                    'packaging_weight' => $product->packaging_weight,
                    'width' => $product->width,
                    'height' => $product->height,
                    'length' => $product->length,
                    'shipping_class' => $product->shipping_class,
                ];
            });

        return $products->sortByDesc('margin_percent')->values();
    }

    public static function getLucidData()
    {
        $currentYear = Carbon::now()->year;

        // Sales des aktuellen Jahres (wir summieren einfach anhand der getätigten Käufe * Gewicht)
        $salesThisYear = OrderOrderItem::whereHas('order', function ($query) use ($currentYear) {
            $query->whereYear('created_at', $currentYear)
                  ->whereNotIn('status', ['cancelled', 'refunded', 'draft']);
        })
        ->select('product_id', DB::raw('SUM(quantity) as total_sold'))
        ->groupBy('product_id')
        ->pluck('total_sold', 'product_id')
        ->toArray();

        $totals = [
            'paper' => 0, 'plastic' => 0, 'glass' => 0, 'wood' => 0, 
            'tin' => 0, 'alu' => 0, 'composite' => 0, 'other' => 0
        ];

        $productDetails = collect();

        $products = Product::with('packagings')->whereIn('id', array_keys($salesThisYear))->get();

        foreach ($products as $product) {
            $sold = $salesThisYear[$product->id] ?? 0;
            $productPackagingSum = 0;
            
            $pData = [
                'name' => $product->name,
                'sold' => $sold,
            ];

            foreach (array_keys($totals) as $type) {
                $grams = $product->packagings->where('material_type', $type)->sum('weight_grams') * $sold;
                $totals[$type] += $grams;
                $pData[$type . '_kg'] = $grams / 1000;
                $productPackagingSum += $grams;
            }

            if ($productPackagingSum > 0) {
                $productDetails->push($pData);
            }
        }

        return [
            'year' => $currentYear,
            'totals_kg' => collect($totals)->map(fn($g) => $g / 1000)->toArray(),
            'details' => $productDetails->sortByDesc('paper_kg')->values(),
        ];
    }

    public function downloadFullReport(\App\Services\Export\FileDownloadService $exportService)
    {
        return $exportService->downloadProductAnalyticsFullReportPdf();
    }

    public function downloadLucidReport(\App\Services\Export\FileDownloadService $exportService)
    {
        return $exportService->downloadProductAnalyticsLucidPdf();
    }

    public function render()
    {
        $this->computeAnalytics();
        $this->computeConfiguratorAnalytics();
        $this->computeCalculatorAnalytics();
        $this->dispatch('analytics-updated');

        return view('livewire.shop.product.product-analytics', [
            'combinedData' => self::getCombinedAnalyticsData(),
            'lucidData' => self::getLucidData(),
        ]);
    }
}
