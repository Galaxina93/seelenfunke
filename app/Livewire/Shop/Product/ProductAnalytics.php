<?php

namespace App\Livewire\Shop\Product;

use Livewire\Attributes\Layout;

use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductLoss;
use App\Models\Product\ProductReview;
use App\Models\Product\ProductSupplier;
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

    public $dateFrom;
    public $dateTo;

    // Analytics State
    public array $lossData = [];
    public array $topLossData = [];
    public array $reviewData = [];
    public array $supplierData = [];


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
        $suppGrouped = $products->groupBy('supplier_id');
        $suppLabels = [];
        $suppData = [];
        foreach ($suppGrouped as $sId => $items) {
            $suppName = $items->first()->supplier->name ?? 'Ohne Lieferant';
            $suppLabels[] = $suppName;
            $suppData[] = $items->count();
        }
        $this->supplierData = ['labels' => $suppLabels, 'data' => $suppData];
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

                $totalCost = $purchase + $laserCost + $packaging + $shipping;
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
                    'price' => $product->price / 100,
                    'net_price' => $netPrice / 100,
                    'purchase_price' => $purchase / 100,
                    'laser_cost' => $laserCost / 100,
                    'packaging_cost' => $packaging / 100,
                    'shipping_cost' => $shipping / 100,
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
                    'packaging_weight' => $product->packaging_weight,
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
        $this->dispatch('analytics-updated');

        return view('livewire.shop.product.product-analytics', [
            'combinedData' => self::getCombinedAnalyticsData(),
            'lucidData' => self::getLucidData(),
        ]);
    }
}
