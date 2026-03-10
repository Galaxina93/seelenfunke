<?php

namespace App\Livewire\Shop\Voucher;

use App\Models\Voucher as VoucherModel;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class Voucher extends Component
{
    use WithPagination;

    public $voucherSectionMode = 'auto'; // 'auto' oder 'manual'

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
            'manual_code' => 'required|min:3|unique:voucher,code,' . $this->manualId,
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

    public function render()
    {
        // Hole Auto-Gutscheine und sortiere sie nach dem Gültigkeitsdatum (Jan-Dez)
        $autoVouchers = VoucherModel::where('mode', 'auto')
            ->orderBy('valid_from', 'asc')
            ->get();

        $manualCoupons = $this->voucherSectionMode === 'manual'
            ? VoucherModel::where('mode', 'manual')->latest()->paginate(10)
            : [];
            
        $chartData = $this->getChartData();

        return view('livewire.shop.voucher.voucher', [
            'autoVouchers' => $autoVouchers,
            'manualCoupons' => $manualCoupons,
            'chartData' => $chartData
        ]);
    }

    private function getChartData()
    {
        $start = now()->subMonths(11)->startOfMonth();
        $end = now()->endOfMonth();

        // Top 10 Gutscheine der letzten 12 Monate
        $topCoupons = \Illuminate\Support\Facades\DB::table('orders')
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
        $orders = \Illuminate\Support\Facades\DB::table('orders')
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
