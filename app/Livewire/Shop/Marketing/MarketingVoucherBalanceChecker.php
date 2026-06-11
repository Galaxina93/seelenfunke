<?php

namespace App\Livewire\Shop\Marketing;

use App\Models\Marketing\MarketingGiftVoucher;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class MarketingVoucherBalanceChecker extends Component
{
    public $code = '';
    public $result = null;

    public function checkBalance()
    {
        $this->validate([
            'code' => 'required|string|min:5|max:100',
        ], [
            'code.required' => 'Bitte gib einen Gutscheincode ein.',
            'code.min' => 'Der Gutscheincode ist zu kurz.',
            'code.max' => 'Der Gutscheincode ist zu lang.',
        ]);

        $ip = request()->ip();
        $key = 'voucher-check:' . $ip;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->result = null;
            $this->addError('code', "Zu viele Versuche. Bitte warte {$seconds} Sekunden.");
            return;
        }

        RateLimiter::hit($key, 60); // 60 seconds decay

        $trimmedCode = trim(strtoupper($this->code));
        $voucher = MarketingGiftVoucher::where('code', $trimmedCode)->first();

        if (!$voucher || !$voucher->isValid()) {
            $this->result = null;
            $this->addError('code', 'Der eingegebene Gutscheincode ist ungültig, abgelaufen oder vollständig aufgebraucht.');
            return;
        }

        // Return only balance and expiration date for security (zero leakage of personal info)
        $this->result = [
            'balance' => number_format($voucher->current_balance / 100, 2, ',', '.') . ' €',
            'valid_until' => $voucher->valid_until ? $voucher->valid_until->format('d.m.Y') : 'Unbegrenzt',
        ];
    }

    public function render()
    {
        return view('livewire.shop.marketing.marketing-voucher-balance-checker');
    }
}
