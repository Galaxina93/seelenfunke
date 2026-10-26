<?php

namespace App\Livewire\Shop\Marketing;

use App\Models\Marketing\MarketingReferral;
use App\Models\Marketing\MarketingVoucher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class MarketingVoucherSlider extends Component
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
     * Prüft, ob aktuell einlösbare Gutscheine für den aktuellen Besucher/Kunden vorliegen.
     */
    public static function hasAvailableVouchers(): bool
    {
        $referralVoucherIds = MarketingReferral::whereNotNull('voucher_id')->pluck('voucher_id')->all();

        $hasManual = MarketingVoucher::where('mode', 'manual')
            ->whereNull('recipient_email')
            ->whereNull('influencer_id')
            ->where('title', 'not like', 'Freunde-Empfehlung%')
            ->where('code', 'not like', 'FUNKI-%')
            ->when(!empty($referralVoucherIds), fn($q) => $q->whereNotIn('id', $referralVoucherIds))
            ->current()
            ->exists();

        if ($hasManual) {
            return true;
        }

        $hasAuto = MarketingVoucher::where('mode', 'auto')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('trigger_event')
                  ->orWhereNotIn('trigger_event', ['birthday', 'registered_date']);
            })
            ->whereMonth('valid_from', now()->month)
            ->exists();

        if ($hasAuto) {
            return true;
        }

        if (Auth::guard('customer')->check()) {
            $customer = Auth::guard('customer')->user();

            return MarketingVoucher::where('mode', 'manual')
                ->where('recipient_email', $customer->email)
                ->current()
                ->exists();
        }

        return false;
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
        $referralVoucherIds = MarketingReferral::whereNotNull('voucher_id')->pluck('voucher_id')->all();

        // 1. Lade alle öffentlichen manuellen Gutscheine (keine Freunde-Empfehlung Codes, keine Influencer-Codes und keine persönlichen Belohnungen)
        $manualVouchers = MarketingVoucher::where('mode', 'manual')
            ->whereNull('recipient_email')
            ->whereNull('influencer_id')
            ->where('title', 'not like', 'Freunde-Empfehlung%')
            ->where('code', 'not like', 'FUNKI-%')
            ->when(!empty($referralVoucherIds), fn($q) => $q->whereNotIn('id', $referralVoucherIds))
            ->current()
            ->get();

        // 2. Lade NUR den aktiven öffentlichen Auto-Gutschein für den AKTUELLEN MONAT (ohne persönliche Trigger)
        $activeAutoVouchers = MarketingVoucher::where('mode', 'auto')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('trigger_event')
                  ->orWhereNotIn('trigger_event', ['birthday', 'registered_date']);
            })
            ->whereMonth('valid_from', now()->month)
            ->get();

        // 3. Verarbeite die Auto-Gutscheine (Platzhalter ersetzen)
        foreach ($activeAutoVouchers as $auto) {
            $auto->code = $this->generateCouponCode($auto->code, 'Shop');
        }

        $vouchers = $manualVouchers->merge($activeAutoVouchers);

        // 4. Nur wenn der Kunde eingeloggt ist: eigene persönliche Belohnungsgutscheine ergänzen
        if (Auth::guard('customer')->check()) {
            $customer = Auth::guard('customer')->user();

            $personalVouchers = MarketingVoucher::where('mode', 'manual')
                ->where('recipient_email', $customer->email)
                ->current()
                ->get();

            $vouchers = $vouchers->merge($personalVouchers)->unique('id')->values();
        }

        return view('livewire.shop.marketing.marketing-voucher-slider', [
            'vouchers' => $vouchers
        ]);
    }
}
