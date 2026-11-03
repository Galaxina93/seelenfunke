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

    // 1b. Themen-Hintergrundvideos & 3D-Bühnenbilder (Zentrale Verwaltung)
    public array $themeVideoUploads = [];
    public array $themeImageUploads = [];

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
    #[\Livewire\Attributes\Locked]
    public ?string $editingSymbolId = null;
    public string $editSymbolName = '';
    public string $editSymbolCategory = '';

    // 5. Kategorien-Verwaltung
    public bool $showCategoryManager = false;
    public string $newCategoryName = '';
    #[\Livewire\Attributes\Locked]
    public ?string $renamingCategory = null;
    public string $renameCategoryValue = '';

    // 6. Spruch- & Widmungs-Bibliothek („Kreativ-Starthilfe“) + KI-Generator
    public string $selectedQuoteCategory = 'all';
    public string $quoteStatusFilter = 'all'; // 'all', 'active', 'archived'
    public string $quoteSearch = '';

    public string $newQuoteText = '';
    public string $newQuoteTitle = '';
    public string $newQuoteCategory = 'Liebe & Partnerschaft';

    public string $aiQuoteCategory = 'Liebe & Partnerschaft';
    public string $aiQuoteStyleHint = '';
    public int $aiQuoteMaxLength = 60;
    public array $aiGeneratedQuotes = [];

    #[\Livewire\Attributes\Locked]
    public ?string $editingQuoteId = null;
    public string $editQuoteText = '';
    public string $editQuoteTitle = '';
    public string $editQuoteCategory = '';

    public bool $showQuoteCategoryManager = false;
    public string $newQuoteCategoryName = '';
    #[\Livewire\Attributes\Locked]
    public ?string $renamingQuoteCategory = null;
    public string $renameQuoteCategoryValue = '';

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }

        $savedLayout = shop_setting('admin_configurator_symbols_layout_mode', 'medium');
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
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');
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
    // KONFIGURATOR-BÜHNENHINTERGRÜNDE & THEMEN-VIDEOS (7 ATMOSPHÄREN)
    // =========================================================================

    public function pinThemeAnimation(string $themeKey): void
    {
        $cfgService = app(\App\Services\ConfiguratorService::class);
        $themes = $cfgService->getBackgroundThemes();
        if (!isset($themes[$themeKey])) {
            return;
        }

        $current = (string) (shop_setting('manual_active_animation', '') ?? '');
        $nextVal = ($current === $themeKey) ? '' : $themeKey;

        \App\Models\System\SystemSetting::updateOrCreate(
            ['key' => 'manual_active_animation'],
            ['value' => $nextVal]
        );
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

        session()->flash(
            'themes_success',
            $nextVal !== ''
                ? "Themen-Welt „{$themes[$themeKey]['label']}“ wurde als aktive Live-Bühne im Konfigurator fixiert."
                : 'Manuelle Fixierung aufgehoben – Kalender-Automatik (Jahreszeiten & Feiertage) ist wieder aktiv.'
        );
    }

    public function updatedThemeVideoUploads($file, $themeKey): void
    {
        $cfgService = app(\App\Services\ConfiguratorService::class);
        $themes = $cfgService->getBackgroundThemes();
        if (!isset($themes[$themeKey]) || !$file) {
            return;
        }

        $this->validate([
            "themeVideoUploads.{$themeKey}" => 'required|file|mimes:mp4,webm|max:20480',
        ]);

        $relPath = $themes[$themeKey]['video'];
        $dir = dirname($relPath);
        $filename = basename($relPath);
        $file->storeAs($dir, $filename, 'public');
        unset($this->themeVideoUploads[$themeKey]);

        session()->flash('themes_success', "Hintergrund-Video für „{$themes[$themeKey]['label']}“ erfolgreich aktualisiert!");
    }

    public function updatedThemeImageUploads($file, $themeKey): void
    {
        $cfgService = app(\App\Services\ConfiguratorService::class);
        $themes = $cfgService->getBackgroundThemes();
        if (!isset($themes[$themeKey]) || !$file) {
            return;
        }

        $this->validate([
            "themeImageUploads.{$themeKey}" => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $relPath = $themes[$themeKey]['image'];
        $dir = dirname($relPath);
        $filename = basename($relPath);
        $file->storeAs($dir, $filename, 'public');
        unset($this->themeImageUploads[$themeKey]);

        session()->flash('themes_success', "3D-Bühnenbild für „{$themes[$themeKey]['label']}“ erfolgreich aktualisiert!");
    }

    // =========================================================================
    // SYMBOL-BIBLIOTHEK AKTIONEN
    // =========================================================================

    public function saveUploadedSymbols()
    {
        $this->validate([
            'newSymbolFiles'   => 'required|array|min:1|max:50',
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

    // =========================================================================
    // SPRUCH- & WIDMUNGS-BIBLIOTHEK („KREATIV-STARTHILFE“) + KI-GENERATOR
    // =========================================================================

    public function saveNewQuote(): void
    {
        $this->validate([
            'newQuoteText'     => 'required|string|min:2|max:240',
            'newQuoteTitle'    => 'nullable|string|max:60',
            'newQuoteCategory' => 'required|string|max:80',
        ], [
            'newQuoteText.required' => 'Bitte gib einen Spruchtext ein.',
            'newQuoteText.max'      => 'Der Spruch sollte maximal 240 Zeichen lang sein.',
        ]);

        app(ProductConfiguratorService::class)->addQuote(
            $this->newQuoteText,
            $this->newQuoteCategory,
            $this->newQuoteTitle,
            'manual'
        );

        $this->newQuoteText = '';
        $this->newQuoteTitle = '';
        session()->flash('quotes_success', 'Neuer Spruch wurde zur Kreativ-Starthilfe hinzugefügt.');
    }

    public function generateQuotesWithAi(): void
    {
        $this->validate([
            'aiQuoteCategory'  => 'required|string|max:80',
            'aiQuoteStyleHint' => 'nullable|string|max:120',
            'aiQuoteMaxLength' => 'required|integer|min:20|max:140',
        ]);

        $this->aiGeneratedQuotes = app(ProductConfiguratorService::class)->generateAiQuotes(
            $this->aiQuoteCategory,
            $this->aiQuoteStyleHint,
            $this->aiQuoteMaxLength,
            5
        );

        session()->flash(
            'quotes_success',
            count($this->aiGeneratedQuotes) . ' neue Spruch-Vorschläge generiert. Klicke auf „+ Übernehmen“, um deine Favoriten fest zu speichern.'
        );
    }

    public function acceptAiQuote(int $index): void
    {
        if (!isset($this->aiGeneratedQuotes[$index])) {
            return;
        }

        $q = $this->aiGeneratedQuotes[$index];
        app(ProductConfiguratorService::class)->addQuote(
            (string) ($q['text'] ?? ''),
            (string) ($q['category'] ?? $this->aiQuoteCategory),
            (string) ($q['title'] ?? ''),
            'ai'
        );

        unset($this->aiGeneratedQuotes[$index]);
        $this->aiGeneratedQuotes = array_values($this->aiGeneratedQuotes);

        session()->flash('quotes_success', 'KI-Spruch wurde fest in die Spruch-Bibliothek übernommen.');
    }

    public function acceptAllAiQuotes(): void
    {
        if (empty($this->aiGeneratedQuotes)) {
            return;
        }

        $service = app(ProductConfiguratorService::class);
        $count = 0;
        foreach ($this->aiGeneratedQuotes as $q) {
            $service->addQuote(
                (string) ($q['text'] ?? ''),
                (string) ($q['category'] ?? $this->aiQuoteCategory),
                (string) ($q['title'] ?? ''),
                'ai'
            );
            $count++;
        }

        $this->aiGeneratedQuotes = [];
        session()->flash('quotes_success', "Alle {$count} KI-Sprüche wurden fest in die Spruch-Bibliothek übernommen.");
    }

    public function discardAiQuote(int $index): void
    {
        if (isset($this->aiGeneratedQuotes[$index])) {
            unset($this->aiGeneratedQuotes[$index]);
            $this->aiGeneratedQuotes = array_values($this->aiGeneratedQuotes);
        }
    }

    public function startEditQuote(string $id, string $text, string $title, string $category): void
    {
        $this->editingQuoteId = $id;
        $this->editQuoteText = $text;
        $this->editQuoteTitle = $title;
        $this->editQuoteCategory = $category;
    }

    public function cancelEditQuote(): void
    {
        $this->editingQuoteId = null;
        $this->editQuoteText = '';
        $this->editQuoteTitle = '';
        $this->editQuoteCategory = '';
    }

    public function saveEditQuote(): void
    {
        if (!$this->editingQuoteId) {
            return;
        }

        $this->validate([
            'editQuoteText'     => 'required|string|min:2|max:240',
            'editQuoteTitle'    => 'nullable|string|max:60',
            'editQuoteCategory' => 'required|string|max:80',
        ]);

        app(ProductConfiguratorService::class)->updateQuote(
            $this->editingQuoteId,
            $this->editQuoteText,
            $this->editQuoteCategory,
            $this->editQuoteTitle
        );

        $this->cancelEditQuote();
        session()->flash('quotes_success', 'Spruch erfolgreich aktualisiert.');
    }

    public function quickChangeQuoteCategory(string $id, string $newCategory): void
    {
        $data = app(ProductConfiguratorService::class)->getAdminQuotesData();
        foreach ($data['quotes'] as $q) {
            if ($q['id'] === $id) {
                app(ProductConfiguratorService::class)->updateQuote($id, $q['text'], $newCategory, $q['title']);
                break;
            }
        }
    }

    public function toggleArchiveQuote(string $id): void
    {
        $isArchived = app(ProductConfiguratorService::class)->toggleArchiveQuote($id);
        if ($isArchived !== null) {
            session()->flash(
                'quotes_success',
                $isArchived
                    ? 'Spruch archiviert (im Kunden-Konfigurator ausgeblendet).'
                    : 'Spruch wieder aktiviert und im Kunden-Konfigurator sichtbar.'
            );
        }
    }

    public function deleteQuote(string $id): void
    {
        if (app(ProductConfiguratorService::class)->deleteQuote($id)) {
            if ($this->editingQuoteId === $id) {
                $this->cancelEditQuote();
            }
            session()->flash('quotes_success', 'Spruch endgültig gelöscht.');
        }
    }

    public function createQuoteCategory(): void
    {
        $name = trim($this->newQuoteCategoryName);
        if ($name === '') {
            return;
        }

        if (app(ProductConfiguratorService::class)->addQuoteCategory($name)) {
            $this->newQuoteCategory = $name;
            $this->aiQuoteCategory = $name;
            $this->newQuoteCategoryName = '';
            session()->flash('quotes_success', "Spruch-Kategorie „{$name}“ wurde angelegt.");
        }
    }

    public function startRenameQuoteCategory(string $cat): void
    {
        $this->renamingQuoteCategory = $cat;
        $this->renameQuoteCategoryValue = $cat;
    }

    public function cancelRenameQuoteCategory(): void
    {
        $this->renamingQuoteCategory = null;
        $this->renameQuoteCategoryValue = '';
    }

    public function saveRenameQuoteCategory(): void
    {
        if (!$this->renamingQuoteCategory) {
            return;
        }

        $old = $this->renamingQuoteCategory;
        $new = trim($this->renameQuoteCategoryValue);

        if ($new !== '' && app(ProductConfiguratorService::class)->renameQuoteCategory($old, $new)) {
            if ($this->selectedQuoteCategory === $old) {
                $this->selectedQuoteCategory = $new;
            }
            if ($this->newQuoteCategory === $old) {
                $this->newQuoteCategory = $new;
            }
            if ($this->aiQuoteCategory === $old) {
                $this->aiQuoteCategory = $new;
            }
            session()->flash('quotes_success', "Spruch-Kategorie „{$old}“ in „{$new}“ umbenannt.");
        }

        $this->cancelRenameQuoteCategory();
    }

    public function removeQuoteCategory(string $cat): void
    {
        if (app(ProductConfiguratorService::class)->deleteQuoteCategory($cat)) {
            if ($this->selectedQuoteCategory === $cat) {
                $this->selectedQuoteCategory = 'all';
            }
            session()->flash('quotes_success', "Spruch-Kategorie „{$cat}“ entfernt (Sprüche wurden in die erste Kategorie verschoben).");
        }
    }

    public function moveQuoteCategory(string $cat, string $direction): void
    {
        app(ProductConfiguratorService::class)->moveQuoteCategoryOrder($cat, $direction);
    }

    public function render(ProductConfiguratorService $service)
    {
        if (!in_array($this->statusFilter, ['all', 'active', 'archived'], true)) {
            $this->statusFilter = 'all';
        }
        if (!in_array($this->previewSurface, ['light', 'dark'], true)) {
            $this->previewSurface = 'light';
        }
        if (!in_array($this->quoteStatusFilter, ['all', 'active', 'archived'], true)) {
            $this->quoteStatusFilter = 'all';
        }

        $tutorialConfig = $service->getTutorialConfig();
        $vectorsData = $service->getAdminVectorsData(
            $this->selectedCategory,
            $this->search,
            $this->statusFilter
        );

        $quotesData = $service->getAdminQuotesData();
        $filteredQuotes = array_values(array_filter($quotesData['quotes'], function (array $q) {
            if ($this->selectedQuoteCategory !== 'all' && $q['category'] !== $this->selectedQuoteCategory) {
                return false;
            }
            if ($this->quoteStatusFilter === 'active' && $q['archived']) {
                return false;
            }
            if ($this->quoteStatusFilter === 'archived' && !$q['archived']) {
                return false;
            }
            if (trim($this->quoteSearch) !== '') {
                $needle = mb_strtolower(trim($this->quoteSearch));
                $haystack = mb_strtolower($q['text'] . ' ' . $q['title'] . ' ' . $q['category']);
                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }
            return true;
        }));

        $cfgService = app(\App\Services\ConfiguratorService::class);
        $backgroundThemes = $cfgService->getBackgroundThemes();
        $currentActiveTheme = $cfgService->getCurrentActiveTheme();
        $manualAnimKey = (string) (shop_setting('manual_active_animation', '') ?? '');

        return view('livewire.shop.product.product-configurator-settings', [
            'tutorialConfig'      => $tutorialConfig,
            'backgroundThemes'    => $backgroundThemes,
            'currentActiveTheme'  => $currentActiveTheme,
            'manualAnimKey'       => $manualAnimKey,
            'categories'          => $vectorsData['categories'],
            'categoryCounts'      => $vectorsData['category_counts'],
            'symbols'             => $vectorsData['items'],
            'symbolStats'         => $vectorsData['stats'],
            'quoteCategories'     => $quotesData['categories'],
            'quoteCategoryCounts' => $quotesData['category_counts'],
            'quotes'              => $filteredQuotes,
            'quoteStats'          => $quotesData['stats'],
        ]);
    }
}
