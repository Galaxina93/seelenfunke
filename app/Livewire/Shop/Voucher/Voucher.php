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

        return view('livewire.shop.voucher.voucher', [
            'autoVouchers' => $autoVouchers,
            'manualCoupons' => $manualCoupons
        ]);
    }
}
