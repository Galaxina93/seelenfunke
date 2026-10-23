<?php

namespace App\Livewire\Shop\Product;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Services\ProductConfiguratorService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.backend_layout')]
class ProductConfiguratorSettings extends Component
{
    use WithFileUploads, WithDepartmentTheming;

    public string $themingDepartment = 'Produkte';

    // 1. Tutorial-Video Upload
    public $tutorialVideoUpload = null;

    // 2. Symbol-Bibliothek Filter & Ansicht
    public string $selectedCategory = 'all';
    public string $statusFilter = 'all'; // 'all', 'active', 'archived'
    public string $search = '';
    public string $previewSurface = 'light'; // 'light' | 'dark'
    public string $layoutMode = 'medium'; // 'small' (Klein), 'medium' (Mittel), 'large' (Groß), 'table' (Tabelle)

    // 3. Neue Symbole hochladen (Drag & Drop / Mehrfach-Upload)
    public $newSymbolFiles = [];
    public string $uploadCategory = 'Liebe';
    public string $uploadCustomName = '';

    // 4. Symbol Inline-Bearbeitung
    public ?string $editingSymbolId = null;
    public string $editSymbolName = '';
    public string $editSymbolCategory = '';

    // 5. Kategorien-Verwaltung
    public bool $showCategoryManager = false;
    public string $newCategoryName = '';
    public ?string $renamingCategory = null;
    public string $renameCategoryValue = '';

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }

        $savedLayout = \App\Models\System\SystemSetting::where('key', 'admin_configurator_symbols_layout_mode')->value('value');
        if (in_array($savedLayout, ['small', 'medium', 'large', 'table'], true)) {
            $this->layoutMode = $savedLayout;
        }
    }

    public function setLayoutMode(string $mode): void
    {
        if (!in_array($mode, ['small', 'medium', 'large', 'table'], true)) {
            return;
        }

        $this->layoutMode = $mode;

        \App\Models\System\SystemSetting::updateOrCreate(
            ['key' => 'admin_configurator_symbols_layout_mode'],
            ['value' => $mode]
        );
    }

    // =========================================================================
    // TUTORIAL-VIDEO AKTIONEN
    // =========================================================================

    public function updatedTutorialVideoUpload()
    {
        $this->saveTutorialVideo();
    }

    public function saveTutorialVideo()
    {
        if (!$this->tutorialVideoUpload) {
            return;
        }

        $this->validate([
            'tutorialVideoUpload' => 'required|file|mimes:mp4,webm,mov|max:102400', // max 100 MB
        ], [
            'tutorialVideoUpload.required' => 'Bitte wähle eine Videodatei aus.',
            'tutorialVideoUpload.mimes'    => 'Das Video muss im Format MP4, WebM oder MOV vorliegen.',
            'tutorialVideoUpload.max'      => 'Das Video darf maximal 100 MB groß sein.',
        ]);

        app(ProductConfiguratorService::class)->storeTutorialVideo($this->tutorialVideoUpload);
        $this->tutorialVideoUpload = null;

        session()->flash('tutorial_success', 'Neues Tutorial-Video wurde erfolgreich gespeichert und aktiviert.');
    }

    public function resetTutorialToDefault()
    {
        app(ProductConfiguratorService::class)->resetToDefaultTutorialVideo();
        session()->flash('tutorial_success', 'Auf das Standard-Tutorial-Video zurückgesetzt.');
    }

    public function toggleTutorialEnabled()
    {
        $service = app(ProductConfiguratorService::class);
        $current = $service->getTutorialConfig()['enabled'] ?? true;
        $newState = $service->setTutorialEnabled(!$current);

        session()->flash(
            'tutorial_success',
            $newState ? 'Tutorial-Video ist im Produktkonfigurator wieder sichtbar.' : 'Tutorial-Video wurde im Produktkonfigurator ausgeblendet.'
        );
    }

    // =========================================================================
    // SYMBOL-BIBLIOTHEK AKTIONEN
    // =========================================================================

    public function saveUploadedSymbols()
    {
        $this->validate([
            'newSymbolFiles'   => 'required|array|min:1',
            'newSymbolFiles.*' => 'required|file|mimes:svg,xml|max:2048',
            'uploadCategory'   => 'required|string|max:60',
        ], [
            'newSymbolFiles.required' => 'Bitte wähle mindestens eine SVG-Datei aus.',
            'newSymbolFiles.*.mimes'  => 'Es sind nur SVG-Vektordateien (.svg) erlaubt.',
            'newSymbolFiles.*.max'    => 'Jede SVG-Datei darf maximal 2 MB groß sein.',
        ]);

        $service = app(ProductConfiguratorService::class);
        $count = count($this->newSymbolFiles);

        foreach ($this->newSymbolFiles as $idx => $file) {
            $customTitle = ($count === 1 && trim($this->uploadCustomName) !== '')
                ? trim($this->uploadCustomName)
                : null;
            $service->uploadSymbol($file, $this->uploadCategory, $customTitle);
        }

        $this->newSymbolFiles = [];
        $this->uploadCustomName = '';

        session()->flash('symbols_success', $count === 1 ? '1 neues Symbol hinzugefügt.' : "{$count} neue Symbole erfolgreich hinzugefügt.");
    }

    public function removeStagedFile(int $index)
    {
        if (isset($this->newSymbolFiles[$index])) {
            unset($this->newSymbolFiles[$index]);
            $this->newSymbolFiles = array_values($this->newSymbolFiles);
        }
    }

    public function startEditSymbol(string $id, string $name, string $category)
    {
        $this->editingSymbolId = $id;
        $this->editSymbolName = $name;
        $this->editSymbolCategory = $category;
    }

    public function cancelEditSymbol()
    {
        $this->editingSymbolId = null;
        $this->editSymbolName = '';
        $this->editSymbolCategory = '';
    }

    public function saveEditSymbol()
    {
        if (!$this->editingSymbolId) {
            return;
        }

        $this->validate([
            'editSymbolName'     => 'required|string|max:80',
            'editSymbolCategory' => 'required|string|max:60',
        ]);

        app(ProductConfiguratorService::class)->updateSymbol(
            $this->editingSymbolId,
            $this->editSymbolName,
            $this->editSymbolCategory
        );

        $this->cancelEditSymbol();
        session()->flash('symbols_success', 'Symbol erfolgreich aktualisiert.');
    }

    public function quickChangeCategory(string $id, string $currentName, string $newCategory)
    {
        app(ProductConfiguratorService::class)->updateSymbol($id, $currentName, $newCategory);
    }

    public function toggleArchiveSymbol(string $id)
    {
        $isArchived = app(ProductConfiguratorService::class)->toggleArchiveSymbol($id);
        if ($isArchived !== null) {
            session()->flash(
                'symbols_success',
                $isArchived
                    ? 'Symbol archiviert (im Kunden-Konfigurator ausgeblendet, für bestehende Bestellungen weiterhin sicher).'
                    : 'Symbol wieder aktiviert und im Kunden-Konfigurator sichtbar.'
            );
        }
    }

    public function deleteSymbol(string $id)
    {
        if (app(ProductConfiguratorService::class)->deleteSymbol($id)) {
            if ($this->editingSymbolId === $id) {
                $this->cancelEditSymbol();
            }
            session()->flash('symbols_success', 'Symbol endgültig gelöscht.');
        }
    }

    // =========================================================================
    // KATEGORIEN-VERWALTUNG
    // =========================================================================

    public function createCategory()
    {
        $name = trim($this->newCategoryName);
        if ($name === '') {
            return;
        }

        if (app(ProductConfiguratorService::class)->addCategory($name)) {
            $this->uploadCategory = $name;
            $this->newCategoryName = '';
            session()->flash('symbols_success', "Kategorie „{$name}“ wurde angelegt.");
        }
    }

    public function startRenameCategory(string $cat)
    {
        $this->renamingCategory = $cat;
        $this->renameCategoryValue = $cat;
    }

    public function cancelRenameCategory()
    {
        $this->renamingCategory = null;
        $this->renameCategoryValue = '';
    }

    public function saveRenameCategory()
    {
        if (!$this->renamingCategory) {
            return;
        }

        $old = $this->renamingCategory;
        $new = trim($this->renameCategoryValue);

        if ($new !== '' && app(ProductConfiguratorService::class)->renameCategory($old, $new)) {
            if ($this->selectedCategory === $old) {
                $this->selectedCategory = $new;
            }
            if ($this->uploadCategory === $old) {
                $this->uploadCategory = $new;
            }
            session()->flash('symbols_success', "Kategorie „{$old}“ in „{$new}“ umbenannt.");
        }

        $this->cancelRenameCategory();
    }

    public function removeCategory(string $cat)
    {
        if (app(ProductConfiguratorService::class)->deleteCategory($cat)) {
            if ($this->selectedCategory === $cat) {
                $this->selectedCategory = 'all';
            }
            session()->flash('symbols_success', "Kategorie „{$cat}“ entfernt (enthaltene Symbole wurden nach „Allgemein“ verschoben).");
        }
    }

    public function moveCategory(string $cat, string $direction)
    {
        app(ProductConfiguratorService::class)->moveCategoryOrder($cat, $direction);
    }

    public function render(ProductConfiguratorService $service)
    {
        $tutorialConfig = $service->getTutorialConfig();
        $vectorsData = $service->getAdminVectorsData(
            $this->selectedCategory,
            $this->search,
            $this->statusFilter
        );

        return view('livewire.shop.product.product-configurator-settings', [
            'tutorialConfig' => $tutorialConfig,
            'categories'     => $vectorsData['categories'],
            'categoryCounts' => $vectorsData['category_counts'],
            'symbols'        => $vectorsData['items'],
            'symbolStats'    => $vectorsData['stats'],
        ]);
    }
}
