<?php

namespace App\Livewire\Shop\Product;

use Livewire\Attributes\Layout;

use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductLoss;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;

#[Layout('components.layouts.backend_layout')]
class ProductAnalytics extends Component
{
    use WithDepartmentTheming;

    protected string $themingDepartment = 'Produkte';
    protected $listeners = ['refreshProductAnalytics' => '$refresh'];

    public function mount()
    {
        // Init logic if needed
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
            $sold = $salesThisYear[$product->id];
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

    public function render()
    {
        return view('livewire.shop.product.product-analytics', [
            'combinedData' => self::getCombinedAnalyticsData(),
            'lucidData' => self::getLucidData(),
        ]);
    }
}
