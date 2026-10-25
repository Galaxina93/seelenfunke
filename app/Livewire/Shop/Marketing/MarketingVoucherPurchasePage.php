<?php

namespace App\Livewire\Shop\Marketing;

use App\Models\Product\Product;
use App\Services\CartService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class MarketingVoucherPurchasePage extends Component
{
    #[\Livewire\Attributes\Locked]
    public Product $product;

    // Step-by-Step Wizard State (1 = Gutscheinwert, 2 = Personalisierung, 3 = Versand & Abschluss)
    public int $currentStep = 1;
    public int $maxReachedStep = 1;

    // Configuration Inputs
    public $amount = 50; // Preset values: 10, 25, 50, 100, custom
    public $customAmount = '';
    public $recipientName = '';
    public $recipientEmail = '';
    public $personalMessage = '';
    public $deliveryMethod = 'email'; // 'email' or 'post'

    // Preset Options
    public array $presets = [10, 25, 50, 100];

    public function mount()
    {
        $product = Product::where('slug', 'geschenkgutschein')->where('status', 'active')->first();
        if (!$product) {
            abort(404, 'Gutschein-Produkt nicht gefunden.');
        }
        $this->product = $product;
    }

    public function getSelectedAmountProperty()
    {
        if ($this->amount === 'custom') {
            return max(0.0, (float) str_replace(',', '.', (string) $this->customAmount));
        }
        return in_array((int) $this->amount, $this->presets, true) ? (float) $this->amount : 50.0;
    }

    public function getSelectedAmountCentsProperty()
    {
        return (int) round($this->selectedAmount * 100);
    }

    public function getPostShippingCostProperty()
    {
        return (float) (shop_setting('shipping_cost_voucher', 350) / 100);
    }

    public function getShippingCostProperty()
    {
        if ($this->deliveryMethod === 'post') {
            return $this->postShippingCost;
        }
        return 0.00;
    }

    public function getFinalTotalProperty()
    {
        return $this->selectedAmount + $this->shippingCost;
    }

    public function getIsStep1ValidProperty(): bool
    {
        if ($this->amount === 'custom') {
            $normalized = str_replace(',', '.', trim((string) $this->customAmount));
            if ($normalized === '' || !is_numeric($normalized)) {
                return false;
            }
            $val = (float) $normalized;
            return $val >= 5.0 && $val <= 1000.0 && fmod($val, 5.0) == 0.0;
        }

        return in_array((int) $this->amount, $this->presets, true);
    }

    public function getIsStep2ValidProperty(): bool
    {
        $nameLen = mb_strlen(trim((string) $this->recipientName));
        $msgLen = mb_strlen((string) $this->personalMessage);

        return $nameLen >= 2 && $nameLen <= 100 && $msgLen <= 160;
    }

    public function getIsStep3ValidProperty(): bool
    {
        if (!in_array($this->deliveryMethod, ['email', 'post'], true)) {
            return false;
        }

        if ($this->deliveryMethod === 'email') {
            $email = trim((string) $this->recipientEmail);
            return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        }

        return true;
    }

    protected function getStepRules(int $step): array
    {
        if ($step === 1) {
            if ($this->amount === 'custom') {
                return [
                    'customAmount' => [
                        'required',
                        'numeric',
                        'min:5',
                        'max:1000',
                        function ($attribute, $value, $fail) {
                            $val = (float) str_replace(',', '.', (string) $value);
                            if (fmod($val, 5.0) != 0.0) {
                                $fail('Der Wunschbetrag muss in 5er Schritten eingegeben werden (z. B. 15, 20, 25, ...).');
                            }
                        },
                    ],
                ];
            }

            return [
                'amount' => 'required|integer|in:10,25,50,100',
            ];
        }

        if ($step === 2) {
            return [
                'recipientName'   => 'required|string|min:2|max:100',
                'personalMessage' => 'nullable|string|max:160',
            ];
        }

        $rules = [
            'deliveryMethod' => 'required|in:email,post',
        ];

        if ($this->deliveryMethod === 'email') {
            $rules['recipientEmail'] = 'required|email|max:255';
        }

        return $rules;
    }

    protected function getValidationMessages(): array
    {
        return [
            'amount.required'          => 'Bitte wähle einen Gutscheinwert aus.',
            'amount.in'                => 'Bitte wähle einen gültigen Gutscheinwert aus.',
            'customAmount.required'    => 'Bitte gib deinen gewünschten Gutscheinbetrag ein.',
            'customAmount.numeric'     => 'Der Wunschbetrag muss eine Zahl sein.',
            'customAmount.min'         => 'Der Mindestwert beträgt 5 €.',
            'customAmount.max'         => 'Der Maximalwert beträgt 1000 €.',
            'recipientName.required'   => 'Bitte gib an, für wen der Gutschein bestimmt ist (Empfänger-Name).',
            'recipientName.min'        => 'Der Name des Empfängers muss mindestens 2 Zeichen lang sein.',
            'recipientName.max'        => 'Der Name darf maximal 100 Zeichen lang sein.',
            'personalMessage.max'      => 'Die persönliche Grußbotschaft darf maximal 160 Zeichen lang sein.',
            'deliveryMethod.required'  => 'Bitte wähle eine Versandmethode aus.',
            'deliveryMethod.in'        => 'Ungültige Versandmethode.',
            'recipientEmail.required'  => 'Bitte gib die E-Mail-Adresse ein, an die der Gutschein gesendet werden soll.',
            'recipientEmail.email'     => 'Bitte gib eine gültige E-Mail-Adresse ein (z. B. name@beispiel.de).',
        ];
    }

    public function validateStep(int $step): void
    {
        if ($step === 2) {
            $this->recipientName = trim((string) $this->recipientName);
        }
        if ($step === 3 && $this->deliveryMethod === 'email') {
            $this->recipientEmail = trim((string) $this->recipientEmail);
        }

        $this->validate($this->getStepRules($step), $this->getValidationMessages());
    }

    public function nextStep(): void
    {
        $this->validateStep($this->currentStep);

        if ($this->currentStep < 3) {
            $this->currentStep++;
            $this->maxReachedStep = max($this->maxReachedStep, $this->currentStep);
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function goToStep(int $targetStep): void
    {
        if ($targetStep < 1 || $targetStep > 3 || $targetStep === $this->currentStep) {
            return;
        }

        if ($targetStep < $this->currentStep) {
            $this->currentStep = $targetStep;
            return;
        }

        for ($step = $this->currentStep; $step < $targetStep; $step++) {
            try {
                $this->validateStep($step);
                $this->currentStep = $step + 1;
                $this->maxReachedStep = max($this->maxReachedStep, $this->currentStep);
            } catch (ValidationException $e) {
                $this->currentStep = $step;
                throw $e;
            }
        }
    }

    public function setAmount($val)
    {
        $this->resetValidation(['amount', 'customAmount']);

        if ($val === 'custom') {
            $this->amount = 'custom';
            return;
        }

        $intVal = (int) $val;
        if (in_array($intVal, $this->presets, true)) {
            $this->amount = $intVal;
            $this->customAmount = '';
        }
    }

    public function updatedCustomAmount($value)
    {
        if ($value === '' || $value === null) {
            $this->resetValidation('customAmount');
            return;
        }

        $this->validateOnly('customAmount', $this->getStepRules(1), $this->getValidationMessages());
    }

    public function updatedRecipientName($value)
    {
        if (trim((string) $value) === '') {
            $this->resetValidation('recipientName');
            return;
        }

        $this->validateOnly('recipientName', $this->getStepRules(2), $this->getValidationMessages());
    }

    public function updatedPersonalMessage()
    {
        $this->validateOnly('personalMessage', $this->getStepRules(2), $this->getValidationMessages());
    }

    public function updatedDeliveryMethod($value)
    {
        if ($value === 'post') {
            $this->resetValidation('recipientEmail');
        }
    }

    public function updatedRecipientEmail($value)
    {
        if ($this->deliveryMethod !== 'email' || trim((string) $value) === '') {
            $this->resetValidation('recipientEmail');
            return;
        }

        $this->validateOnly('recipientEmail', $this->getStepRules(3), $this->getValidationMessages());
    }

    public function addToCart(CartService $cartService)
    {
        // 1. Schrittweise Validierung über alle 3 Schritte hinweg
        foreach ([1, 2, 3] as $step) {
            try {
                $this->validateStep($step);
            } catch (ValidationException $e) {
                $this->currentStep = $step;
                throw $e;
            }
        }

        // 2. Prepare Config
        $config = [
            'is_gift_voucher'    => true,
            'amount_cents'       => $this->selectedAmountCents,
            'recipient_name'     => trim((string) $this->recipientName),
            'recipient_email'    => $this->deliveryMethod === 'email' ? trim((string) $this->recipientEmail) : null,
            'personal_message'   => trim((string) $this->personalMessage) !== '' ? trim((string) $this->personalMessage) : null,
            'delivery_method'    => $this->deliveryMethod,
            'shipping_surcharge' => $this->deliveryMethod === 'post' ? shop_setting('shipping_cost_voucher', 350) : 0,
        ];

        // 3. Add to Cart
        try {
            $voucherProduct = Product::where('slug', 'geschenkgutschein')->where('status', 'active')->firstOrFail();
            $cartService->addItem($voucherProduct, 1, $config);
            $this->dispatch('cart-updated');
            session()->flash('success', 'Der Gutschein wurde in den Warenkorb gelegt.');
            return redirect()->route('cart');
        } catch (\Exception $e) {
            \Log::error('Voucher purchase add to cart failed: ' . $e->getMessage());
            session()->flash('error', 'Der Gutschein konnte nicht hinzugefügt werden.');
        }
    }

    public function render()
    {
        return view('livewire.shop.marketing.marketing-voucher-purchase-page')
            ->title('Geschenkgutschein kaufen | Mein-Seelenfunke');
    }
}
