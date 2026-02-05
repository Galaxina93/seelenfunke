<?php

namespace App\Livewire\Shop\voucher;

use App\Models\Coupon;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class Voucher extends Component
{
    use WithPagination;

    public $search = '';
    public $isCreating = false;
    public $isEditing = false;
    public $editId = null;

    // Form Fields
    public $code;
    public $type = 'fixed'; // fixed | percent
    public $value;
    public $min_order_value;
    public $usage_limit;
    public $valid_until;
    public $is_active = true;

    protected $rules = [
        'code' => 'required|min:3|unique:coupons,code',
        'type' => 'required|in:fixed,percent',
        'value' => 'required|numeric|min:1',
        'min_order_value' => 'nullable|numeric|min:0',
        'usage_limit' => 'nullable|integer|min:1',
        'valid_until' => 'nullable|date',
    ];

    public function render()
    {
        $coupons = Coupon::query()
            ->where('code', 'like', '%'.$this->search.'%')
            ->latest()
            ->paginate(10);

        return view('livewire.shop.voucher.voucher', [
            'coupons' => $coupons
        ]);
    }

    public function create()
    {
        $this->resetInput();
        $this->code = strtoupper(Str::random(8));
        $this->isCreating = true;
    }

    public function edit($id)
    {
        $this->resetInput();
        $c = Coupon::findOrFail($id);

        $this->editId = $c->id;
        $this->code = $c->code;
        $this->type = $c->type;
        $this->is_active = (bool)$c->is_active;
        $this->usage_limit = $c->usage_limit;

        // Werte für Input formatieren (Cent -> Euro)
        $this->value = $c->type === 'fixed' ? $c->value / 100 : $c->value;
        $this->min_order_value = $c->min_order_value ? $c->min_order_value / 100 : null;

        $this->valid_until = $c->valid_until ? $c->valid_until->format('Y-m-d') : null;

        $this->isEditing = true;
    }

    public function save()
    {
        // Dynamische Regel für Unique Code bei Edit
        $rules = $this->rules;
        if ($this->isEditing) {
            $rules['code'] = 'required|min:3|unique:coupons,code,' . $this->editId;
        }

        $this->validate($rules);

        // Datenaufbereitung
        $data = [
            'code' => strtoupper($this->code),
            'type' => $this->type,
            'is_active' => $this->is_active,
            'usage_limit' => $this->usage_limit ?: null,
            'valid_until' => $this->valid_until ?: null,
        ];

        // Werte speichern (Fixed = Cent, Percent = Ganzzahl)
        if ($this->type === 'fixed') {
            $data['value'] = (int) ($this->value * 100);
        } else {
            $data['value'] = (int) $this->value;
        }

        if ($this->min_order_value) {
            $data['min_order_value'] = (int) ($this->min_order_value * 100);
        } else {
            $data['min_order_value'] = null;
        }

        if ($this->isEditing) {
            Coupon::find($this->editId)->update($data);
            session()->flash('success', 'Gutschein aktualisiert.');
        } else {
            Coupon::create($data);
            session()->flash('success', 'Gutschein erstellt.');
        }

        $this->cancel();
    }

    public function delete($id)
    {
        Coupon::find($id)->delete();
        session()->flash('success', 'Gutschein gelöscht.');
    }

    public function cancel()
    {
        $this->resetInput();
        $this->isCreating = false;
        $this->isEditing = false;
    }

    private function resetInput()
    {
        $this->reset(['code', 'type', 'value', 'min_order_value', 'usage_limit', 'valid_until', 'is_active', 'editId']);
    }
}
