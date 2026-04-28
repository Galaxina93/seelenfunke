<?php

namespace App\Livewire\Shop\Marketing;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Product\Product;
use App\Models\Marketing\MarketingLandingPage;
use Illuminate\Support\Str;

#[Layout('components.layouts.backend_layout')]
class MarketingLandingPages extends Component
{
    use \App\Livewire\Traits\WithDepartmentTheming;

    public $themingDepartment = 'Marketing';
    public $loadingMessage = '';
    public $actionError = '';

    private function getDefaultCtaText(Product $product): string
    {
        if ($product->isPersonalizable()) {
            return 'Jetzt dein Unikat gestalten';
        }

        return match ($product->type) {
            'digital' => 'Jetzt digitalen Download ansehen',
            'service' => 'Jetzt Dienstleistung buchen',
            default => 'Jetzt zum Produkt', // Physisch aber nicht personalisierbar
        };
    }

    public function generateLandingPage($productId)
    {
        $this->doGenerate($productId, false);
    }

    public function regenerateLandingPage($productId)
    {
        $this->doGenerate($productId, true);
    }

    private function doGenerate($productId, $isRebuild)
    {
        $this->actionError = '';
        try {
            $product = Product::findOrFail($productId);

            if (!$isRebuild && $product->landingPage) {
                $this->actionError = 'Für dieses Produkt existiert bereits eine Landing Page.';
                return;
            }

            $this->loadingMessage = ($isRebuild ? 'Baue Layout-Datei neu auf für ' : 'Erstelle Layout-Datei für ') . $product->name . '...';

            $slug = $product->slug;
            $bladePath = resource_path('views/frontend/pages/landingpages/' . $slug . '.blade.php');

            // Generate the boilerplate content from the stub
            $stubPath = resource_path('views/stubs/landing-page-template.stub');
            $content = \Illuminate\Support\Facades\File::get($stubPath);

            if (!\Illuminate\Support\Facades\File::exists(dirname($bladePath))) {
                \Illuminate\Support\Facades\File::makeDirectory(dirname($bladePath), 0755, true);
            }
            \Illuminate\Support\Facades\File::put($bladePath, $content);

            // Speichern
            MarketingLandingPage::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'slug' => $slug,
                    'title' => $product->name,
                    'headline' => 'Entdecke die Magie von ' . $product->name,
                    'sales_copy' => $product->description,
                    'cta_text' => $this->getDefaultCtaText($product),
                    'status' => 'active',
                ]
            );

            $this->actionError = '';
            $this->loadingMessage = '';
            $msg = $isRebuild ? 'Landing Page für ' . $product->name . ' komplett neu generiert!' : 'Landing Page für ' . $product->name . ' erstellt! (Individuelles Blade)';
            $this->dispatch('notify', ['type' => 'success', 'message' => $msg]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("LandingPage Generation Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->actionError = 'Systemfehler: ' . $e->getMessage();
            $this->loadingMessage = '';
        }
    }

    public function render()
    {
        $products = Product::with('landingPage')->where('status', 'active')->get();

        return view('livewire.shop.marketing.marketing-landing-pages', [
            'products' => $products
        ]);
    }
}
