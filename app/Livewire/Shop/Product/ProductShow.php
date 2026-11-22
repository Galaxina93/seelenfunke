<?php

namespace App\Livewire\Shop\Product;

use App\Livewire\Shop\Product\Traits\ManagesConfiguratorStartSelection;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use App\Services\ProductTrackingService;
use Illuminate\Support\Str;
use Livewire\Component;

class ProductShow extends Component
{
    use ManagesConfiguratorStartSelection;

    #[\Livewire\Attributes\Locked]
    public Product $product;

    public function mount(Product $product)
    {
        if ($product->status !== 'active') {
            abort(404);
        }

        $this->product = $product;

        // Telemetrie: Seitenaufruf Konfigurator
        ProductTrackingService::trackConfiguratorView($this->product->id);

        // Startauswahl ("Neuling -> Anfänger -> Profi") zentral initialisieren, wenn das Produkt den Studio-Konfigurator nutzt
        if ($this->product->usesStudioConfigurator()) {
            $requestedTemplateId = request()->query('t');
            $this->initStartSelectionForStudioProduct(
                is_string($requestedTemplateId) && $requestedTemplateId !== '' ? $requestedTemplateId : null
            );
        }
    }

    protected function resolveStartSelectionProductId(): ?string
    {
        return isset($this->product) ? (string) $this->product->id : null;
    }

    protected function afterStartModeTransition(string $mode, ?ProductTemplate $selectedTemplate = null): void
    {
        if ($mode === 'custom') {
            $mainMeta = $this->product->resolveMainMediaMeta(null, $this->product->isAutoSeasonalMainImageEnabled());
            if (!empty($mainMeta['url']) && ($mainMeta['type'] ?? 'image') === 'image') {
                $this->dispatch('variant-selected',
                    variantId: null,
                    variantName: null,
                    formattedPrice: null,
                    imageUrl: $mainMeta['url'],
                    imageIsAi: (bool) ($mainMeta['is_ai_generated'] ?? true),
                    imageLabel: $mainMeta['label'] ?? $this->product->name
                );
            }
        } elseif ($mode === 'template' && $selectedTemplate) {
            $config = $this->currentConfig ?? [];
            $tplVariantId = $config['variant_id'] ?? null;
            $tplPriceCents = (int) $this->product->price;
            if ($tplVariantId) {
                $matchedVar = collect($this->product->variants_data ?? [])->firstWhere('id', $tplVariantId);
                if ($matchedVar && isset($matchedVar['price']) && $matchedVar['price'] !== '') {
                    $tplPriceCents += (int) round(((float) str_replace(',', '.', (string) $matchedVar['price'])) * 100);
                }
            }
            if ($this->product->tax_included === false && !app(\App\Services\Tax\TaxSettingService::class)->isCurrentSmallBusiness()) {
                $taxRate = (float) ($this->product->tax_rate ?? shop_setting('default_tax_rate', 19.0));
                $tplPriceCents = (int) round($tplPriceCents * (1 + ($taxRate / 100)));
            }
            $tplImgUrl = !empty($selectedTemplate->preview_image)
                ? ProductTemplate::resolveImageUrl($selectedTemplate->preview_image)
                : ($tplVariantId ? ($this->product->resolveVariantImageMeta($tplVariantId, $selectedTemplate->holiday ?: null)['url'] ?? null) : null);

            $this->dispatch('variant-selected',
                variantId: $tplVariantId,
                variantName: $config['variant_name'] ?? null,
                formattedPrice: number_format($tplPriceCents / 100, 2, ',', '.') . ' €',
                imageUrl: $tplImgUrl,
                imageIsAi: (bool) ($selectedTemplate->is_ai_generated ?? true),
                imageLabel: $selectedTemplate->name
            );
        }

        $this->dispatch('scroll-to-config-stage');
    }

    public function cancelConfig()
    {
        ProductTrackingService::trackConfiguratorAbandoned($this->product->id, ['action' => 'canceled_to_templates']);
        if ($this->product->usesStudioConfigurator() || !empty($this->productTemplates)) {
            $this->currentConfig = [];
            $this->showTemplateSelection = true;
            $this->showTemplatesList = false;

            $mainMeta = $this->product->resolveMainMediaMeta(null, $this->product->isAutoSeasonalMainImageEnabled());
            if (!empty($mainMeta['url']) && ($mainMeta['type'] ?? 'image') === 'image') {
                $this->dispatch('variant-selected',
                    variantId: null,
                    variantName: null,
                    formattedPrice: null,
                    imageUrl: $mainMeta['url'],
                    imageIsAi: (bool) ($mainMeta['is_ai_generated'] ?? true),
                    imageLabel: $mainMeta['label'] ?? $this->product->name
                );
            }

            $this->dispatch('scroll-to-config');
        }
    }

    public function toggleWishlist(\App\Services\WishlistService $wishlistService, ?bool $shouldBeWishlisted = null): void
    {
        $wishlistService->setWishlisted((string) $this->product->id, $shouldBeWishlisted);
        $this->dispatch(
            'wishlist-updated',
            count: $wishlistService->getCount(),
            productIds: $wishlistService->getProductIds()
        );
    }

    public function render()
    {
        $seoTitle = $this->product->seo_title ?: ($this->product->name . ' · Personalisiert mit Lasergravur');
        $rawDesc = $this->product->seo_description ?: ($this->product->short_description ?: $this->product->description);
        $seoDescription = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $rawDesc))), 160);
        $mainImageUrl = $this->product->resolveSeoMainImageMeta()['url']
            ?: asset('shop/projekt/logo/mein-seelenfunke-logo.png');
        $canonicalUrl = route('product.show', $this->product->slug);

        $productSchema = $this->product->buildStructuredDataSchema($canonicalUrl);
        $isWishlisted = app(\App\Services\WishlistService::class)->has((string) $this->product->id);

        return view('livewire.shop.product.product-show', [
            'productSchema' => $productSchema,
            'mainImageUrl' => $mainImageUrl,
            'isWishlisted' => $isWishlisted,
        ])
            ->title($seoTitle)
            ->layout('components.layouts.frontend_layout', [
                'meta_description' => $seoDescription,
                'og_title' => $seoTitle,
                'og_description' => $seoDescription,
                'og_image' => $mainImageUrl,
                'og_type' => 'product',
                'canonical' => $canonicalUrl,
            ]);
    }
}
