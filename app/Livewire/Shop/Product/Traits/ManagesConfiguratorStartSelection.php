<?php

namespace App\Livewire\Shop\Product\Traits;

use App\Models\Product\ProductTemplate;
use App\Services\ConfiguratorService;
use App\Services\ProductTrackingService;
use Livewire\Attributes\Locked;

trait ManagesConfiguratorStartSelection
{
    public $showTemplateSelection = false;
    public $showTemplatesList = false;

    #[Locked]
    public $productTemplates = [];

    public bool $showAllSeasonalTemplates = false;

    #[Locked]
    public int $hiddenSeasonalTemplatesCount = 0;

    public $currentConfig = [];

    abstract protected function resolveStartSelectionProductId(): ?string;

    protected function canProceedWithStartSelectionAction(): bool
    {
        return !filter_var(shop_setting('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN);
    }

    protected function afterStartModeTransition(string $mode, ?ProductTemplate $selectedTemplate = null): void
    {
        // Optionaler Hook für Host-Komponenten (z. B. Varianten-Bildwechsel, Step-Wechsel oder Scroll-Event)
    }

    protected function initStartSelectionForStudioProduct(?string $requestedTemplateId = null): void
    {
        $productId = $this->resolveStartSelectionProductId();
        if (!$productId) {
            return;
        }

        $this->currentConfig = [];
        $this->showAllSeasonalTemplates = false;
        $this->hiddenSeasonalTemplatesCount = 0;
        $this->showTemplatesList = false;

        $templates = ProductTemplate::where('product_id', $productId)
            ->where('is_active', true)
            ->get();

        if ($templates->isNotEmpty()) {
            $this->refreshStorefrontTemplates($templates);

            if ($requestedTemplateId) {
                $matchingTemplate = $templates->firstWhere('id', $requestedTemplateId);
                if ($matchingTemplate) {
                    $this->selectTemplate($matchingTemplate->id);
                    return;
                }
            }
        } else {
            $this->productTemplates = [];
        }

        $this->showTemplateSelection = true;
    }

    protected function refreshStorefrontTemplates(?iterable $allActiveTemplates = null): void
    {
        $productId = $this->resolveStartSelectionProductId();
        if (!$productId) {
            $this->productTemplates = [];
            $this->hiddenSeasonalTemplatesCount = 0;
            $this->showAllSeasonalTemplates = false;
            return;
        }

        $templates = $allActiveTemplates !== null
            ? collect($allActiveTemplates)
            : ProductTemplate::where('product_id', $productId)->where('is_active', true)->get();

        if ($templates->isEmpty()) {
            $this->productTemplates = [];
            $this->hiddenSeasonalTemplatesCount = 0;
            $this->showAllSeasonalTemplates = false;
            return;
        }

        $allAsArrays = $templates
            ->map(fn ($t) => $t instanceof ProductTemplate ? $t->toArray() : (array) $t)
            ->values()
            ->all();
        $totalActive = count($allAsArrays);
        $curated = ProductTemplate::filterAndSortForStorefront($allAsArrays, false);

        if (empty($curated)) {
            $this->productTemplates = ProductTemplate::filterAndSortForStorefront($allAsArrays, true);
            $this->hiddenSeasonalTemplatesCount = 0;
            $this->showAllSeasonalTemplates = false;
            return;
        }

        if (count($curated) === $totalActive) {
            $this->productTemplates = $curated;
            $this->hiddenSeasonalTemplatesCount = 0;
            $this->showAllSeasonalTemplates = false;
            return;
        }

        if ($this->showAllSeasonalTemplates) {
            $this->productTemplates = ProductTemplate::filterAndSortForStorefront($allAsArrays, true);
            $this->hiddenSeasonalTemplatesCount = 0;
            return;
        }

        $this->productTemplates = $curated;
        $this->hiddenSeasonalTemplatesCount = max(0, $totalActive - count($curated));
    }

    public function toggleShowAllTemplates(): void
    {
        if (!$this->canProceedWithStartSelectionAction()) {
            return;
        }

        if (!$this->resolveStartSelectionProductId()) {
            return;
        }

        $this->showAllSeasonalTemplates = !$this->showAllSeasonalTemplates;
        $this->refreshStorefrontTemplates();
    }

    public function backToStartSelection(): void
    {
        if (!$this->canProceedWithStartSelectionAction()) {
            return;
        }

        $this->showTemplatesList = false;
        $this->showTemplateSelection = true;
        $this->afterStartModeTransition('back_to_selection');
    }

    public function startBeginnerConfig(): void
    {
        if (!$this->canProceedWithStartSelectionAction()) {
            return;
        }

        $productId = $this->resolveStartSelectionProductId();
        if (!$productId) {
            return;
        }

        ProductTrackingService::trackModeChosen($productId, 'beginner');
        $this->showTemplateSelection = false;
        $this->showTemplatesList = false;
        $this->currentConfig = [
            'personalization_mode' => 'custom_fields',
        ];

        $this->afterStartModeTransition('beginner');
    }

    public function openTemplatesList(): void
    {
        if (!$this->canProceedWithStartSelectionAction()) {
            return;
        }

        $productId = $this->resolveStartSelectionProductId();
        if (!$productId) {
            return;
        }

        ProductTrackingService::trackModeChosen($productId, 'template');
        $this->showTemplateSelection = false;
        $this->showTemplatesList = true;

        $this->afterStartModeTransition('templates_list');
    }

    public function startCustomConfig(): void
    {
        if (!$this->canProceedWithStartSelectionAction()) {
            return;
        }

        $productId = $this->resolveStartSelectionProductId();
        if (!$productId) {
            return;
        }

        ProductTrackingService::trackModeChosen($productId, 'custom');
        $this->showTemplateSelection = false;
        $this->showTemplatesList = false;
        $this->currentConfig = [
            'personalization_mode' => 'studio',
        ];

        $this->afterStartModeTransition('custom');
    }

    public function selectTemplate($templateId): void
    {
        if (!$this->canProceedWithStartSelectionAction()) {
            return;
        }

        $productId = $this->resolveStartSelectionProductId();
        if (!$productId) {
            return;
        }

        $template = ProductTemplate::where('product_id', $productId)
            ->where('is_active', true)
            ->find($templateId);

        if ($template) {
            $config = $template->configuration ?? [];
            if (empty($config['background_theme'])) {
                $config['background_theme'] = app(ConfiguratorService::class)
                    ->resolveThemeKey($template->holiday ?? $template->name);
            }
            $config['holiday'] = $template->holiday;
            $config['template_name'] = $template->name;
            $this->currentConfig = $config;

            ProductTrackingService::trackTemplateSelected(
                $productId,
                $templateId,
                $template->name ?? $template->title ?? ('Vorlage #' . $templateId)
            );
        } else {
            $this->currentConfig = [];
        }

        $this->showTemplateSelection = false;
        $this->showTemplatesList = false;

        $this->afterStartModeTransition('template', $template);
    }
}
