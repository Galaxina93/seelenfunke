<?php

namespace App\Livewire\Shop;

use App\Models\Product;
use Livewire\Component;

class ProductShipping extends Component
{
    public Product $product;

    public $is_physical_product;
    public $weight;
    public $height;
    public $width;
    public $length;
    public $shipping_class;

    public $infoTexts = [
        'is_physical' => 'Legt fest, ob das Produkt versendet werden muss. Deaktivieren für digitale Güter oder Dienstleistungen.',
        'weight' => 'Das Gewicht des verpackten Produkts in Gramm. Entscheidend für die automatische Versandkostenberechnung.',
        'shipping_class' => 'Ordnet das Produkt einer Versandkategorie zu (z.B. für Sperrgut-Zuschläge oder Briefversand).',
        'dimensions' => 'Die Außenmaße der Verpackung in Millimetern. Wichtig für die Auswahl der Paketgröße.',
    ];

    protected $rules = [
        'is_physical_product' => 'boolean',
        'weight' => 'nullable|integer|min:0',
        'height' => 'nullable|integer|min:0',
        'width' => 'nullable|integer|min:0',
        'length' => 'nullable|integer|min:0',
        'shipping_class' => 'nullable|string|max:255',
    ];

    public function mount(Product $product)
    {
        $this->product = $product;
        $this->fill([
            'is_physical_product' => $product->is_physical_product,
            'weight' => $product->weight,
            'height' => $product->height,
            'width' => $product->width,
            'length' => $product->length,
            'shipping_class' => $product->shipping_class,
        ]);
    }

    // --- Explizite Update Methoden für mehr Stabilität ---

    public function updatedIsPhysicalProduct($value)
    {
        $this->product->update(['is_physical_product' => (bool) $value]);
    }

    public function updatedWeight($value)
    {
        $this->validateOnly('weight');
        $this->product->update(['weight' => $value === '' ? null : $value]);
    }

    public function updatedHeight($value)
    {
        $this->validateOnly('height');
        $this->product->update(['height' => $value === '' ? null : $value]);
    }

    public function updatedWidth($value)
    {
        $this->validateOnly('width');
        $this->product->update(['width' => $value === '' ? null : $value]);
    }

    public function updatedLength($value)
    {
        $this->validateOnly('length');
        $this->product->update(['length' => $value === '' ? null : $value]);
    }

    public function updatedShippingClass($value)
    {
        $this->validateOnly('shipping_class');
        $this->product->update(['shipping_class' => $value === '' ? null : $value]);
    }

    public function render()
    {
        return view('livewire.shop.product-shipping');
    }
}
