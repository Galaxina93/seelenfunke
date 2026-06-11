<?php

namespace App\Livewire\Shop\Marketing;

use Livewire\Attributes\Layout;

use App\Models\Marketing\MarketingVoucher as VoucherModel;
use App\Livewire\Traits\WithDepartmentTheming;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backend_layout')]
class MarketingVoucher extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Marketing';

    use WithPagination, WithDepartmentTheming;

    public $voucherSectionMode = 'auto'; // 'auto' oder 'manual'

    // --- SUCHE & FILTER FÜR WERTGUTSCHEINE ---
    public $searchCode = '';
    public $filterDelivery = 'all'; // 'all', 'email', 'post'
    public $filterBalance = 'all'; // 'all', 'full', 'partial', 'empty'
    public $filterStatus = 'all'; // 'all', 'active', 'inactive', 'expired'
    public $filterMinInitialValue = '';
    public $filterMaxInitialValue = '';
    public $filterMinCurrentBalance = '';
    public $filterMaxCurrentBalance = '';
    public $filterCreatedAtFrom = '';
    public $filterCreatedAtTo = '';
    public $filterValidUntilFrom = '';
    public $filterValidUntilTo = '';
    public $sortOrder = 'created_at_desc';

    public function updatingSearchCode() { $this->resetPage(); }
    public function updatingFilterDelivery() { $this->resetPage(); }
    public function updatingFilterBalance() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }
    public function updatingFilterMinInitialValue() { $this->resetPage(); }
    public function updatingFilterMaxInitialValue() { $this->resetPage(); }
    public function updatingFilterMinCurrentBalance() { $this->resetPage(); }
    public function updatingFilterMaxCurrentBalance() { $this->resetPage(); }
    public function updatingFilterCreatedAtFrom() { $this->resetPage(); }
    public function updatingFilterCreatedAtTo() { $this->resetPage(); }
    public function updatingFilterValidUntilFrom() { $this->resetPage(); }
    public function updatingFilterValidUntilTo() { $this->resetPage(); }
    public function updatingSortOrder() { $this->resetPage(); }

    public function clearGiftFilters()
    {
        $this->reset([
            'searchCode', 'filterDelivery', 'filterBalance', 'filterStatus',
            'filterMinInitialValue', 'filterMaxInitialValue',
            'filterMinCurrentBalance', 'filterMaxCurrentBalance',
            'filterCreatedAtFrom', 'filterCreatedAtTo',
            'filterValidUntilFrom', 'filterValidUntilTo',
            'sortOrder'
        ]);
        $this->resetPage();
    }

    // --- MANUELLE GUTSCHEINE ---
    public $isCreatingManual = false;
    public $isEditingManual = false;
    public $manualId = null;
    public $manual_code;
    public $manual_type = 'fixed';
    public $manual_value;
    public $manual_min_order_value;
    public $manual_usage_limit;
    public $manual_valid_until;
    public $manual_is_active = true;

    public function toggleVoucherSectionMode()
    {
        $this->voucherSectionMode = ($this->voucherSectionMode === 'auto') ? 'manual' : 'auto';
        $this->cancelManualCoupon();
        $this->resetPage();
    }

    public function toggleVoucherStatus($id)
    {
        $v = VoucherModel::find($id);
        if ($v) {
            $v->is_active = !$v->is_active;
            $v->save();
            session()->flash('success', "Gutschein erfolgreich " . ($v->is_active ? 'aktiviert' : 'pausiert') . ".");
        }
    }

    // ==========================================
    // LOGIK: MANUELLE GUTSCHEINE
    // ==========================================

    public function createManualCoupon()
    {
        $this->resetManualInput();
        $this->manual_code = strtoupper(Str::random(8));
        $this->isCreatingManual = true;
        $this->isEditingManual = false;
    }

    public function editManualCoupon($id)
    {
        $v = VoucherModel::where('mode', 'manual')->findOrFail($id);
        $this->manualId = $v->id;
        $this->manual_code = $v->code;
        $this->manual_type = $v->type;
        $this->manual_is_active = (bool)$v->is_active;
        $this->manual_usage_limit = $v->usage_limit;
        $this->manual_value = $v->type === 'fixed' ? $v->value / 100 : $v->value;
        $this->manual_min_order_value = $v->min_order_value ? $v->min_order_value / 100 : null;
        $this->manual_valid_until = $v->valid_until ? (is_string($v->valid_until) ? substr($v->valid_until, 0, 10) : $v->valid_until->format('Y-m-d')) : null;

        $this->isCreatingManual = false;
        $this->isEditingManual = true;
    }

    public function saveManualCoupon()
    {
        $this->validate([
            'manual_code' => 'required|min:3|unique:marketing_vouchers,code,' . $this->manualId,
            'manual_type' => 'required|in:fixed,percent',
            'manual_value' => 'required|numeric|min:1',
        ]);

        $dbValue = ($this->manual_type === 'fixed') ? (int)($this->manual_value * 100) : (int)$this->manual_value;
        $dbMinOrder = $this->manual_min_order_value ? (int)($this->manual_min_order_value * 100) : null;

        VoucherModel::updateOrCreate(
            ['id' => $this->manualId],
            [
                'code' => strtoupper($this->manual_code),
                'title' => 'Manueller Code: ' . strtoupper($this->manual_code),
                'type' => $this->manual_type,
                'is_active' => $this->manual_is_active,
                'usage_limit' => $this->manual_usage_limit ?: null,
                'valid_until' => $this->manual_valid_until ?: null,
                'value' => $dbValue,
                'min_order_value' => $dbMinOrder,
                'mode' => 'manual',
                'valid_from' => $this->manualId ? VoucherModel::find($this->manualId)->valid_from : now(),
            ]
        );

        session()->flash('success', $this->isEditingManual ? 'Gutschein aktualisiert.' : 'Gutschein erstellt.');
        $this->cancelManualCoupon();
    }

    public function deleteManualCoupon($id)
    {
        VoucherModel::where('mode', 'manual')->findOrFail($id)->delete();
        session()->flash('success', 'Gutschein gelöscht.');
    }

    public function cancelManualCoupon()
    {
        $this->resetManualInput();
        $this->isCreatingManual = false;
        $this->isEditingManual = false;
    }

    private function resetManualInput()
    {
        $this->reset([
            'manualId', 'manual_code', 'manual_type', 'manual_value',
            'manual_min_order_value', 'manual_usage_limit', 'manual_valid_until'
        ]);
        $this->manual_is_active = true;
        $this->manual_type = 'fixed';
    }

    public function toggleGiftVoucherStatus($id)
    {
        $v = \App\Models\Marketing\MarketingGiftVoucher::find($id);
        if ($v) {
            $v->is_active = !$v->is_active;
            $v->save();
            session()->flash('success', "Wertgutschein erfolgreich " . ($v->is_active ? 'aktiviert' : 'deaktiviert') . ".");
        }
    }

    public function render()
    {
        // Hole Auto-Gutscheine und sortiere sie nach dem Gültigkeitsdatum (Jan-Dez)
        $autoVouchers = VoucherModel::where('mode', 'auto')
            ->orderBy('valid_from', 'asc')
            ->get();

        $manualCoupons = $this->voucherSectionMode === 'manual'
            ? VoucherModel::where('mode', 'manual')->latest()->paginate(10)
            : [];

        $giftVouchers = [];
        $giftVoucherStats = [
            'count' => 0,
            'sum_initial' => 0.0,
            'sum_current' => 0.0,
            'sum_used' => 0.0,
        ];

        if ($this->voucherSectionMode === 'gift') {
            $giftVouchersQuery = \App\Models\Marketing\MarketingGiftVoucher::with(['logs.order', 'orderItem.order']);

            // 1. Suche nach Code, Bestellung-Nr, E-Mail (Käufer & Empfänger), Name (Käufer & Empfänger), Nachricht
            if (!empty($this->searchCode)) {
                $search = '%' . $this->searchCode . '%';
                $giftVouchersQuery->where(function ($q) use ($search) {
                    $q->where('code', 'like', $search)
                      ->orWhere('recipient_name', 'like', $search)
                      ->orWhere('recipient_email', 'like', $search)
                      ->orWhere('personal_message', 'like', $search)
                      ->orWhereHas('orderItem.order', function ($oq) use ($search) {
                          $oq->where('order_number', 'like', $search)
                            ->orWhere('email', 'like', $search)
                            ->orWhere('billing_address->first_name', 'like', $search)
                            ->orWhere('billing_address->last_name', 'like', $search);
                      });
                });
            }

            // 2. Filter nach Versandart
            if ($this->filterDelivery !== 'all') {
                $giftVouchersQuery->where('delivery_method', $this->filterDelivery);
            }

            // 3. Filter nach Guthaben
            if ($this->filterBalance !== 'all') {
                if ($this->filterBalance === 'full') {
                    $giftVouchersQuery->whereRaw('current_balance = initial_value');
                } elseif ($this->filterBalance === 'partial') {
                    $giftVouchersQuery->whereRaw('current_balance > 0 AND current_balance < initial_value');
                } elseif ($this->filterBalance === 'empty') {
                    $giftVouchersQuery->where('current_balance', 0);
                }
            }

            // 4. Filter nach Status
            if ($this->filterStatus !== 'all') {
                $now = now();
                if ($this->filterStatus === 'active') {
                    $giftVouchersQuery->where('is_active', true)
                      ->where('current_balance', '>', 0)
                      ->where(function ($q) use ($now) {
                          $q->whereNull('valid_until')->orWhere('valid_until', '>', $now);
                      });
                } elseif ($this->filterStatus === 'inactive') {
                    $giftVouchersQuery->where('is_active', false);
                } elseif ($this->filterStatus === 'expired') {
                    $giftVouchersQuery->whereNotNull('valid_until')->where('valid_until', '<=', $now);
                }
            }

            // 5. Filter nach Initialwert (in € umgerechnet in Cent)
            if (is_numeric($this->filterMinInitialValue)) {
                $giftVouchersQuery->where('initial_value', '>=', (int)($this->filterMinInitialValue * 100));
            }
            if (is_numeric($this->filterMaxInitialValue)) {
                $giftVouchersQuery->where('initial_value', '<=', (int)($this->filterMaxInitialValue * 100));
            }

            // 6. Filter nach Restguthaben (in € umgerechnet in Cent)
            if (is_numeric($this->filterMinCurrentBalance)) {
                $giftVouchersQuery->where('current_balance', '>=', (int)($this->filterMinCurrentBalance * 100));
            }
            if (is_numeric($this->filterMaxCurrentBalance)) {
                $giftVouchersQuery->where('current_balance', '<=', (int)($this->filterMaxCurrentBalance * 100));
            }

            // 7. Filter nach Erstellungsdatum (created_at)
            if (!empty($this->filterCreatedAtFrom)) {
                $giftVouchersQuery->whereDate('created_at', '>=', $this->filterCreatedAtFrom);
            }
            if (!empty($this->filterCreatedAtTo)) {
                $giftVouchersQuery->whereDate('created_at', '<=', $this->filterCreatedAtTo);
            }

            // 8. Filter nach Gültigkeitsdatum (valid_until)
            if (!empty($this->filterValidUntilFrom)) {
                $giftVouchersQuery->whereDate('valid_until', '>=', $this->filterValidUntilFrom);
            }
            if (!empty($this->filterValidUntilTo)) {
                $giftVouchersQuery->whereDate('valid_until', '<=', $this->filterValidUntilTo);
            }

            // 9. Aggregierte Kennzahlen berechnen vor Sortierung und Pagination
            $statsQuery = clone $giftVouchersQuery;
            $stats = $statsQuery->getQuery()->selectRaw('
                COUNT(*) as count_total,
                SUM(initial_value) as sum_initial,
                SUM(current_balance) as sum_current
            ')->first();

            $giftVoucherStats = [
                'count' => $stats->count_total ?? 0,
                'sum_initial' => ($stats->sum_initial ?? 0) / 100,
                'sum_current' => ($stats->sum_current ?? 0) / 100,
                'sum_used' => (($stats->sum_initial ?? 0) - ($stats->sum_current ?? 0)) / 100,
            ];

            // 10. Sortierung
            switch ($this->sortOrder) {
                case 'created_at_asc':
                    $giftVouchersQuery->orderBy('created_at', 'asc');
                    break;
                case 'current_balance_desc':
                    $giftVouchersQuery->orderBy('current_balance', 'desc');
                    break;
                case 'current_balance_asc':
                    $giftVouchersQuery->orderBy('current_balance', 'asc');
                    break;
                case 'initial_value_desc':
                    $giftVouchersQuery->orderBy('initial_value', 'desc');
                    break;
                case 'initial_value_asc':
                    $giftVouchersQuery->orderBy('initial_value', 'asc');
                    break;
                case 'recipient_name_asc':
                    $giftVouchersQuery->orderBy('recipient_name', 'asc');
                    break;
                case 'recipient_name_desc':
                    $giftVouchersQuery->orderBy('recipient_name', 'desc');
                    break;
                case 'created_at_desc':
                default:
                    $giftVouchersQuery->orderBy('created_at', 'desc');
                    break;
            }

            $giftVouchers = $giftVouchersQuery->paginate(10);
        }
            
        $chartData = $this->getChartData();

        return view('livewire.shop.marketing.marketing-voucher', [
            'autoVouchers' => $autoVouchers,
            'manualCoupons' => $manualCoupons,
            'giftVouchers' => $giftVouchers,
            'giftVoucherStats' => $giftVoucherStats,
            'chartData' => $chartData
        ]);
    }

    private function getChartData()
    {
        $start = now()->subMonths(11)->startOfMonth();
        $end = now()->endOfMonth();

        // Top 10 Gutscheine der letzten 12 Monate
        $topCoupons = \Illuminate\Support\Facades\DB::table('order_orders')
            ->whereNotNull('coupon_code')
            ->whereBetween('created_at', [$start, $end])
            ->select('coupon_code', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('coupon_code')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('coupon_code')
            ->map(fn($c) => strtoupper($c))
            ->toArray();

        // Alle relevanten Bestellungen
        $orders = \Illuminate\Support\Facades\DB::table('order_orders')
            ->whereNotNull('coupon_code')
            ->whereBetween('created_at', [$start, $end])
            ->get(['coupon_code', 'created_at']);

        $monthlyData = [];

        // Initialisiere die letzten 12 Monate
        for ($i = 11; $i >= 0; $i--) {
            $monthKey = now()->subMonths($i)->format('Y-m'); // "2023-01"
            $monthLabel = now()->subMonths($i)->translatedFormat('M Y'); // "Jan 2023"
            $monthlyData[$monthKey] = [
                'label' => $monthLabel,
                'coupons' => []
            ];
            foreach($topCoupons as $tc) {
                $monthlyData[$monthKey]['coupons'][$tc] = 0;
            }
        }

        foreach ($orders as $order) {
            $monthKey = \Carbon\Carbon::parse($order->created_at)->format('Y-m');
            $code = strtoupper($order->coupon_code);
            
            if (!isset($monthlyData[$monthKey])) continue;
            if (in_array($code, $topCoupons)) {
                $monthlyData[$monthKey]['coupons'][$code]++;
            }
        }

        $labels = array_column($monthlyData, 'label');
        $datasets = [];

        $colors = [
            '168, 85, 247',  // Purple
            '234, 88, 12',   // Orange
            '16, 185, 129',  // Emerald
            '59, 130, 246',  // Blue
            '236, 72, 153',  // Pink
            '234, 179, 8',   // Yellow
            '14, 165, 233',  // Sky
            '244, 63, 94',   // Rose
            '139, 92, 246',  // Violet
            '20, 184, 166',  // Teal
        ];

        $colorIndex = 0;

        foreach ($topCoupons as $code) {
            $data = [];
            foreach ($monthlyData as $monthKey => $monthInfo) {
                $data[] = $monthInfo['coupons'][$code];
            }

            $color = $colors[$colorIndex % count($colors)];
            
            $datasets[] = [
                'label' => $code,
                'data' => $data,
                'backgroundColor' => 'rgba(' . $color . ', 0.2)',
                'borderColor' => 'rgb(' . $color . ')',
                'borderWidth' => 2,
                'tension' => 0.4,
                'fill' => true
            ];
            $colorIndex++;
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets
        ];
    }
}
