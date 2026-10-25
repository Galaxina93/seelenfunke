<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use App\Services\ProductTrackingService;
use Illuminate\Support\Str;
use Livewire\Component;

class ProductShow extends Component
{
    #[\Livewire\Attributes\Locked]
    public Product $product;
    public $showTemplateSelection = false;
    public $showTemplatesList = false;
    #[\Livewire\Attributes\Locked]
    public $productTemplates = [];
    public $currentConfig = [];

    public function mount(Product $product)
    {
        if ($product->status !== 'active') {
            abort(404);
        }

        $this->product = $product;

        // Telemetrie: Seitenaufruf Konfigurator
        ProductTrackingService::trackConfiguratorView($this->product->id);

        $templates = ProductTemplate::where('product_id', $this->product->id)->where('is_active', true)->get();

        if ($templates->isNotEmpty()) {
            $this->productTemplates = $templates->toArray();

            // Template Projection Deep-Link Check
            $requestedTemplateId = request()->query('t');
            if ($requestedTemplateId) {
                $matchingTemplate = $templates->firstWhere('id', $requestedTemplateId);
                if ($matchingTemplate) {
                    $this->selectTemplate($matchingTemplate->id);
                    return;
                }
            }

            $this->showTemplateSelection = true;
        }
    }

    public function openTemplatesList()
    {
        ProductTrackingService::trackModeChosen($this->product->id, 'template');
        $this->showTemplateSelection = false;
        $this->showTemplatesList = true;
    }

    public function startCustomConfig()
    {
        ProductTrackingService::trackModeChosen($this->product->id, 'custom');
        $this->showTemplateSelection = false;
        $this->showTemplatesList = false;
        $this->currentConfig = [];
    }

    public function selectTemplate($templateId)
    {
        $template = ProductTemplate::where('product_id', $this->product->id)
            ->where('is_active', true)
            ->find($templateId);
        if ($template) {
            $config = $template->configuration ?? [];
            if (empty($config['background_theme'])) {
                $config['background_theme'] = app(\App\Services\ConfiguratorService::class)
                    ->resolveThemeKey($template->holiday ?? $template->name);
            }
            $config['holiday'] = $template->holiday;
            $config['template_name'] = $template->name;
            $this->currentConfig = $config;
            ProductTrackingService::trackTemplateSelected($this->product->id, $templateId, $template->name ?? $template->title ?? ('Vorlage #' . $templateId));

            $tplVariantId = $config['variant_id'] ?? null;
            $tplPriceCents = $this->product->price;
            if ($tplVariantId) {
                $matchedVar = collect($this->product->variants_data ?? [])->firstWhere('id', $tplVariantId);
                if ($matchedVar && isset($matchedVar['price']) && $matchedVar['price'] !== '') {
                    $tplPriceCents = (int) round((float) $matchedVar['price'] * 100);
                }
            }
            $tplImgUrl = !empty($template->preview_image)
                ? ProductTemplate::resolveImageUrl($template->preview_image)
                : ($tplVariantId ? ($this->product->resolveVariantImageMeta($tplVariantId, $template->holiday ?: null)['url'] ?? null) : null);

            $this->dispatch('variant-selected',
                variantId: $tplVariantId,
                variantName: $config['variant_name'] ?? null,
                formattedPrice: number_format($tplPriceCents / 100, 2, ',', '.') . ' €',
                imageUrl: $tplImgUrl,
                imageIsAi: (bool) ($template->is_ai_generated ?? true),
                imageLabel: $template->name
            );
        } else {
            $this->currentConfig = [];
        }
        $this->showTemplateSelection = false;
        $this->showTemplatesList = false;
    }

    public function cancelConfig()
    {
        ProductTrackingService::trackConfiguratorAbandoned($this->product->id, ['action' => 'canceled_to_templates']);
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
