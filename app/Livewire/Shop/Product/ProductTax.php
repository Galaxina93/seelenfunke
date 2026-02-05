<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ProductTax extends Component
{
    public Product $product;

    // Nur noch die Klasse ist variabel pro Produkt
    public $tax_class;

    // Anzeige-Variable
    public $current_tax_rate = 0.00;

    public $infoTexts = [
        'tax' => 'Informationen zur steuerlichen Behandlung dieses Produkts.',
        'tax_class' => 'Bestimmt den anzuwendenden Steuersatz (z.B. 19% Standard oder 7% Ermäßigt).',
    ];

    public function mount(Product $product)
    {
        $this->product = $product;
        $this->tax_class = $product->tax_class ?? 'standard';
        $this->updateCurrentRate();
    }

    public function updatedTaxClass($value)
    {
        $this->product->update(['tax_class' => $value]);
        $this->updateCurrentRate();

        // Sagt der Eltern-Komponente (ProductCreate), dass sich was geändert hat
        $this->dispatch('product-updated');
    }

    protected function updateCurrentRate()
    {
        // Holt den %-Satz passend zur gewählten Klasse (z.B. "standard" -> 19.00)
        $rate = DB::table('tax_rates')
            ->where('tax_class', $this->tax_class)
            ->where('is_default', true)
            ->value('rate');

        $this->current_tax_rate = $rate ? (float)$rate : 19.00;
    }

    public function render()
    {
        return view('livewire.shop.product.product-tax');
    }
}
