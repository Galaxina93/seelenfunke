<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use Illuminate\Support\Str;
use Livewire\Component;

class ProductShow extends Component
{
    public Product $product;
    public $showTemplateSelection = false;
    public $showTemplatesList = false;
    public $productTemplates = [];
    public $currentConfig = [];

    public function mount(Product $product)
    {
        if ($product->status !== 'active') {
            abort(404);
        }

        $this->product = $product;

        $templates = ProductTemplate::where('product_id', $this->product->id)->where('is_active', true)->get();

        if ($templates->isNotEmpty()) {
            $this->productTemplates = $templates->toArray();
            $this->showTemplateSelection = true;
        }
    }

    public function openTemplatesList()
    {
        $this->showTemplateSelection = false;
        $this->showTemplatesList = true;
    }

    public function startCustomConfig()
    {
        $this->showTemplateSelection = false;
        $this->showTemplatesList = false;
        $this->currentConfig = [];
    }

    public function selectTemplate($templateId)
    {
        $template = ProductTemplate::find($templateId);
        if ($template) {
            $this->currentConfig = $template->configuration ?? [];
        } else {
            $this->currentConfig = [];
        }
        $this->showTemplateSelection = false;
        $this->showTemplatesList = false;
    }

    public function cancelConfig()
    {
        if (!empty($this->productTemplates)) {
            $this->currentConfig = [];
            $this->showTemplateSelection = true;
            $this->showTemplatesList = false;
            $this->dispatch('scroll-to-config');
        }
    }

    public function render()
    {
        return view('livewire.shop.product.product-show')
            ->title($this->product->seo_title ?? $this->product->name)
            ->with(['meta_description' => $this->product->seo_description ?? Str::limit($this->product->short_description, 160)])
            ->layout('components.layouts.frontend_layout');
    }
}
