<?php

namespace App\Livewire\Shop\Product;

use Livewire\Attributes\Layout;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Livewire\Traits\WithDepartmentTheming;

#[Layout('components.layouts.backend_layout')]
class ProductTemplates extends Component
{
    use WithFileUploads, WithDepartmentTheming;

    public string $themingDepartment = 'Produkte';

    // Steuert die aktuelle Ansicht ('list', 'create_select_product', 'configure')
    public $viewMode = 'list';
    public string $layoutMode = 'large'; // 'large' (Groß), 'medium' (Mittel), 'list' (Listenansicht)
    public bool $showArchived = false;
    public string $search = '';
    public string $filterProductId = '';
    public string $filterHoliday = '';

    // Formulardaten für die Vorlage
    #[\Livewire\Attributes\Locked]
    public $selectedProductId = null;
    public $templateName = '';
    public $templateIsActive = true;
    public bool $templateIsAiGenerated = true;
    public $templateHoliday = '';
    public $templateConfig = [];
    #[\Livewire\Attributes\Locked]
    public $editingTemplateId = null;
    public $templateImage;
    #[\Livewire\Attributes\Locked]
    public $existingTemplateImagePath = null;
    public $removeExistingImage = false;

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function setLayoutMode(string $mode): void
    {
        if (in_array($mode, ['large', 'medium', 'list'], true)) {
            $this->layoutMode = $mode;
        }
    }

    public function toggleArchiveMode(): void
    {
        $this->showArchived = !$this->showArchived;
        $this->search = '';
    }

    public function archiveTemplate($templateId): void
    {
        $template = ProductTemplate::find($templateId);
        if ($template) {
            $template->is_active = false;
            $template->save();
            $template->delete(); // Soft delete -> landet im Archiv
            session()->flash('success', 'Vorlage ins Archiv verschoben.');
        }
    }

    public function restoreTemplate($templateId): void
    {
        $template = ProductTemplate::withTrashed()->find($templateId);
        if ($template) {
            $template->restore();
            $template->is_active = true;
            $template->save();
            session()->flash('success', 'Vorlage aus dem Archiv als aktiv wiederhergestellt.');
        }
    }

    public function forceDeleteTemplate($templateId): void
    {
        $template = ProductTemplate::withTrashed()->findOrFail($templateId);

        if ($template->preview_image && Str::startsWith($template->preview_image, 'produkte/product-templates/')) {
            // Nur löschen, wenn keine andere Vorlage denselben Bildpfad nutzt
            $otherUses = ProductTemplate::withTrashed()
                ->where('id', '!=', $template->id)
                ->where('preview_image', $template->preview_image)
                ->exists();
            if (!$otherUses) {
                Storage::disk('public')->delete($template->preview_image);
            }
        }

        $template->forceDelete();
        session()->flash('success', 'Vorlage endgültig gelöscht.');
    }

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
        if (!Product::where('id', $productId)->exists()) {
            return;
        }
        $this->selectedProductId = $productId;
        $this->viewMode = 'configure';
    }

    public function edit($templateId)
    {
        $template = ProductTemplate::withTrashed()->findOrFail($templateId);

        $this->editingTemplateId = $template->id;
        $this->selectedProductId = $template->product_id;
        $this->templateName = $template->name;
        $this->templateIsActive = $template->is_active;
        $this->templateIsAiGenerated = (bool) ($template->is_ai_generated ?? true);
        $this->templateHoliday = $template->holiday ?? '';
        $this->templateConfig = $template->configuration ?? [];
        $this->templateImage = null;
        $this->existingTemplateImagePath = $template->preview_image;
        $this->removeExistingImage = false;

        $this->viewMode = 'configure';
    }

    public function delete($templateId)
    {
        $this->archiveTemplate($templateId);
    }

    public function toggleActive($templateId)
    {
        $template = ProductTemplate::findOrFail($templateId);
        $template->update([
            'is_active' => !$template->is_active,
        ]);
    }

    public function updateStatus($templateId, string $status): void
    {
        $template = ProductTemplate::find($templateId);
        if ($template) {
            $template->update([
                'is_active' => $status === 'active',
            ]);
        }
    }

    public function toggleAiGenerated($templateId): void
    {
        $template = ProductTemplate::withTrashed()->find($templateId);
        if ($template) {
            $template->update([
                'is_ai_generated' => !$template->is_ai_generated,
            ]);
        }
    }

    public function removePreviewImage()
    {
        $this->removeExistingImage = true;
        $this->existingTemplateImagePath = null;
    }

    #[On('save-template-data')]
    public function handleTemplateSaved($configData, $previewImagePath = null)
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $product = $this->selectedProductId ? Product::find($this->selectedProductId) : null;
        if (!$product) {
            $this->addError('templateName', 'Bitte wählen Sie ein gültiges Produkt aus.');
            return;
        }
        if (is_array($configData)) {
            $configData = app(\App\Services\CartService::class)->sanitizeConfiguration($product, $configData) ?? $configData;
        }
        $this->templateConfig = is_array($configData) ? $configData : [];

        $this->validate([
            'templateName' => 'required|string|max:255',
            'templateHoliday' => 'nullable|string|max:100',
            'templateImage' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'templateName.required' => 'Bitte geben Sie der Vorlage einen Namen.',
            'templateImage.image' => 'Die Datei muss ein gültiges Bildformat sein.',
            'templateImage.max' => 'Das Bild darf maximal 5MB groß sein.',
        ]);

        $template = $this->editingTemplateId
            ? ProductTemplate::withTrashed()->findOrNew($this->editingTemplateId)
            : new ProductTemplate();

        $template->product_id = $product->id;
        $template->name = $this->templateName;
        $template->configuration = $this->templateConfig;
        $template->is_active = (bool) $this->templateIsActive;
        $template->is_ai_generated = (bool) $this->templateIsAiGenerated;
        $template->holiday = $this->templateHoliday ?: null;

        // Bild-Verarbeitung
        if ($this->templateImage) {
            $realPath = $this->templateImage->getRealPath();
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = $realPath ? finfo_file($finfo, $realPath) : false;
            finfo_close($finfo);
            $ext = strtolower((string) $this->templateImage->getClientOriginalExtension());
            if (!in_array($detectedMime, ['image/jpeg', 'image/png', 'image/webp'], true) || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $this->addError('templateImage', 'Die Datei muss ein gültiges Bildformat sein.');
                return;
            }
            if ($template->exists && $template->preview_image && Str::startsWith($template->preview_image, 'produkte/product-templates/')) {
                Storage::disk('public')->delete($template->preview_image);
            }
            $template->preview_image = $this->templateImage->storeAs('produkte/product-templates', Str::uuid() . '.' . $ext, 'public');
        } elseif ($this->removeExistingImage) {
            if ($template->exists && $template->preview_image && Str::startsWith($template->preview_image, 'produkte/product-templates/')) {
                Storage::disk('public')->delete($template->preview_image);
            }
            $template->preview_image = null;
        } elseif (is_string($previewImagePath) && $previewImagePath !== '' && !$template->exists) {
            $cleanPreview = ltrim(str_replace('\\', '/', trim($previewImagePath)), '/');
            $previewExt = strtolower(pathinfo($cleanPreview, PATHINFO_EXTENSION));
            if (
                !str_contains($cleanPreview, '..')
                && !str_contains($cleanPreview, "\0")
                && !str_contains($cleanPreview, ':')
                && (Str::startsWith($cleanPreview, 'produkte/') || Str::startsWith($cleanPreview, 'shop/'))
                && in_array($previewExt, ['jpg', 'jpeg', 'png', 'webp'], true)
            ) {
                $template->preview_image = $cleanPreview;
            }
        }

        $template->save();

        $this->cancel();
    }

    private function resetForm()
    {
        $this->selectedProductId = null;
        $this->templateName = '';
        $this->templateIsActive = true;
        $this->templateIsAiGenerated = true;
        $this->templateHoliday = '';
        $this->templateConfig = [];
        $this->editingTemplateId = null;
        $this->templateImage = null;
        $this->existingTemplateImagePath = null;
        $this->removeExistingImage = false;
        $this->resetErrorBag();
    }

    public function render()
    {
        $query = ProductTemplate::with('product');

        if ($this->showArchived) {
            $query->onlyTrashed();
        }

        if ($this->search !== '') {
            $escapedSearch = addcslashes($this->search, '%_\\');
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('name', 'like', '%' . $escapedSearch . '%')
                  ->orWhere('holiday', 'like', '%' . $escapedSearch . '%')
                  ->orWhereHas('product', function ($pq) use ($escapedSearch) {
                      $pq->where('name', 'like', '%' . $escapedSearch . '%');
                  });
            });
        }

        if ($this->filterProductId !== '') {
            $query->where('product_id', $this->filterProductId);
        }

        if ($this->filterHoliday !== '') {
            $query->where(function ($q) {
                $q->where('holiday', $this->filterHoliday)
                  ->orWhere('holiday', strtolower($this->filterHoliday))
                  ->orWhere('holiday', ucfirst(strtolower($this->filterHoliday)));
            });
        }

        $templates = ($this->viewMode === 'list')
            ? $query->orderBy('created_at', 'desc')->get()
            : collect();

        $products = Product::where('status', 'active')
            ->where('type', 'physical')
            ->where('is_personalizable', true)
            ->orderBy('name')
            ->get();

        $archivedCount = ProductTemplate::onlyTrashed()->count();

        return view('livewire.shop.product.product-templates', [
            'templates' => $templates,
            'products' => $products,
            'archivedCount' => $archivedCount,
        ]);
    }
}
