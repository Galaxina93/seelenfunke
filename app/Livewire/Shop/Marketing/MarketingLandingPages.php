<?php

namespace App\Livewire\Shop\Marketing;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Product\Product;
use App\Models\Marketing\MarketingLandingPage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

#[Layout('components.layouts.backend_layout')]
class MarketingLandingPages extends Component
{
    use \App\Livewire\Traits\WithDepartmentTheming;

    public $themingDepartment = 'Marketing';
    public $loadingMessage = '';
    public $actionError = '';

    // Modal state for editing
    public bool $showEditModal = false;
    public ?string $editingLandingPageId = null;
    public string $editTitle = '';
    public string $editHeadline = '';
    public string $editSalesCopy = '';
    public string $editCtaText = '';
    public string $editStatus = 'active';

    protected function rules(): array
    {
        return [
            'editTitle' => 'required|string|max:255',
            'editHeadline' => 'nullable|string|max:255',
            'editSalesCopy' => 'nullable|string|max:5000',
            'editCtaText' => 'nullable|string|max:100',
            'editStatus' => 'required|in:active,draft',
        ];
    }

    private function getDefaultCtaText(Product $product): string
    {
        if ($product->isPersonalizable()) {
            return 'Jetzt dein Unikat gestalten';
        }

        return match ($product->type) {
            'digital' => 'Jetzt digitalen Download ansehen',
            'service' => 'Jetzt Dienstleistung buchen',
            default => 'Jetzt zum Produkt',
        };
    }

    /**
     * Activates or creates a dynamic landing page (central master-template, no redundant files).
     */
    public function generateLandingPage($productId)
    {
        $this->actionError = '';
        try {
            $product = Product::findOrFail($productId);

            MarketingLandingPage::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'slug' => $product->slug,
                    'title' => $product->name,
                    'headline' => 'Entdecke die Magie von ' . $product->name,
                    'sales_copy' => $product->description,
                    'cta_text' => $this->getDefaultCtaText($product),
                    'status' => 'active',
                ]
            );

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Landing Page für ' . $product->name . ' erfolgreich aktiviert!'
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("LandingPage Activation Error: " . $e->getMessage());
            $this->actionError = 'Fehler beim Aktivieren: ' . $e->getMessage();
        }
    }

    /**
     * Opens the edit modal for a specific landing page.
     */
    public function openEditModal($landingPageId)
    {
        $lp = MarketingLandingPage::findOrFail($landingPageId);
        $this->editingLandingPageId = $lp->id;
        $this->editTitle = $lp->title ?: '';
        $this->editHeadline = $lp->headline ?: '';
        $this->editSalesCopy = $lp->sales_copy ?: '';
        $this->editCtaText = $lp->cta_text ?: '';
        $this->editStatus = $lp->status ?: 'active';
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingLandingPageId = null;
        $this->resetValidation();
    }

    /**
     * Saves the modified landing page copy and status.
     */
    public function saveEdit()
    {
        $this->validate();

        if (!$this->editingLandingPageId) {
            return;
        }

        $lp = MarketingLandingPage::findOrFail($this->editingLandingPageId);
        $lp->update([
            'title' => $this->editTitle,
            'headline' => $this->editHeadline,
            'sales_copy' => $this->editSalesCopy,
            'cta_text' => $this->editCtaText,
            'status' => $this->editStatus,
        ]);

        $this->closeEditModal();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Landing Page Inhalte erfolgreich aktualisiert!'
        ]);
    }

    /**
     * Toggles between active and draft status.
     */
    public function toggleStatus($landingPageId)
    {
        $lp = MarketingLandingPage::findOrFail($landingPageId);
        $lp->status = ($lp->status === 'active') ? 'draft' : 'active';
        $lp->save();

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Status auf ' . ($lp->status === 'active' ? 'Aktiv' : 'Entwurf') . ' gesetzt.'
        ]);
    }

    /**
     * Deletes a landing page and any custom override blade file.
     */
    public function deleteLandingPage($landingPageId)
    {
        $lp = MarketingLandingPage::findOrFail($landingPageId);
        $productName = $lp->product ? $lp->product->name : $lp->title;

        // Delete custom blade file if one exists
        $safeSlug = Str::slug($lp->slug);
        $customBlade = resource_path('views/frontend/pages/landingpages/' . $safeSlug . '.blade.php');
        if (File::exists($customBlade)) {
            File::delete($customBlade);
        }

        $lp->delete();

        $this->dispatch('notify', [
            'type' => 'warning',
            'message' => 'Landing Page für ' . $productName . ' wurde gelöscht.'
        ]);
    }

    /**
     * Optional advanced feature: creates an individual blade override file if developer wants custom HTML.
     */
    public function createCustomOverrideFile($productId)
    {
        try {
            $product = Product::findOrFail($productId);
            $slug = Str::slug($product->slug);
            $bladePath = resource_path('views/frontend/pages/landingpages/' . $slug . '.blade.php');

            $stubPath = resource_path('views/stubs/landing-page-template.stub');
            $content = File::exists($stubPath) ? File::get($stubPath) : '';

            if (!File::exists(dirname($bladePath))) {
                File::makeDirectory(dirname($bladePath), 0755, true);
            }
            File::put($bladePath, $content);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Individuelle Blade-Datei unter /landingpages/' . $slug . '.blade.php angelegt!'
            ]);
        } catch (\Exception $e) {
            $this->actionError = 'Fehler beim Erstellen der Datei: ' . $e->getMessage();
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
