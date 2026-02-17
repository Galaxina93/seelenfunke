<?php

namespace App\Livewire\Frontend;

use App\Models\FunkiVoucher;
use Livewire\Component;

class VoucherSlider extends Component
{
    public $isOpen = false;

    public function toggle()
    {
        $this->isOpen = !$this->isOpen;
    }

    public function copyCode($code)
    {
        $this->dispatch('code-copied', code: $code); // Für JS (Toast Nachricht)
    }

    public function render()
    {
        // Hole Gutscheine für den aktuellen Monat
        $vouchers = FunkiVoucher::current()->get();

        return view('livewire.frontend.voucher-slider', [
            'vouchers' => $vouchers
        ]);
    }
}
