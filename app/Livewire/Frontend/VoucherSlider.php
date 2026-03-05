<?php

namespace App\Livewire\Frontend;

use App\Models\Voucher;
use Illuminate\Support\Str;
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
        $this->dispatch('code-copied', code: $code);
    }

    /**
     * Ersetzt Platzhalter im Gutscheincode durch dynamische Werte.
     */
    private function generateCouponCode($pattern, $name = 'Gast')
    {
        $code = strtoupper($pattern);
        $code = str_replace('{YEAR}', date('Y'), $code);
        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', Str::slug($name));
        $code = str_replace('{NAME}', strtoupper(substr($cleanName, 0, 5)), $code);

        return $code;
    }

    public function render()
    {
        // 1. Lade alle normalen, manuellen Gutscheine, die gerade gültig sind
        $manualVouchers = Voucher::where('mode', 'manual')->current()->get();

        // 2. Lade NUR den aktiven Auto-Gutschein, der für den AKTUELLEN MONAT vorgesehen ist
        $activeAutoVouchers = Voucher::where('mode', 'auto')
            ->where('is_active', true)
            ->whereMonth('valid_from', now()->month)
            ->get();

        // 3. Verarbeite die Auto-Gutscheine (Platzhalter ersetzen)
        foreach ($activeAutoVouchers as $auto) {
            $auto->code = $this->generateCouponCode($auto->code, 'Shop');
            // Da Auto-Gutscheine ihre Werte oft in Cent speichern, passen wir das hier *nicht* an,
            // sondern überlassen die Logik der Blade-Datei, um Konsistenz zu wahren.
        }

        // Füge beide Listen zusammen
        $vouchers = $manualVouchers->merge($activeAutoVouchers);

        return view('livewire.frontend.voucher-slider', [
            'vouchers' => $vouchers
        ]);
    }
}
