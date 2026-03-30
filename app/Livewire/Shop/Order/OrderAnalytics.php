<?php

namespace App\Livewire\Shop\Order;

use App\Models\Order\OrderOrder;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Order\OrderRevocation;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;

#[Layout('components.layouts.backend_layout')]
class OrderAnalytics extends Component
{
    use WithDepartmentTheming;

    protected string $themingDepartment = 'Bestellungen';

    // Filters
    public $dateRange = '30'; // 7, 30, 90, 365, all
    

    // Processed Chart Data
    public array $b2bData = [];
    public array $processingData = [];
    public array $peakTimesData = [];
    public array $retentionData = [];
    public array $cancellationData = [];
    public array $bestsellerData = [];
    public array $weekdayData = [];
    public array $quotesData = [];
    public array $revocationsData = [];

    public function mount()
    {
        $this->calculateMetrics();
    }

    public function calculateMetrics()
    {
        $query = OrderOrder::query()
            ->with(['items.product'])
            ->whereHas('items.product', function ($q) {
                $q->where('type', 'physical'); // ONLY Physical Items
            });

        // Date Filtering
        if ($this->dateRange !== 'all') {
            $query->where('created_at', '>=', now()->subDays((int)$this->dateRange));
        }

        // We fetch the collection to do complex grouping/JSON analytics in memory 
        // because JSON extraction in raw MySQL can be inconsistent across environments.
        $orders = $query->get();

        $this->computeB2bRatio($orders);
        $this->computeProcessingTime($orders);
        $this->computePeakTimes($orders);
        $this->computeRetentionRate($orders);
        $this->computeCancellationDrain($orders);
        $this->computeBestsellers($orders);
        $this->computeWeekdayVolume($orders);
        $this->computeQuotes();
        $this->computeRevocations();
        
        // Let the Alpine/Chart.js frontend know data has refreshed
        $this->dispatch('analytics-updated');
    }

    public function updatedDateRange()
    {
        $this->calculateMetrics();
    }

    private function computeB2bRatio($orders)
    {
        $b2b = 0;
        $b2c = 0;

        foreach ($orders as $order) {
            $isB2b = !empty($order->billing_address['company'] ?? null);
            if ($isB2b) {
                $b2b++;
            } else {
                $b2c++;
            }
        }

        $this->b2bData = [
            'labels' => ['B2C (Privat)', 'B2B (Geschäfts.)'],
            'data' => [$b2c, $b2b]
        ];
    }

    private function computeProcessingTime($orders)
    {
        // Focus only on shipped or completed orders to measure actual fulfillment time
        $fulfilledOrders = $orders->filter(function($order) {
            return in_array($order->status, ['shipped', 'completed']);
        });

        // Group by Date (Y-m-d)
        $grouped = $fulfilledOrders->groupBy(function($order) {
            return $order->created_at->format('Y-m-d');
        });

        $labels = [];
        $avgTimesRaw = [];

        foreach ($grouped as $date => $dailyOrders) {
            $totalHours = 0;
            $count = 0;
            
            foreach ($dailyOrders as $order) {
                // If it's shipped, updated_at typically represents the fulfillment timestamp
                $totalHours += abs($order->created_at->diffInHours($order->updated_at));
                $count++;
            }

            if ($count > 0) {
                $labels[] = Carbon::parse($date)->format('d.m.');
                $avgTimesRaw[] = round($totalHours / $count, 1);
            }
        }

        $this->processingData = [
            'labels' => $labels,
            'data' => $avgTimesRaw
        ];
    }

    private function computePeakTimes($orders)
    {
        // Group by hour of day (0-23)
        $hourCounts = array_fill(0, 24, 0);

        foreach ($orders as $order) {
            $hour = (int)$order->created_at->format('H');
            $hourCounts[$hour]++;
        }

        $labels = [];
        for ($i=0; $i<24; $i++) {
            $labels[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
        }

        $this->peakTimesData = [
            'labels' => $labels,
            'data' => $hourCounts
        ];
    }

    private function computeRetentionRate($orders)
    {
        $newCustomers = 0;
        $returningCustomers = 0;

        foreach ($orders as $order) {
            $email = $order->shipping_address['email'] ?? ($order->billing_address['email'] ?? null);
            if ($email) {
                // Check if this customer has a COMPLETED order BEFORE this specific order's creation date
                $hasPrior = OrderOrder::where(function($q) use ($email) {
                        $q->where('shipping_address->email', $email)
                          ->orWhere('billing_address->email', $email);
                    })
                    ->where('status', 'completed')
                    ->where('created_at', '<', $order->created_at)
                    ->exists();

                if ($hasPrior) {
                    $returningCustomers++;
                } else {
                    $newCustomers++;
                }
            } else {
                $newCustomers++; // Fallback if no email is attached natively
            }
        }

        $this->retentionData = [
            'labels' => ['Stammkunden', 'Neukunden'],
            'data' => [$returningCustomers, $newCustomers]
        ];
    }

    private function computeCancellationDrain($orders)
    {
        $cancelledOrders = $orders->filter(function($order) {
            return in_array($order->status, ['cancelled', 'refunded', 'failed']);
        });

        // Group by Date for timeline analysis
        $grouped = $cancelledOrders->groupBy(function($order) {
            return $order->created_at->format('Y-m-d');
        });

        $labels = [];
        $moneyRaw = [];
        $totalLost = 0;

        foreach ($grouped as $date => $dailyOrders) {
            $sum = 0;
            foreach ($dailyOrders as $order) {
                $sum += (float) $order->total_amount;
            }
            $labels[] = Carbon::parse($date)->format('d.m.');
            $moneyRaw[] = round($sum, 2);
            $totalLost += $sum;
        }

        $this->cancellationData = [
            'labels' => $labels,
            'data' => $moneyRaw,
            'total_lost' => round($totalLost, 2)
        ];
    }

    private function computeBestsellers($orders)
    {
        $productCounts = [];
        $productNames = [];

        foreach ($orders as $order) {
            // Only count successfully sold metrics
            if (!in_array($order->status, ['cancelled', 'failed', 'refunded'])) {
                foreach ($order->items as $item) {
                    if ($item->product && $item->product->type === 'physical') {
                        $pid = $item->product_id;
                        if (!isset($productCounts[$pid])) {
                            $productCounts[$pid] = 0;
                            // Ensure string length is safely capped for Chart arrays
                            $productNames[$pid] = mb_strimwidth($item->product->name ?? 'Unbekannt', 0, 20, '...');
                        }
                        $productCounts[$pid] += $item->quantity;
                    }
                }
            }
        }

        // Sort descending
        arsort($productCounts);
        $top5Ids = array_slice(array_keys($productCounts), 0, 5);

        $labels = [];
        $data = [];

        foreach ($top5Ids as $id) {
            $labels[] = $productNames[$id];
            $data[] = $productCounts[$id];
        }

        $this->bestsellerData = [
            'labels' => $labels,
            'data' => $data
        ];
    }

    private function computeWeekdayVolume($orders)
    {
        $days = [
            1 => 'Montag', 
            2 => 'Dienstag', 
            3 => 'Mittwoch', 
            4 => 'Donnerstag', 
            5 => 'Freitag', 
            6 => 'Samstag', 
            7 => 'Sonntag'
        ];
        
        $dayCounts = array_fill_keys(array_values($days), 0);

        foreach ($orders as $order) {
            // Format N outputs 1 for Monday to 7 for Sunday
            $dayOfWeek = $order->created_at->format('N');
            $dayName = $days[(int)$dayOfWeek];
            $dayCounts[$dayName]++;
        }

        $this->weekdayData = [
            'labels' => array_keys($dayCounts),
            'data' => array_values($dayCounts)
        ];
    }

    private function computeQuotes()
    {
        $query = OrderQuoteRequest::query();
        if ($this->dateRange !== 'all') {
            $query->where('created_at', '>=', now()->subDays((int)$this->dateRange));
        }
        $quotes = $query->get();
        // Group by Date for timeline
        $grouped = $quotes->groupBy(function($q) {
            return $q->created_at->format('Y-m-d');
        });

        $labels = [];
        $data = [];

        foreach ($grouped as $date => $dailyQuotes) {
            $labels[] = Carbon::parse($date)->format('d.m.');
            $data[] = $dailyQuotes->count();
        }

        $this->quotesData = [
            'labels' => $labels,
            'data' => $data
        ];
    }

    private function computeRevocations()
    {
        $query = OrderRevocation::query();
        if ($this->dateRange !== 'all') {
            $query->where('created_at', '>=', now()->subDays((int)$this->dateRange));
        }
        $revocations = $query->get();
        $grouped = $revocations->groupBy(function($r) {
            return $r->created_at->format('Y-m-d');
        });

        $labels = [];
        $data = [];

        foreach ($grouped as $date => $dailyRevs) {
            $labels[] = Carbon::parse($date)->format('d.m.');
            $data[] = $dailyRevs->count();
        }

        $this->revocationsData = [
            'labels' => $labels,
            'data' => $data
        ];
    }


    public function render()
    {
        return view('livewire.shop.order.order-analytics');
    }
}
