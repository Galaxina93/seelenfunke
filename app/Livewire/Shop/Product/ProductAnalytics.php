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

    // Analytics State (Mengenrechner /calculator)
    public array $calculatorFunnel = [];
    public array $calculatorDropOffs = [];
    public array $calculatorStats = [];

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->dispatch('analytics-updated');
    }


    public function mount()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Nur Administratoren haben Zugriff auf das Produkt Analytics.');
        }

        $this->updateDateRange();

    }

    public function updatedDateRange()
    {
        $this->updateDateRange();
    }

    private function updateDateRange()
    {
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
                ? Carbon::createFromFormat('Y-m', $gDate)->locale('de')->shortMonthName . ' ' . substr($gDate, 0, 4)
                : Carbon::createFromFormat('Y-m-d', $gDate)->format('d.m.y');
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

        // 6. Gesamt-Performance & Conversion Funnel
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
                  ->where('status', '!=', 'cancelled')
                  ->where('status', '!=', 'draft');
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

                $totalCost = $purchase + $laserCost + $packaging + $shipping + $marketingCost;
                $netMargin = $netPrice - $totalCost;
                $marginPercent = $netPrice > 0 ? round(($netMargin / $netPrice) * 100, 1) : 0;

                // Forecasting logic
                $soldLast30 = $recentSales[$product->id] ?? 0;
                $velocityPerDay = $soldLast30 / 30;
                $stock = $product->quantity;
                
                // Fetch dynamic lead time from the attached supplier if present, fallback to product setting, fallback to 14
                if ($product->supplier) {
                    $shipMethod = $product->supplier->shipping_method ?? 'land';
                    $daysField = "lead_time_{$shipMethod}_days";
                    $deliveryDays = $product->supplier->{$daysField} ?? ($product->delivery_time_days ?? 14);
                } else {
                    $deliveryDays = $product->delivery_time_days ?? 14;
                }
                
                $reachDays = $velocityPerDay > 0 ? round($stock / $velocityPerDay) : '∞';
                
                $status = 'ok';
                if ($stock <= 0) {
                    $status = 'out_of_stock';
                } elseif ($reachDays !== '∞' && $reachDays <= $deliveryDays) {
                    $status = 'critical';
                } elseif ($reachDays !== '∞' && $reachDays <= ($deliveryDays + 7)) {
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
                    'price' => $product->price / 100,
                    'net_price' => $netPrice / 100,
                    'purchase_price' => $purchase / 100,
                    'laser_cost' => $laserCost / 100,
                    'packaging_cost' => $packaging / 100,
                    'shipping_cost' => $shipping / 100,
                    'marketing_cost' => $marketingCost / 100,
                    'marketing_percent' => $marketingPercent,
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
                  ->where('status', '!=', 'cancelled')
                  ->where('status', '!=', 'draft');
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
