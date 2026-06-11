<?php

namespace App\Livewire\Shop\Marketing;

use App\Models\Product\Product;
use App\Services\CartService;
use Livewire\Component;

class MarketingVoucherPurchasePage extends Component
{
    public Product $product;

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
        $product = Product::where('slug', 'geschenkgutschein')->first();
        if (!$product) {
            abort(404, 'Gutschein-Produkt nicht gefunden.');
        }
        $this->product = $product;
    }

    public function getSelectedAmountProperty()
    {
        if ($this->amount === 'custom') {
            return (float) str_replace(',', '.', $this->customAmount);
        }
        return (float) $this->amount;
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

    public function setAmount($val)
    {
        $this->amount = $val;
        if ($val !== 'custom') {
            $this->customAmount = '';
        }
    }

    public function updatedCustomAmount($value)
    {
        if ($value === '' || $value === null) {
            $this->resetValidation('customAmount');
            return;
        }

        $this->validateOnly('customAmount', [
            'customAmount' => [
                'numeric',
                'min:5',
                'max:1000',
                function ($attribute, $value, $fail) {
                    $val = (float) str_replace(',', '.', $value);
                    if (fmod($val, 5.0) != 0.0) {
                        $fail('Der Wunschbetrag muss in 5er Schritten eingegeben werden (z. B. 15, 20, 25, ...).');
                    }
                }
            ]
        ], [
            'customAmount.numeric' => 'Der Wunschbetrag muss eine Zahl sein.',
            'customAmount.min' => 'Der Mindestwert beträgt 5 €.',
            'customAmount.max' => 'Der Maximalwert beträgt 1000 €.',
        ]);
    }

    public function addToCart(CartService $cartService)
    {
        // 1. Validation
        $rules = [
            'recipientName' => 'required|string|min:2|max:100',
            'personalMessage' => 'nullable|string|max:160',
            'deliveryMethod' => 'required|in:email,post',
        ];

        if ($this->amount === 'custom') {
            $rules['customAmount'] = [
                'required',
                'numeric',
                'min:5',
                'max:1000',
                function ($attribute, $value, $fail) {
                    $val = (float) str_replace(',', '.', $value);
                    if (fmod($val, 5.0) != 0.0) {
                        $fail('Der Wunschbetrag muss in 5er Schritten eingegeben werden (z. B. 15, 20, 25, ...).');
                    }
                }
            ];
        } else {
            $rules['amount'] = 'required|integer|in:10,25,50,100';
        }

        if ($this->deliveryMethod === 'email') {
            $rules['recipientEmail'] = 'required|email|max:255';
        }

        $this->validate($rules, [
            'recipientName.required' => 'Bitte gib den Namen des Empfängers an.',
            'recipientName.min' => 'Der Name muss mindestens 2 Zeichen lang sein.',
            'recipientEmail.required' => 'Bitte gib die E-Mail-Adresse für den Gutscheinversand an.',
            'recipientEmail.email' => 'Die E-Mail-Adresse ist ungültig.',
            'personalMessage.max' => 'Die Grußbotschaft darf maximal 160 Zeichen lang sein.',
            'customAmount.required' => 'Bitte gib deinen Wunschbetrag an.',
            'customAmount.numeric' => 'Der Wunschbetrag muss eine Zahl sein.',
            'customAmount.min' => 'Der Mindestwert beträgt 5 €.',
            'customAmount.max' => 'Der Maximalwert beträgt 1000 €.',
        ]);

        // 2. Prepare Config
        $config = [
            'is_gift_voucher' => true,
            'amount_cents' => $this->selectedAmountCents,
            'recipient_name' => $this->recipientName,
            'recipient_email' => $this->deliveryMethod === 'email' ? $this->recipientEmail : null,
            'personal_message' => $this->personalMessage ?: null,
            'delivery_method' => $this->deliveryMethod,
            'shipping_surcharge' => $this->deliveryMethod === 'post' ? shop_setting('shipping_cost_voucher', 350) : 0,
        ];

        // 3. Add to Cart
        try {
            $cartService->addItem($this->product, 1, $config);
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
