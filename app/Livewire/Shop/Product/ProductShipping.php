<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\Product;
use Livewire\Component;

class ProductShipping extends Component
{
    public Product $product;

    public $weight;
    public $height;
    public $width;
    public $length;
    public $shipping_class;

    public $infoTexts = [
        'weight' => 'Das Gewicht des verpackten Produkts in Gramm. Entscheidend für die automatische Versandkostenberechnung.',
        'shipping_class' => 'Ordnet das Produkt einer Versandkategorie zu (z.B. für Sperrgut-Zuschläge oder Briefversand).',
        'dimensions' => 'Die Außenmaße der Verpackung in Millimetern. Wichtig für die Auswahl der Paketgröße.',
    ];

    protected $rules = [
        'weight' => 'nullable|integer|min:0',
        'height' => 'nullable|integer|min:0',
        'width' => 'nullable|integer|min:0',
        'length' => 'nullable|integer|min:0',
        'shipping_class' => 'nullable|string|max:255',
    ];

    public function mount(Product $product)
    {
        $this->product = $product;

        // Initialisieren der Werte aus dem Model
        $this->fill([
            'weight' => $product->weight,
            'height' => $product->height,
            'width' => $product->width,
            'length' => $product->length,
            'shipping_class' => $product->shipping_class,
        ]);
    }

    // --- Live Updates: Speichern sofort bei Änderung (Blur) ---

    public function updatedWeight($value)
    {
        $this->validateOnly('weight');
        // Cast auf Integer oder null sicherstellen
        $val = ($value === '' || $value === null) ? null : (int) $value;
        $this->product->update(['weight' => $val]);
    }

    public function updatedHeight($value)
    {
        $this->validateOnly('height');
        $val = ($value === '' || $value === null) ? null : (int) $value;
        $this->product->update(['height' => $val]);
    }

    public function updatedWidth($value)
    {
        $this->validateOnly('width');
        $val = ($value === '' || $value === null) ? null : (int) $value;
        $this->product->update(['width' => $val]);
    }

    public function updatedLength($value)
    {
        $this->validateOnly('length');
        $val = ($value === '' || $value === null) ? null : (int) $value;
        $this->product->update(['length' => $val]);
    }

    public function updatedShippingClass($value)
    {
        $this->validateOnly('shipping_class');
        $this->product->update(['shipping_class' => $value === '' ? null : $value]);
    }

    public function render()
    {
        return view('livewire.shop.product.product-shipping');
    }
}
