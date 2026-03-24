<?php

namespace App\Livewire\Shop\Product;

use App\Models\Order\OrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductLoss;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ProductAnalytics extends Component
{
    public $activeTab = 'true-costs';

    // Schwund Erfassung Properties
    public $lossModalOpen = false;
    public $lossProductId = null;
    public $lossQuantity = 1;
    public $lossReason = '';

    // Inline Bearbeitung
    public $editLossId = null;
    public $editLossQuantity = 1;
    public $editLossReason = '';

    protected $listeners = ['refreshProductAnalytics' => '$refresh'];

    public function mount()
    {
        // Init logic if needed
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function openLossModal($productId)
    {
        $this->lossProductId = $productId;
        $this->lossQuantity = 1;
        $this->lossReason = '';
        $this->lossModalOpen = true;
    }

    public function recordLoss()
    {
        $this->validate([
            'lossProductId' => 'required',
            'lossQuantity' => 'required|numeric|min:1',
            'lossReason' => 'required|string|min:3'
        ]);

        $product = Product::findOrFail($this->lossProductId);

        if ($product->quantity < $this->lossQuantity) {
            $this->addError('lossQuantity', 'Nicht genug Bestand vorhanden.');
            return;
        }

        // Berechne Wertverlust (Einkaufspreis * Menge) - Fallback auf 0
        $costValue = ($product->purchase_price ?? 0) * $this->lossQuantity;

        ProductLoss::create([
            'product_id' => $product->id,
            'quantity' => $this->lossQuantity,
            'cost_value' => $costValue,
            'reason' => $this->lossReason,
            'recorded_by' => auth('admin')->id(),
        ]);

        // Bestand abziehen
        $product->reduceStock($this->lossQuantity);

        $this->lossModalOpen = false;
        $this->dispatch('toast', message: 'Verlust erfolgreich verbucht und Bestand aktualisiert.', type: 'success');
        $this->dispatch('refreshProductAnalytics');
    }

    public function startEditLoss($id)
    {
        $loss = ProductLoss::findOrFail($id);
        $this->editLossId = $id;
        $this->editLossQuantity = $loss->quantity;
        $this->editLossReason = $loss->reason;
    }

    public function cancelEditLoss()
    {
        $this->editLossId = null;
    }

    public function updateLoss()
    {
        $this->validate([
            'editLossQuantity' => 'required|numeric|min:1',
            'editLossReason' => 'required|string|min:3'
        ]);

        $loss = ProductLoss::findOrFail($this->editLossId);
        $product = $loss->product;

        if (!$product) {
            $this->addError('editLossQuantity', 'Produkt nicht mehr im System.');
            return;
        }

        $difference = $this->editLossQuantity - $loss->quantity;
        
        if ($difference > 0 && $product->quantity < $difference) {
            $this->addError('editLossQuantity', 'Nicht genug Bestand für diese Erhöhung.');
            return;
        }

        if ($difference > 0) {
            $product->reduceStock($difference);
        } elseif ($difference < 0) {
            $product->increaseStock(abs($difference));
        }

        $costValue = ($product->purchase_price ?? 0) * $this->editLossQuantity;

        $loss->update([
            'quantity' => $this->editLossQuantity,
            'reason' => $this->editLossReason,
            'cost_value' => $costValue,
        ]);

        $this->editLossId = null;
        $this->dispatch('toast', message: 'Log aktualisiert & Bestand angepasst.', type: 'success');
    }

    public function deleteLoss($id)
    {
        $loss = ProductLoss::findOrFail($id);
        
        if ($loss->product) {
            $loss->product->increaseStock($loss->quantity);
        }

        $loss->delete();
        $this->dispatch('toast', message: 'Eintrag gelöscht und Bestand wiederhergestellt.', type: 'info');
    }

    public static function getTrueCostData()
    {
        $products = Product::where('status', 'active')
            ->where('type', 'physical')
            ->get()
            ->map(function ($product) {
                $netPrice = $product->net_price; // from Model (Cents)
                $purchase = $product->purchase_price ?? 0;
                $laserRuntime = $product->laser_runtime_minutes ?? 0;
                
                // 1 Cent per minute baseline for XTool UV wear, if no custom electricity/wear factor is set
                $wearFactor = $product->electricity_wear_factor > 0 ? $product->electricity_wear_factor : 1;
                $laserCost = $laserRuntime * $wearFactor;
                
                $packaging = $product->packaging_cost ?? 0;
                $shipping = $product->shipping_cost > 0 ? $product->shipping_cost : (int) shop_setting('shipping_cost', 490);

                $totalCost = $purchase + $laserCost + $packaging + $shipping;
                $netMargin = $netPrice - $totalCost;
                $marginPercent = $netPrice > 0 ? round(($netMargin / $netPrice) * 100, 1) : 0;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'net_price' => $netPrice / 100,
                    'purchase_price' => $purchase / 100,
                    'laser_cost' => $laserCost / 100,
                    'packaging_cost' => $packaging / 100,
                    'shipping_cost' => $shipping / 100,
                    'total_cost' => $totalCost / 100,
                    'net_margin' => $netMargin / 100,
                    'margin_percent' => $marginPercent,
                ];
            });

        return $products->sortByDesc('margin_percent')->values();
    }

    public static function getForecastingData()
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        // Sales der letzten 30 Tage pro Produkt holen
        // Wir nehmen an, dass 'order_items' eine 'product_id' hat und an Bestellungen (orders) gebunden ist.
        $recentSales = OrderItem::whereHas('order', function ($query) use ($thirtyDaysAgo) {
            $query->where('created_at', '>=', $thirtyDaysAgo)
                  ->where('status', '!=', 'cancelled')
                  ->where('status', '!=', 'draft');
        })
        ->select('product_id', DB::raw('SUM(quantity) as total_sold'))
        ->groupBy('product_id')
        ->pluck('total_sold', 'product_id')
        ->toArray();

        $products = Product::where('status', 'active')
            ->where('type', 'physical')
            ->where('track_quantity', true)
            ->get()
            ->map(function ($product) use ($recentSales) {
                $soldLast30 = $recentSales[$product->id] ?? 0;
                $velocityPerDay = $soldLast30 / 30;
                
                $stock = $product->quantity;
                $reachDays = $velocityPerDay > 0 ? round($stock / $velocityPerDay) : 999;
                
                $deliveryDays = $product->delivery_time_days ?? 14; // Default 14 Tage
                
                // Status Logic
                $status = 'ok';
                if ($stock <= 0) $status = 'out_of_stock';
                elseif ($reachDays <= $deliveryDays) $status = 'critical';
                elseif ($reachDays <= ($deliveryDays + 7)) $status = 'warning';

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'stock' => $stock,
                    'sold_last_30' => $soldLast30,
                    'velocity' => round($velocityPerDay, 2),
                    'reach_days' => $reachDays,
                    'delivery_days' => $deliveryDays,
                    'status' => $status,
                ];
            });

        return $products->sortBy('reach_days')->values();
    }

    public static function getLossesData()
    {
        $currentMonth = Carbon::now()->startOfMonth();
        
        $lossesThisMonth = ProductLoss::where('created_at', '>=', $currentMonth)->sum('cost_value') / 100;
        $totalLosses = ProductLoss::sum('cost_value') / 100;
        
        $recentLosses = ProductLoss::with('product')->latest()->take(10)->get();

        return [
            'this_month' => $lossesThisMonth,
            'total' => $totalLosses,
            'recent' => $recentLosses
        ];
    }

    public static function getLucidData()
    {
        $currentYear = Carbon::now()->year;

        // Sales des aktuellen Jahres (wir summieren einfach anhand der getätigten Käufe * Gewicht)
        $salesThisYear = OrderItem::whereHas('order', function ($query) use ($currentYear) {
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
            'trueCostData' => self::getTrueCostData(),
            'forecastingData' => self::getForecastingData(),
            'lossesData' => self::getLossesData(),
            'lucidData' => self::getLucidData(),
        ]);
    }
}
