<?php

namespace App\Livewire\Shop\Order;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Order\OrderRevocation;
use App\Services\AI\AiAuthHelper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class OrderAnalytics extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Bestellungen';

    private const ALLOWED_DATE_RANGES = ['7', '30', '90', '365', 'all'];
    private const EXCLUDED_PAID_STATUSES = ['cancelled', 'refunded', 'failed'];

    #[Url]
    public string $dateRange = '30';

    // Headline KPIs
    public int $kpiPaidOrdersCount = 0;
    public float $kpiGrossRevenueEuro = 0.0;
    public float $kpiAvgOrderValueEuro = 0.0;
    public float $kpiCancelRevokeRate = 0.0;

    // Chart Payloads
    public array $b2bChartData = ['labels' => ['B2C (Privat)', 'B2B (Gewerbe)'], 'data' => [0, 0]];
    public array $processingTimeData = ['labels' => [], 'data' => []];
    public array $peakTimesData = ['labels' => [], 'data' => []];
    public array $retentionData = ['labels' => ['Wiederkehrend', 'Neukunden'], 'data' => [0, 0]];
    public array $cancellationData = ['labels' => [], 'data' => []];
    public array $bestsellerData = ['labels' => [], 'data' => []];
    public array $weekdayData = ['labels' => [], 'data' => []];
    public array $quotesChartData = ['labels' => [], 'data' => []];
    public array $revocationsChartData = ['labels' => [], 'data' => []];

    public function boot(): void
    {
        if (!AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee()) {
            abort(403, 'Zugriff verweigert: Nur für autorisiertes Personal.');
        }

        if (!in_array($this->dateRange, self::ALLOWED_DATE_RANGES, true)) {
            $this->dateRange = '30';
        }
    }

    public function mount(): void
    {
        $this->calculateMetrics();
    }

    public function updatedDateRange(): void
    {
        if (!in_array($this->dateRange, self::ALLOWED_DATE_RANGES, true)) {
            $this->dateRange = '30';
        }

        $this->calculateMetrics();
        $this->dispatch('analytics-updated');
    }

    public function calculateMetrics(): void
    {
        $now = Carbon::now()->endOfDay();

        if ($this->dateRange === 'all') {
            $earliest = array_filter([
                OrderOrder::min('created_at'),
                OrderQuoteRequest::min('created_at'),
                OrderRevocation::min('created_at'),
            ]);
            $startDate = !empty($earliest)
                ? Carbon::parse(min($earliest))->startOfDay()
                : Carbon::now()->subDays(30)->startOfDay();
        } else {
            $days = max(1, (int) $this->dateRange);
            $startDate = Carbon::now()->subDays($days)->startOfDay();
        }

        $groupByMonth = in_array($this->dateRange, ['365', 'all'], true) && $startDate->diffInDays($now) > 90;

        $orders = OrderOrder::with(['items', 'shipments:id,order_id,created_at'])
            ->whereBetween('created_at', [$startDate, $now])
            ->get();

        $quotes = OrderQuoteRequest::whereBetween('created_at', [$startDate, $now])->get(['id', 'created_at']);
        $revocations = OrderRevocation::whereBetween('created_at', [$startDate, $now])->get(['id', 'created_at']);

        $validPaidOrders = $orders->filter(function ($o) {
            return $o->payment_status === 'paid'
                && !in_array((string) $o->status, self::EXCLUDED_PAID_STATUSES, true);
        });

        // 0. Headline KPIs
        $this->kpiPaidOrdersCount = $validPaidOrders->count();
        $grossCents = (int) $validPaidOrders->sum('total_price');
        $this->kpiGrossRevenueEuro = round($grossCents / 100.0, 2);
        $this->kpiAvgOrderValueEuro = $this->kpiPaidOrdersCount > 0
            ? round($this->kpiGrossRevenueEuro / $this->kpiPaidOrdersCount, 2)
            : 0.0;

        $cancelledOrRefundedCount = $orders->filter(
            fn ($o) => in_array((string) $o->status, ['cancelled', 'refunded'], true)
        )->count();
        $problemCount = $cancelledOrRefundedCount + $revocations->count();
        $totalBase = max(1, $orders->count());
        $this->kpiCancelRevokeRate = $orders->count() > 0
            ? round(min(100.0, ($problemCount / $totalBase) * 100.0), 1)
            : 0.0;

        // Charts
        $this->computeB2bRatio($validPaidOrders);
        $this->computeProcessingTime($orders, $startDate, $now, $groupByMonth);
        $this->computePeakTimes($validPaidOrders);
        $this->computeRetentionRate($validPaidOrders);
        $this->computeCancellationDrain($orders, $startDate, $now, $groupByMonth);
        $this->computeBestsellers($validPaidOrders);
        $this->computeWeekdayVolume($validPaidOrders);
        $this->computeQuotesTimeline($quotes, $startDate, $now, $groupByMonth);
        $this->computeRevocationsTimeline($revocations, $startDate, $now, $groupByMonth);
    }

    protected function buildEmptyTimeline(Carbon $from, Carbon $to, bool $groupByMonth): array
    {
        $map = [];
        $cursor = $groupByMonth ? $from->copy()->startOfMonth() : $from->copy();
        while ($cursor->lte($to)) {
            $key = $groupByMonth ? $cursor->format('m/Y') : $cursor->format('d.m.');
            $map[$key] = 0.0;
            $cursor = $groupByMonth ? $cursor->addMonth() : $cursor->addDay();
        }

        return $map;
    }

    private function computeB2bRatio(Collection $validPaidOrders): void
    {
        $b2bCount = 0;
        $b2cCount = 0;

        foreach ($validPaidOrders as $order) {
            $billing = is_array($order->billing_address) ? $order->billing_address : [];
            if (!empty(trim((string) ($billing['company'] ?? '')))) {
                $b2bCount++;
            } else {
                $b2cCount++;
            }
        }

        $this->b2bChartData = [
            'labels' => ['B2C (Privat)', 'B2B (Gewerbe)'],
            'data' => [$b2cCount, $b2bCount],
        ];
    }

    private function computeProcessingTime(Collection $orders, Carbon $from, Carbon $to, bool $groupByMonth): void
    {
        $completed = $orders->filter(fn ($o) => in_array((string) $o->status, ['shipped', 'completed'], true));

        $buckets = [];
        $cursor = $groupByMonth ? $from->copy()->startOfMonth() : $from->copy();
        while ($cursor->lte($to)) {
            $key = $groupByMonth ? $cursor->format('m/Y') : $cursor->format('d.m.');
            $buckets[$key] = [];
            $cursor = $groupByMonth ? $cursor->addMonth() : $cursor->addDay();
        }

        foreach ($completed as $order) {
            if (!$order->created_at) {
                continue;
            }

            $earliestShipment = $order->relationLoaded('shipments')
                ? $order->shipments->sortBy('created_at')->first()
                : null;

            $finishTime = ($earliestShipment && $earliestShipment->created_at)
                ? $earliestShipment->created_at
                : $order->updated_at;

            if (!$finishTime) {
                continue;
            }

            $hours = round(max(0.0, $order->created_at->diffInMinutes($finishTime, false)) / 60.0, 1);
            $key = $groupByMonth ? $order->created_at->format('m/Y') : $order->created_at->format('d.m.');
            if (array_key_exists($key, $buckets)) {
                $buckets[$key][] = $hours;
            }
        }

        $labels = [];
        $data = [];
        foreach ($buckets as $key => $hoursList) {
            if (!empty($hoursList)) {
                $labels[] = $key;
                $data[] = round(array_sum($hoursList) / count($hoursList), 1);
            }
        }

        $this->processingTimeData = [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    private function computePeakTimes(Collection $validPaidOrders): void
    {
        $hours = array_fill(0, 24, 0);

        foreach ($validPaidOrders as $order) {
            if ($order->created_at) {
                $hour = (int) $order->created_at->format('G');
                $hours[$hour]++;
            }
        }

        $labels = [];
        for ($i = 0; $i < 24; $i++) {
            $labels[] = str_pad((string) $i, 2, '0', STR_PAD_LEFT) . ':00';
        }

        $this->peakTimesData = [
            'labels' => $labels,
            'data' => array_values($hours),
        ];
    }

    private function computeRetentionRate(Collection $validPaidOrders): void
    {
        if ($validPaidOrders->isEmpty()) {
            $this->retentionData = [
                'labels' => ['Wiederkehrend', 'Neukunden'],
                'data' => [0, 0],
            ];

            return;
        }

        $customerIds = $validPaidOrders->pluck('customer_id')->filter()->unique()->values()->all();
        $emails = $validPaidOrders->map(function ($o) {
            $raw = $o->email ?: ($o->billing_address['email'] ?? null);

            return is_string($raw) && trim($raw) !== '' ? mb_strtolower(trim($raw)) : null;
        })->filter()->unique()->values()->all();

        $firstByCustomerId = [];
        if (!empty($customerIds)) {
            $rows = OrderOrder::query()
                ->select('customer_id', DB::raw('MIN(created_at) as first_at'))
                ->where('payment_status', 'paid')
                ->whereNotIn('status', self::EXCLUDED_PAID_STATUSES)
                ->whereIn('customer_id', $customerIds)
                ->groupBy('customer_id')
                ->get();
            foreach ($rows as $r) {
                if ($r->customer_id && $r->first_at) {
                    $firstByCustomerId[(string) $r->customer_id] = Carbon::parse($r->first_at);
                }
            }
        }

        $firstByEmail = [];
        if (!empty($emails)) {
            $rows = OrderOrder::query()
                ->select(DB::raw('LOWER(email) as norm_email'), DB::raw('MIN(created_at) as first_at'))
                ->where('payment_status', 'paid')
                ->whereNotIn('status', self::EXCLUDED_PAID_STATUSES)
                ->whereNotNull('email')
                ->whereIn(DB::raw('LOWER(email)'), $emails)
                ->groupBy(DB::raw('LOWER(email)'))
                ->get();
            foreach ($rows as $r) {
                if ($r->norm_email && $r->first_at) {
                    $firstByEmail[(string) $r->norm_email] = Carbon::parse($r->first_at);
                }
            }
        }

        $returningOrders = 0;
        $newOrders = 0;

        foreach ($validPaidOrders as $order) {
            if (!$order->created_at) {
                $newOrders++;
                continue;
            }

            $cid = $order->customer_id ? (string) $order->customer_id : null;
            $rawEmail = $order->email ?: ($order->billing_address['email'] ?? null);
            $normEmail = is_string($rawEmail) && trim($rawEmail) !== '' ? mb_strtolower(trim($rawEmail)) : null;

            $earliestAt = null;
            if ($cid && isset($firstByCustomerId[$cid])) {
                $earliestAt = $firstByCustomerId[$cid];
            }
            if ($normEmail && isset($firstByEmail[$normEmail])) {
                $cand = $firstByEmail[$normEmail];
                if ($earliestAt === null || $cand->lt($earliestAt)) {
                    $earliestAt = $cand;
                }
            }

            if ($earliestAt !== null && $earliestAt->lt($order->created_at)) {
                $returningOrders++;
            } else {
                $newOrders++;
            }
        }

        $this->retentionData = [
            'labels' => ['Wiederkehrend', 'Neukunden'],
            'data' => [$returningOrders, $newOrders],
        ];
    }

    private function computeCancellationDrain(Collection $orders, Carbon $from, Carbon $to, bool $groupByMonth): void
    {
        $lostOrders = $orders->filter(function ($o) {
            return in_array((string) $o->status, ['cancelled', 'refunded'], true)
                || (string) $o->payment_status === 'failed';
        });

        $timeline = $this->buildEmptyTimeline($from, $to, $groupByMonth);

        foreach ($lostOrders as $order) {
            if (!$order->created_at) {
                continue;
            }
            $key = $groupByMonth ? $order->created_at->format('m/Y') : $order->created_at->format('d.m.');
            if (array_key_exists($key, $timeline)) {
                $timeline[$key] = round($timeline[$key] + (((int) $order->total_price) / 100.0), 2);
            }
        }

        $this->cancellationData = [
            'labels' => array_keys($timeline),
            'data' => array_values($timeline),
        ];
    }

    private function computeBestsellers(Collection $validPaidOrders): void
    {
        $productSales = [];

        foreach ($validPaidOrders as $order) {
            foreach ($order->items as $item) {
                $pName = trim((string) ($item->product_name ?: 'Unbekanntes Produkt'));
                $pid = $item->product_id
                    ? 'prod_' . $item->product_id
                    : 'custom_' . md5(mb_strtolower($pName));

                if (!isset($productSales[$pid])) {
                    $productSales[$pid] = [
                        'name' => $pName,
                        'qty' => 0,
                    ];
                }
                $productSales[$pid]['qty'] += max(0, (int) $item->quantity);
            }
        }

        usort($productSales, fn ($a, $b) => $b['qty'] <=> $a['qty']);
        $top5 = array_slice($productSales, 0, 5);

        $labels = [];
        $data = [];
        foreach ($top5 as $p) {
            if ($p['qty'] <= 0) {
                continue;
            }
            $labels[] = mb_strlen($p['name']) > 28 ? (mb_substr($p['name'], 0, 28) . '...') : $p['name'];
            $data[] = $p['qty'];
        }

        $this->bestsellerData = [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    private function computeWeekdayVolume(Collection $validPaidOrders): void
    {
        $days = [
            1 => ['name' => 'Montag', 'count' => 0],
            2 => ['name' => 'Dienstag', 'count' => 0],
            3 => ['name' => 'Mittwoch', 'count' => 0],
            4 => ['name' => 'Donnerstag', 'count' => 0],
            5 => ['name' => 'Freitag', 'count' => 0],
            6 => ['name' => 'Samstag', 'count' => 0],
            7 => ['name' => 'Sonntag', 'count' => 0],
        ];

        foreach ($validPaidOrders as $order) {
            if ($order->created_at) {
                $dayOfWeek = (int) $order->created_at->format('N');
                if (isset($days[$dayOfWeek])) {
                    $days[$dayOfWeek]['count']++;
                }
            }
        }

        $this->weekdayData = [
            'labels' => array_column($days, 'name'),
            'data' => array_column($days, 'count'),
        ];
    }

    private function computeQuotesTimeline(Collection $quotes, Carbon $from, Carbon $to, bool $groupByMonth): void
    {
        $timeline = $this->buildEmptyTimeline($from, $to, $groupByMonth);

        foreach ($quotes as $q) {
            if (!$q->created_at) {
                continue;
            }
            $key = $groupByMonth ? $q->created_at->format('m/Y') : $q->created_at->format('d.m.');
            if (array_key_exists($key, $timeline)) {
                $timeline[$key]++;
            }
        }

        $this->quotesChartData = [
            'labels' => array_keys($timeline),
            'data' => array_map('intval', array_values($timeline)),
        ];
    }

    private function computeRevocationsTimeline(Collection $revocations, Carbon $from, Carbon $to, bool $groupByMonth): void
    {
        $timeline = $this->buildEmptyTimeline($from, $to, $groupByMonth);

        foreach ($revocations as $r) {
            if (!$r->created_at) {
                continue;
            }
            $key = $groupByMonth ? $r->created_at->format('m/Y') : $r->created_at->format('d.m.');
            if (array_key_exists($key, $timeline)) {
                $timeline[$key]++;
            }
        }

        $this->revocationsChartData = [
            'labels' => array_keys($timeline),
            'data' => array_map('intval', array_values($timeline)),
        ];
    }

    public function render()
    {
        return view('livewire.shop.order.order-analytics');
    }
}
