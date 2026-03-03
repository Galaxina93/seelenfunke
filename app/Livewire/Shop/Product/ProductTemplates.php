<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductTemplates extends Component
{
    use WithFileUploads;

    // Steuert die aktuelle Ansicht ('list', 'create_select_product', 'configure')
    public $viewMode = 'list';

    // Formulardaten für die Vorlage
    public $selectedProductId = null;
    public $templateName = '';
    public $templateIsActive = true;
    public $templateConfig = [];
    public $editingTemplateId = null;
    public $templateImage;

    public function cancel()
    {
        $this->resetForm();
        $this->viewMode = 'list';
    }

    public function createNew()
    {
        $this->resetForm();
        $this->viewMode = 'create_select_product';
    }

    public function selectProduct($productId)
    {
        $this->selectedProductId = $productId;
        $this->viewMode = 'configure';
    }

    public function edit($templateId)
    {
        $template = ProductTemplate::findOrFail($templateId);

        $this->editingTemplateId = $template->id;
        $this->selectedProductId = $template->product_id;
        $this->templateName = $template->name;
        $this->templateIsActive = $template->is_active;
        $this->templateConfig = $template->configuration ?? [];
        $this->templateImage = null;

        $this->viewMode = 'configure';
    }

    public function delete($templateId)
    {
        $template = ProductTemplate::findOrFail($templateId);

        // HIER IST DER FIX: Lösche das Bild NUR, wenn es explizit für die Vorlage hochgeladen wurde
        // und nicht das Fallback-Bild des Hauptprodukts ist!
        if ($template->preview_image && Str::startsWith($template->preview_image, 'product-templates/')) {
            Storage::disk('public')->delete($template->preview_image);
        }

        $template->delete();
    }

    public function toggleActive($templateId)
    {
        $template = ProductTemplate::findOrFail($templateId);
        $template->update([
            'is_active' => !$template->is_active
        ]);
    }

    #[On('save-template-data')]
    public function handleTemplateSaved($configData, $previewImagePath = null)
    {
        $this->templateConfig = $configData;

        $this->validate([
            'templateName' => 'required|string|max:255',
            'templateImage' => 'nullable|image|max:5120',
        ], [
            'templateName.required' => 'Bitte geben Sie der Vorlage einen Namen.',
            'templateImage.image' => 'Die Datei muss ein gültiges Bildformat sein.',
            'templateImage.max' => 'Das Bild darf maximal 5MB groß sein.',
        ]);

        $template = ProductTemplate::findOrNew($this->editingTemplateId);
        $template->product_id = $this->selectedProductId;
        $template->name = $this->templateName;
        $template->configuration = $configData;
        $template->is_active = $this->templateIsActive;

        // Bild-Verarbeitung
        if ($this->templateImage) {
            // HIER AUCH DER FIX: Altes Bild nur löschen, wenn es ein Vorlagen-Bild ist
            if ($template->exists && $template->preview_image && Str::startsWith($template->preview_image, 'product-templates/')) {
                Storage::disk('public')->delete($template->preview_image);
            }
            $template->preview_image = $this->templateImage->store('product-templates', 'public');
        } elseif ($previewImagePath && !$template->exists) {
            $template->preview_image = $previewImagePath;
        }

        $template->save();

        $this->cancel();
    }

    private function resetForm()
    {
        $this->selectedProductId = null;
        $this->templateName = '';
        $this->templateIsActive = true;
        $this->templateConfig = [];
        $this->editingTemplateId = null;
        $this->templateImage = null;
        $this->resetErrorBag();
    }

    public function render()
    {
        $templates = ProductTemplate::with('product')->orderBy('created_at', 'desc')->get();
        $products = Product::where('status', 'active')->get();

        return view('livewire.shop.product.product-templates', [
            'templates' => $templates,
            'products' => $products,
        ]);
    }
}
