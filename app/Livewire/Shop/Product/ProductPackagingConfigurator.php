<?php

namespace App\Livewire\Shop\Product;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Product\Product;
use App\Models\Product\ProductPackaging;
use App\Models\Product\ProductPackagingItem;
use App\Models\Product\ProductSupplier;
use Database\Seeders\ProductPackagingSeeder;
use Database\Seeders\ProductSupplierSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class ProductPackagingConfigurator extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Produkte';

    #[Url(as: 'tab')]
    public string $activeTab = 'products'; // 'products' = Tab 1 (Produkterfassung), 'catalog' = Tab 2 (Verpackungsmaterial)

    #[Url(as: 'product')]
    public $selectedProductId = '';

    // Tab 1: Katalog-Verpackung einem Produkt zuweisen
    public $selectedCatalogItemId = '';
    public string $assignMode = 'add'; // 'add' (zusätzlich z.B. Schmuckbox + Versandtasche) oder 'replace' (ersetzen)
    public bool $syncProductPackagingCost = true;

    // Tab 1: Manuelle LUCID-Erfassung
    public $newType = '';
    public $newWeightGrams = null;
    public $newLabel = '';

    #[Locked]
    public $editId = null;
    public $editWeightGrams = null;

    // Tab 2: Filter & KI-Link-Importer
    public string $catalogCategoryFilter = 'all';
    public string $catalogSearch = '';
    public string $aiLinkUrl = '';
    public string $aiStatusMessage = '';
    public string $aiStatusType = 'info';

    // Tab 2: Modal für Manuelle Anlage / Bearbeitung eines Verpackungsmaterials
    public bool $showItemModal = false;
    #[\Livewire\Attributes\Locked]
    public ?int $editingItemId = null;

    public string $itemName = '';
    public string $itemSku = '';
    public string $itemCategory = 'gift_box';
    public string $itemDescription = '';
    public string $itemSupplierName = '';
    public string $itemSupplierId = '';
    public string $itemReorderUrl = '';

    public $itemLengthMm = 86;
    public $itemWidthMm = 86;
    public $itemHeightMm = 17;

    public $itemWeightGrams = 20;
    public string $itemPrimaryMaterial = 'paper';
    public $itemPrimaryWeight = 18;
    public string $itemSecondaryMaterial = 'plastic';
    public $itemSecondaryWeight = 2;

    public $itemPackQuantity = 48;
    public string $itemPackPriceEur = '26,40';
    public string $itemUnitCostEur = '0,55';

    public $itemStockQuantity = 48;
    public $itemMinStockThreshold = 15;
    public bool $itemIsUpsellOption = true;
    public string $itemUpsellPriceEur = '2,90';

    public function boot(): void
    {
        $this->authorizeAccess();
    }

    public function mount(): void
    {
        $this->authorizeAccess();

        if (!in_array($this->activeTab, ['products', 'catalog'], true)) {
            $this->activeTab = 'products';
        }

        // Auto-Seed falls Katalog-Tabelle noch leer ist
        if (Schema::hasTable('product_packaging_items') && ProductPackagingItem::count() === 0) {
            try {
                (new ProductSupplierSeeder())->run();
                (new ProductPackagingSeeder())->run();
            } catch (\Throwable $e) {
                // Silent fallback
            }
        }

        // Standardmäßig das erste physische Produkt vorwählen, damit Tab 1 sofort einsatzbereit ist
        if (empty($this->selectedProductId)) {
            $firstProduct = Product::where('status', '!=', 'archived')
                ->where('type', 'physical')
                ->orderBy('name')
                ->first();

            if ($firstProduct) {
                $this->selectedProductId = (string) $firstProduct->id;
            }
        }
    }

    protected function authorizeAccess(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['products', 'catalog'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function selectProduct(string $productId): void
    {
        $this->selectedProductId = $productId;
        $this->reset(['newType', 'newWeightGrams', 'newLabel', 'editId', 'editWeightGrams']);
    }

    protected function rules(): array
    {
        return [
            'newType' => ['required', 'string', Rule::in(array_keys(ProductPackaging::getMaterialTypes()))],
            'newWeightGrams' => 'required|numeric|min:1|max:100000',
        ];
    }

    public function getProductProperty(): ?Product
    {
        return $this->selectedProductId ? Product::find($this->selectedProductId) : null;
    }

    public function updatedSelectedProductId(): void
    {
        $this->reset(['newType', 'newWeightGrams', 'newLabel', 'editId', 'editWeightGrams']);
    }

    /**
     * Tab 1: Weist einem Produkt mit 1 Klick eine gespeicherte Verpackung aus Tab 2 zu.
     */
    public function assignCatalogItemToProduct(?int $catalogItemId = null): void
    {
        $this->authorizeAccess();

        $product = $this->product;
        if (!$product) {
            $this->dispatch('toast', message: 'Bitte zuerst ein Produkt auswählen.', type: 'error');
            return;
        }

        $targetId = $catalogItemId ?: (int) $this->selectedCatalogItemId;
        $item = ProductPackagingItem::find($targetId);

        if (!$item) {
            $this->dispatch('toast', message: 'Bitte eine Verpackung aus dem Katalog wählen.', type: 'error');
            return;
        }

        if ($this->assignMode === 'replace') {
            $product->packagings()->delete();
        }

        // Primäres Material eintragen
        if ($item->primary_material_type && $item->primary_weight_grams > 0) {
            $existingPrimary = $product->packagings()
                ->where('material_type', $item->primary_material_type)
                ->where('packaging_item_id', $item->id)
                ->first();

            if ($existingPrimary) {
                $existingPrimary->update([
                    'weight_grams' => min(100000, $existingPrimary->weight_grams + $item->primary_weight_grams),
                    'label' => $item->name,
                ]);
            } else {
                $product->packagings()->create([
                    'packaging_item_id' => $item->id,
                    'material_type' => $item->primary_material_type,
                    'label' => $item->name,
                    'weight_grams' => $item->primary_weight_grams,
                ]);
            }
        }

        // Sekundäres Material eintragen (z.B. Schaumstoffeinlage / Luftpolsterfolie)
        if ($item->secondary_material_type && $item->secondary_weight_grams > 0) {
            $existingSecondary = $product->packagings()
                ->where('material_type', $item->secondary_material_type)
                ->where('packaging_item_id', $item->id)
                ->first();

            if ($existingSecondary) {
                $existingSecondary->update([
                    'weight_grams' => min(100000, $existingSecondary->weight_grams + $item->secondary_weight_grams),
                    'label' => $item->name . ' (Einlage/Polster)',
                ]);
            } else {
                $product->packagings()->create([
                    'packaging_item_id' => $item->id,
                    'material_type' => $item->secondary_material_type,
                    'label' => $item->name . ' (Einlage/Polster)',
                    'weight_grams' => $item->secondary_weight_grams,
                ]);
            }
        }

        // Optional: Verpackungskosten & Verpackungsgewicht direkt am Produkt-Stammsatz aktualisieren
        if ($this->syncProductPackagingCost) {
            $this->syncProductPackagingTotals($product);
        }

        $this->selectedCatalogItemId = '';
        $this->dispatch('toast', message: "„{$item->name}“ wurde dem Produkt zugewiesen.", type: 'success');
    }

    public function applyPackagingPreset(?int $catalogItemId = null): void
    {
        $this->assignCatalogItemToProduct($catalogItemId);
    }

    protected function syncProductPackagingTotals(Product $product): void
    {
        $totalWeightGrams = (int) round((float) $product->packagings()->sum('weight_grams'));

        $linkedItemIds = $product->packagings()
            ->whereNotNull('packaging_item_id')
            ->pluck('packaging_item_id')
            ->unique()
            ->all();

        $totalPackagingCostCents = !empty($linkedItemIds)
            ? (int) ProductPackagingItem::whereIn('id', $linkedItemIds)->sum('unit_cost_cents')
            : 0;

        $product->update([
            'packaging_weight' => $totalWeightGrams,
            'packaging_cost' => $totalPackagingCostCents > 0 ? $totalPackagingCostCents : $product->packaging_cost,
        ]);
    }

    public function addMaterial(): void
    {
        $this->authorizeAccess();
        $this->validate();

        $product = $this->product;
        if (!$product) {
            return;
        }

        $existing = $product->packagings()
            ->where('material_type', $this->newType)
            ->whereNull('packaging_item_id')
            ->first();

        if ($existing) {
            $newTotal = min(100000, (int) $existing->weight_grams + (int) $this->newWeightGrams);
            $existing->update([
                'weight_grams' => $newTotal,
                'label' => $this->newLabel ?: $existing->label,
            ]);
            $this->dispatch('toast', message: 'Gewicht zum bestehenden Material addiert.', type: 'info');
        } else {
            $product->packagings()->create([
                'material_type' => $this->newType,
                'label' => $this->newLabel ?: null,
                'weight_grams' => (int) $this->newWeightGrams,
            ]);
            $this->dispatch('toast', message: 'Verpackungsmaterial erfolgreich hinzugefügt.', type: 'success');
        }

        $this->syncProductPackagingTotals($product);
        $this->reset(['newType', 'newWeightGrams', 'newLabel']);
    }

    public function startEdit($id, $currentWeight): void
    {
        $this->editId = $id;
        $this->editWeightGrams = $currentWeight;
    }

    public function saveEdit(): void
    {
        $this->authorizeAccess();

        $this->validate([
            'editWeightGrams' => 'required|numeric|min:1|max:100000',
        ]);

        $product = $this->product;
        if (!$product) {
            return;
        }

        $packaging = $product->packagings()->findOrFail($this->editId);
        $packaging->update(['weight_grams' => (int) $this->editWeightGrams]);

        $this->syncProductPackagingTotals($product);

        $this->editId = null;
        $this->editWeightGrams = null;

        $this->dispatch('toast', message: 'Gewicht aktualisiert.', type: 'success');
    }

    public function cancelEdit(): void
    {
        $this->editId = null;
        $this->editWeightGrams = null;
    }

    public function deleteMaterial($id): void
    {
        $this->authorizeAccess();

        $product = $this->product;
        if (!$product) {
            return;
        }

        $product->packagings()->where('id', $id)->delete();
        $this->syncProductPackagingTotals($product);
        $this->dispatch('toast', message: 'Material-Eintrag entfernt.', type: 'info');
    }

    // =========================================================================
    // TAB 2: VERPACKUNGSMATERIAL-KATALOG & KI-LINK-IMPORTER
    // =========================================================================

    public function updatedItemPackQuantity(): void
    {
        $this->recalculateUnitCost();
    }

    public function updatedItemPackPriceEur(): void
    {
        $this->recalculateUnitCost();
    }

    public function updatedItemPrimaryWeight(): void
    {
        $this->itemWeightGrams = max(1, (int) $this->itemPrimaryWeight + (int) $this->itemSecondaryWeight);
    }

    public function updatedItemSecondaryWeight(): void
    {
        $this->itemWeightGrams = max(1, (int) $this->itemPrimaryWeight + (int) $this->itemSecondaryWeight);
    }

    protected function recalculateUnitCost(): void
    {
        $qty = max(1, (int) $this->itemPackQuantity);
        $packCents = $this->parseEurToCents($this->itemPackPriceEur);
        if ($packCents > 0) {
            $unitCents = (int) round($packCents / $qty);
            $this->itemUnitCostEur = number_format($unitCents / 100, 2, ',', '.');
        }
    }

    protected function parseEurToCents($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $normalized = str_replace(['€', ' '], '', (string) $value);
        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }
        return max(0, (int) round(((float) $normalized) * 100));
    }

    /**
     * Öffnet das Modal zur manuellen Neuanlage einer Verpackung.
     */
    public function openCreateModal(): void
    {
        $this->authorizeAccess();
        $this->editingItemId = null;
        $this->itemName = '';
        $this->itemSku = 'PKG-' . strtoupper(Str::random(5));
        $this->itemCategory = 'gift_box';
        $this->itemDescription = '';
        $this->itemSupplierName = '';
        $this->itemSupplierId = '';
        $this->itemReorderUrl = $this->aiLinkUrl ?: '';
        $this->itemLengthMm = 86;
        $this->itemWidthMm = 86;
        $this->itemHeightMm = 17;
        $this->itemWeightGrams = 20;
        $this->itemPrimaryMaterial = 'paper';
        $this->itemPrimaryWeight = 18;
        $this->itemSecondaryMaterial = 'plastic';
        $this->itemSecondaryWeight = 2;
        $this->itemPackQuantity = 48;
        $this->itemPackPriceEur = '26,40';
        $this->itemUnitCostEur = '0,55';
        $this->itemStockQuantity = 0;
        $this->itemMinStockThreshold = 10;
        $this->itemIsUpsellOption = false;
        $this->itemUpsellPriceEur = '2,90';

        $this->showItemModal = true;
    }

    /**
     * Öffnet das Modal zum Bearbeiten einer bestehenden Verpackung.
     */
    public function editCatalogItem(int $id): void
    {
        $this->authorizeAccess();
        $item = ProductPackagingItem::findOrFail($id);

        $this->editingItemId = $item->id;
        $this->itemName = (string) $item->name;
        $this->itemSku = (string) ($item->sku ?? '');
        $this->itemCategory = (string) ($item->category ?: 'gift_box');
        $this->itemDescription = (string) ($item->description ?? '');
        $this->itemSupplierName = (string) ($item->supplier_name ?? '');
        $this->itemSupplierId = (string) ($item->product_supplier_id ?? '');
        $this->itemReorderUrl = (string) ($item->reorder_url ?? '');
        $this->itemLengthMm = $item->length_mm;
        $this->itemWidthMm = $item->width_mm;
        $this->itemHeightMm = $item->height_mm;
        $this->itemWeightGrams = (int) $item->weight_grams;
        $this->itemPrimaryMaterial = (string) ($item->primary_material_type ?: 'paper');
        $this->itemPrimaryWeight = (int) $item->primary_weight_grams;
        $this->itemSecondaryMaterial = (string) ($item->secondary_material_type ?? '');
        $this->itemSecondaryWeight = (int) $item->secondary_weight_grams;
        $this->itemPackQuantity = max(1, (int) $item->pack_quantity);
        $this->itemPackPriceEur = number_format($item->pack_price_cents / 100, 2, ',', '.');
        $this->itemUnitCostEur = number_format($item->unit_cost_cents / 100, 2, ',', '.');
        $this->itemStockQuantity = (int) $item->stock_quantity;
        $this->itemMinStockThreshold = (int) $item->min_stock_threshold;
        $this->itemIsUpsellOption = (bool) $item->is_upsell_option;
        $this->itemUpsellPriceEur = number_format($item->upsell_price_cents / 100, 2, ',', '.');

        $this->showItemModal = true;
    }

    public function closeItemModal(): void
    {
        $this->showItemModal = false;
    }

    public function saveCatalogItem(): void
    {
        $this->authorizeAccess();

        $this->validate([
            'itemName' => 'required|string|max:255',
            'itemCategory' => ['required', 'string', Rule::in(array_keys(ProductPackagingItem::getCategories()))],
            'itemPrimaryMaterial' => ['required', 'string', Rule::in(array_keys(ProductPackaging::getMaterialTypes()))],
            'itemPrimaryWeight' => 'required|numeric|min:1|max:50000',
        ]);

        $supplier = null;
        if (!empty($this->itemSupplierId)) {
            $supplier = ProductSupplier::find($this->itemSupplierId);
        }

        $primaryWeight = max(1, (int) $this->itemPrimaryWeight);
        $secondaryWeight = !empty($this->itemSecondaryMaterial) ? max(0, (int) $this->itemSecondaryWeight) : 0;
        $totalWeight = $primaryWeight + $secondaryWeight;

        $packQty = max(1, (int) $this->itemPackQuantity);
        $packPriceCents = $this->parseEurToCents($this->itemPackPriceEur);
        $unitCostCents = $this->parseEurToCents($this->itemUnitCostEur);
        if ($unitCostCents <= 0 && $packPriceCents > 0) {
            $unitCostCents = (int) round($packPriceCents / $packQty);
        }

        $data = [
            'name' => trim($this->itemName),
            'sku' => trim($this->itemSku) ?: ('PKG-' . strtoupper(Str::random(5))),
            'category' => $this->itemCategory,
            'description' => trim($this->itemDescription) ?: null,
            'supplier_name' => $supplier ? $supplier->name : (trim($this->itemSupplierName) ?: null),
            'product_supplier_id' => $supplier?->id,
            'reorder_url' => trim($this->itemReorderUrl) ?: null,
            'length_mm' => $this->itemLengthMm !== '' && $this->itemLengthMm !== null ? (float) $this->itemLengthMm : null,
            'width_mm' => $this->itemWidthMm !== '' && $this->itemWidthMm !== null ? (float) $this->itemWidthMm : null,
            'height_mm' => $this->itemHeightMm !== '' && $this->itemHeightMm !== null ? (float) $this->itemHeightMm : null,
            'weight_grams' => $totalWeight,
            'primary_material_type' => $this->itemPrimaryMaterial,
            'primary_weight_grams' => $primaryWeight,
            'secondary_material_type' => !empty($this->itemSecondaryMaterial) ? $this->itemSecondaryMaterial : null,
            'secondary_weight_grams' => $secondaryWeight,
            'pack_quantity' => $packQty,
            'pack_price_cents' => $packPriceCents,
            'unit_cost_cents' => $unitCostCents,
            'stock_quantity' => max(0, (int) $this->itemStockQuantity),
            'min_stock_threshold' => max(0, (int) $this->itemMinStockThreshold),
            'is_upsell_option' => $this->itemIsUpsellOption,
            'upsell_price_cents' => $this->itemIsUpsellOption ? $this->parseEurToCents($this->itemUpsellPriceEur) : 0,
            'is_active' => true,
        ];

        if ($this->editingItemId) {
            $item = ProductPackagingItem::findOrFail($this->editingItemId);
            $item->update($data);
            $this->dispatch('toast', message: "Verpackung „{$item->name}“ wurde aktualisiert.", type: 'success');
        } else {
            $item = ProductPackagingItem::create($data);
            $this->dispatch('toast', message: "Verpackung „{$item->name}“ wurde im Katalog angelegt.", type: 'success');
        }

        $this->showItemModal = false;
        $this->editingItemId = null;
    }

    public function adjustStock(int $id, int $delta): void
    {
        $this->authorizeAccess();
        $item = ProductPackagingItem::findOrFail($id);
        $newStock = max(0, (int) $item->stock_quantity + $delta);
        $item->update(['stock_quantity' => $newStock]);
        $this->dispatch('toast', message: "Lagerbestand auf {$newStock} Stk. angepasst.", type: 'info');
    }

    public function deleteCatalogItem(int $id): void
    {
        $this->authorizeAccess();
        $item = ProductPackagingItem::findOrFail($id);
        $name = $item->name;
        $item->delete();
        $this->dispatch('toast', message: "Verpackung „{$name}“ wurde aus dem Katalog entfernt.", type: 'info');
    }

    /**
     * Button 1: „KI eintragen lassen“ – Analysiert den Link und legt den Eintrag direkt im Katalog an!
     */
    public function createPackagingFromAiUrl(): void
    {
        $this->authorizeAccess();

        $url = trim($this->aiLinkUrl);
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->aiStatusType = 'error';
            $this->aiStatusMessage = 'Bitte gib einen gültigen Produkt-Link (https://...) zu einer Verpackung ein.';
            $this->dispatch('toast', message: 'Bitte gültige URL eingeben.', type: 'error');
            return;
        }

        $parsed = $this->inferPackagingFromUrl($url);

        $item = ProductPackagingItem::updateOrCreate(
            ['reorder_url' => $url],
            [
                'name' => $parsed['name'],
                'sku' => $parsed['sku'],
                'category' => $parsed['category'],
                'description' => $parsed['description'],
                'supplier_name' => $parsed['supplier_name'],
                'product_supplier_id' => $parsed['product_supplier_id'],
                'length_mm' => $parsed['length_mm'],
                'width_mm' => $parsed['width_mm'],
                'height_mm' => $parsed['height_mm'],
                'weight_grams' => $parsed['weight_grams'],
                'primary_material_type' => $parsed['primary_material_type'],
                'primary_weight_grams' => $parsed['primary_weight_grams'],
                'secondary_material_type' => $parsed['secondary_material_type'],
                'secondary_weight_grams' => $parsed['secondary_weight_grams'],
                'pack_quantity' => $parsed['pack_quantity'],
                'pack_price_cents' => $parsed['pack_price_cents'],
                'unit_cost_cents' => $parsed['unit_cost_cents'],
                'stock_quantity' => $parsed['stock_quantity'],
                'min_stock_threshold' => 15,
                'is_upsell_option' => $parsed['is_upsell_option'],
                'upsell_price_cents' => $parsed['upsell_price_cents'],
                'is_active' => true,
            ]
        );

        $tariffInfo = $item->postal_tariff_info;
        $unitEur = number_format($item->unit_cost_cents / 100, 2, ',', '.');

        $this->aiStatusType = 'success';
        $this->aiStatusMessage = "KI-Import erfolgreich: „{$item->name}“ ({$item->formatted_dimensions} · {$item->weight_grams} g · {$unitEur} €/Stk. · {$tariffInfo['badge']}) wurde direkt im Katalog gespeichert!";
        $this->aiLinkUrl = '';

        $this->dispatch('toast', message: "KI hat „{$item->name}“ analysiert & eingetragen!", type: 'success');
    }

    /**
     * Button 2: „Link analysieren & im Formular prüfen“ – Befüllt das Modal mit den KI-Daten.
     */
    public function analyzeAiUrlIntoModal(): void
    {
        $this->authorizeAccess();

        $url = trim($this->aiLinkUrl);
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->aiStatusType = 'error';
            $this->aiStatusMessage = 'Bitte gib einen gültigen Produkt-Link (https://...) ein.';
            $this->dispatch('toast', message: 'Bitte gültige URL eingeben.', type: 'error');
            return;
        }

        $parsed = $this->inferPackagingFromUrl($url);

        $this->editingItemId = null;
        $this->itemName = $parsed['name'];
        $this->itemSku = $parsed['sku'];
        $this->itemCategory = $parsed['category'];
        $this->itemDescription = $parsed['description'];
        $this->itemSupplierName = (string) ($parsed['supplier_name'] ?? '');
        $this->itemSupplierId = (string) ($parsed['product_supplier_id'] ?? '');
        $this->itemReorderUrl = $url;
        $this->itemLengthMm = $parsed['length_mm'];
        $this->itemWidthMm = $parsed['width_mm'];
        $this->itemHeightMm = $parsed['height_mm'];
        $this->itemWeightGrams = $parsed['weight_grams'];
        $this->itemPrimaryMaterial = $parsed['primary_material_type'];
        $this->itemPrimaryWeight = $parsed['primary_weight_grams'];
        $this->itemSecondaryMaterial = (string) ($parsed['secondary_material_type'] ?? '');
        $this->itemSecondaryWeight = (int) ($parsed['secondary_weight_grams'] ?? 0);
        $this->itemPackQuantity = $parsed['pack_quantity'];
        $this->itemPackPriceEur = number_format($parsed['pack_price_cents'] / 100, 2, ',', '.');
        $this->itemUnitCostEur = number_format($parsed['unit_cost_cents'] / 100, 2, ',', '.');
        $this->itemStockQuantity = $parsed['stock_quantity'];
        $this->itemMinStockThreshold = 15;
        $this->itemIsUpsellOption = $parsed['is_upsell_option'];
        $this->itemUpsellPriceEur = number_format($parsed['upsell_price_cents'] / 100, 2, ',', '.');

        $this->showItemModal = true;
        $this->dispatch('toast', message: 'KI-Analyse abgeschlossen – Daten im Formular vorbefüllt.', type: 'info');
    }

    protected function isSafeExternalUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return false;
        }

        foreach (['.local', '.internal', '.localhost', '.test', '.invalid'] as $blockedSuffix) {
            if (str_ends_with($host, $blockedSuffix)) {
                return false;
            }
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $resolved = @gethostbynamel($host);
            if (is_array($resolved)) {
                $ips = array_merge($ips, $resolved);
            }
            $dnsRecords = @dns_get_record($host, DNS_A + DNS_AAAA);
            if (is_array($dnsRecords)) {
                foreach ($dnsRecords as $rec) {
                    if (!empty($rec['ip'])) {
                        $ips[] = $rec['ip'];
                    }
                    if (!empty($rec['ipv6'])) {
                        $ips[] = $rec['ipv6'];
                    }
                }
            }
        }

        $ips = array_values(array_unique(array_filter($ips)));
        if (empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Kombinierte KI- & Domain-/HTML-Tiefenanalyse für Verpackungs-Links.
     */
    protected function inferPackagingFromUrl(string $url): array
    {
        $lowerUrl = mb_strtolower($url);
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        $path = urldecode(parse_url($url, PHP_URL_PATH) ?? '');

        // 1. Versuche Live-Metadaten (Title, OG-Description, Maße) von der Zielseite abzurufen (mit SSRF-Schutz)
        $scrapedTitle = '';
        $scrapedDescription = '';
        $scrapedTextSnippet = '';

        try {
            if ($this->isSafeExternalUrl($url)) {
                $response = Http::timeout(4)
                    ->withOptions(['allow_redirects' => false])
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                        'Accept-Language' => 'de-DE,de;q=0.9,en;q=0.8',
                    ])
                    ->get($url);

                if ($response->successful()) {
                    $html = (string) $response->body();
                    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
                        $scrapedTitle = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    }
                    if (preg_match('/<meta[^>]+(?:name|property)=["\'](?:description|og:description)["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
                        $scrapedDescription = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    }
                    $cleanBody = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', ' ', $html);
                    $scrapedTextSnippet = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) $cleanBody))), 0, 1800);
                }
            }
        } catch (\Throwable $e) {
            // Kein Abbruch bei Bot-Schutz (z.B. Cloudflare / Amazon) – unsere Domain-Heuristik + Gemini übernimmt
        }

        // 2. Bekannte B2B-Verpackungslieferanten verknüpfen
        $westpackSupplier = ProductSupplier::where('name', 'LIKE', '%Westpack%')->first();
        $kartonSupplier = ProductSupplier::where('name', 'LIKE', '%Karton.eu%')->first();

        // 3. Spezifische High-Precision-Profile für bekannte Verpackungen
        if (str_contains($lowerUrl, 'westpack.com') && (str_contains($lowerUrl, '30286060102') || str_contains($lowerUrl, 'frankfurt-eco'))) {
            return [
                'name' => 'Frankfurt ECO Schmuckschachtel Anhänger/Armreif (mit Logo)',
                'sku' => '30286060102',
                'category' => 'gift_box',
                'description' => 'Mattschwarze FSC-Schmuckschachtel inkl. Mein-Seelenfunke-Logodruck & schwarzer Schaumstoffeinlage (Order #29000069521). 17 mm flach für 1,80 € Großbrief.',
                'supplier_name' => 'Westpack A/S',
                'product_supplier_id' => $westpackSupplier?->id,
                'length_mm' => 86.0,
                'width_mm' => 86.0,
                'height_mm' => 17.0,
                'weight_grams' => 20,
                'primary_material_type' => 'paper',
                'primary_weight_grams' => 18,
                'secondary_material_type' => 'plastic',
                'secondary_weight_grams' => 2,
                'pack_quantity' => 96,
                'pack_price_cents' => 10176,
                'unit_cost_cents' => 106,
                'stock_quantity' => 96,
                'is_upsell_option' => true,
                'upsell_price_cents' => 490,
            ];
        }

        if (str_contains($lowerUrl, 'b0gknzyh5m') || (str_contains($lowerUrl, 'amazon.') && str_contains($lowerUrl, 'yaunming'))) {
            return [
                'name' => 'Yaunming Schmuckkästchen Geschenkbox (Schwarz, 90 × 70 × 30 mm)',
                'sku' => 'B0GKNZYH5M',
                'category' => 'gift_box',
                'description' => 'Schwarze Karton-Geschenkbox mit Schaumstoffeinlage (30 mm Höhe -> Maxibrief).',
                'supplier_name' => 'Amazon DE',
                'product_supplier_id' => null,
                'length_mm' => 90.0,
                'width_mm' => 70.0,
                'height_mm' => 30.0,
                'weight_grams' => 26,
                'primary_material_type' => 'paper',
                'primary_weight_grams' => 23,
                'secondary_material_type' => 'plastic',
                'secondary_weight_grams' => 3,
                'pack_quantity' => 12,
                'pack_price_cents' => 1399,
                'unit_cost_cents' => 117,
                'stock_quantity' => 12,
                'is_upsell_option' => true,
                'upsell_price_cents' => 390,
            ];
        }

        // 4. Falls Gemini API Key hinterlegt ist, nutze Gemini 2.5 Flash zur intelligenten Extraktion
        $apiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
        if (!empty($apiKey)) {
            try {
                $prompt = "Du bist ein E-Commerce-Verpackungs- und LUCID-Experte für 'Mein Seelenfunke' (Lasergravur-Manufaktur). "
                    . "Analysiere diesen Verpackungs-Link und die Seiten-Metadaten und gib ein valides JSON-Objekt zurück.\n"
                    . "URL: {$url}\n"
                    . "Seitentitel: {$scrapedTitle}\n"
                    . "Beschreibung: {$scrapedDescription}\n"
                    . "Textauszug: {$scrapedTextSnippet}\n\n"
                    . "Gib exakt folgende JSON-Felder zurück:\n"
                    . "{\n"
                    . "  \"name\": \"Präziser deutscher Produktname inkl. Farbe & Maße\",\n"
                    . "  \"sku\": \"Kurze Artikelnummer z.B. PKG-...\",\n"
                    . "  \"category\": \"gift_box | shipping_envelope | shipping_box | filler | accessory\",\n"
                    . "  \"description\": \"Kurze deutsche Beschreibung inkl. Eignung und Hinweis zur Post-Versandklasse\",\n"
                    . "  \"supplier_name\": \"Name des Händlers/Herstellers\",\n"
                    . "  \"length_mm\": 86,\n"
                    . "  \"width_mm\": 86,\n"
                    . "  \"height_mm\": 17,\n"
                    . "  \"primary_material_type\": \"paper | plastic | composite | glass | wood | other\",\n"
                    . "  \"primary_weight_grams\": 18,\n"
                    . "  \"secondary_material_type\": \"plastic | paper | null\",\n"
                    . "  \"secondary_weight_grams\": 2,\n"
                    . "  \"pack_quantity\": 48,\n"
                    . "  \"pack_price_cents\": 2640,\n"
                    . "  \"unit_cost_cents\": 55,\n"
                    . "  \"is_upsell_option\": true,\n"
                    . "  \"upsell_price_cents\": 290\n"
                    . "}";

                $aiResp = Http::timeout(8)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}",
                    [
                        'contents' => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => [
                            'temperature' => 0.1,
                            'responseMimeType' => 'application/json',
                        ],
                    ]
                );

                if ($aiResp->successful()) {
                    $rawJson = $aiResp->json('candidates.0.content.parts.0.text');
                    $decoded = is_string($rawJson) ? json_decode($rawJson, true) : null;

                    if (is_array($decoded) && !empty($decoded['name'])) {
                        $pWeight = max(1, (int) ($decoded['primary_weight_grams'] ?? 18));
                        $sWeight = max(0, (int) ($decoded['secondary_weight_grams'] ?? 0));
                        $packQty = max(1, (int) ($decoded['pack_quantity'] ?? 1));
                        $packCents = max(0, (int) ($decoded['pack_price_cents'] ?? 0));
                        $unitCents = max(0, (int) ($decoded['unit_cost_cents'] ?? ($packCents > 0 ? round($packCents / $packQty) : 50)));

                        return [
                            'name' => (string) $decoded['name'],
                            'sku' => (string) ($decoded['sku'] ?? ('PKG-' . strtoupper(Str::random(5)))),
                            'category' => in_array($decoded['category'] ?? '', array_keys(ProductPackagingItem::getCategories()), true)
                                ? $decoded['category']
                                : 'gift_box',
                            'description' => (string) ($decoded['description'] ?? 'Automatisch per KI aus Produktlink analysiert.'),
                            'supplier_name' => (string) ($decoded['supplier_name'] ?? $host),
                            'product_supplier_id' => str_contains($lowerUrl, 'westpack') ? $westpackSupplier?->id : (str_contains($lowerUrl, 'karton.eu') ? $kartonSupplier?->id : null),
                            'length_mm' => isset($decoded['length_mm']) ? (float) $decoded['length_mm'] : 86.0,
                            'width_mm' => isset($decoded['width_mm']) ? (float) $decoded['width_mm'] : 86.0,
                            'height_mm' => isset($decoded['height_mm']) ? (float) $decoded['height_mm'] : 17.0,
                            'weight_grams' => $pWeight + $sWeight,
                            'primary_material_type' => in_array($decoded['primary_material_type'] ?? '', array_keys(ProductPackaging::getMaterialTypes()), true)
                                ? $decoded['primary_material_type']
                                : 'paper',
                            'primary_weight_grams' => $pWeight,
                            'secondary_material_type' => in_array($decoded['secondary_material_type'] ?? '', array_keys(ProductPackaging::getMaterialTypes()), true)
                                ? $decoded['secondary_material_type']
                                : null,
                            'secondary_weight_grams' => $sWeight,
                            'pack_quantity' => $packQty,
                            'pack_price_cents' => $packCents,
                            'unit_cost_cents' => $unitCents,
                            'stock_quantity' => $packQty,
                            'is_upsell_option' => (bool) ($decoded['is_upsell_option'] ?? false),
                            'upsell_price_cents' => (int) ($decoded['upsell_price_cents'] ?? 290),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Fallback auf deterministischen Parser unten
            }
        }

        // 5. Deterministischer Fallback-Parser (extrahiert Maße, Lieferant & Kategorie aus URL & HTML)
        $combinedSource = $scrapedTitle . ' ' . $scrapedDescription . ' ' . $path . ' ' . $scrapedTextSnippet;
        $lengthMm = 90.0;
        $widthMm = 90.0;
        $heightMm = 17.0;

        if (preg_match('/(\d{1,3}(?:[.,]\d)?)\s*[x×*]\s*(\d{1,3}(?:[.,]\d)?)\s*[x×*]\s*(\d{1,3}(?:[.,]\d)?)\s*(mm|cm)/iu', $combinedSource, $dimMatch)) {
            $factor = mb_strtolower($dimMatch[4]) === 'cm' ? 10.0 : 1.0;
            $lengthMm = (float) str_replace(',', '.', $dimMatch[1]) * $factor;
            $widthMm = (float) str_replace(',', '.', $dimMatch[2]) * $factor;
            $heightMm = (float) str_replace(',', '.', $dimMatch[3]) * $factor;
        }

        $isEnvelope = str_contains($lowerUrl, 'tasche') || str_contains($lowerUrl, 'polster') || str_contains($lowerUrl, 'envelope') || str_contains($lowerUrl, 'brief');
        $isGiftBox = str_contains($lowerUrl, 'westpack') || str_contains($lowerUrl, 'leser') || str_contains($lowerUrl, 'schmuck') || str_contains($lowerUrl, 'jewellery') || str_contains($lowerUrl, 'etui') || str_contains($lowerUrl, 'geschenk');

        $category = $isGiftBox ? 'gift_box' : ($isEnvelope ? 'shipping_envelope' : 'shipping_box');

        $slugName = trim(preg_replace('/[-_]+/', ' ', pathinfo($path, PATHINFO_FILENAME)));
        $slugName = preg_replace('/\b\d{6,}\b/', '', (string) $slugName);
        $readableName = $scrapedTitle ?: (ucwords(trim((string) $slugName)) ?: 'Verpackungsmaterial aus Link');

        $supplierName = match (true) {
            str_contains($host, 'westpack') => 'Westpack A/S',
            str_contains($host, 'leser') => 'LESER GmbH Verpackungen',
            str_contains($host, 'karton.eu') => 'Karton.eu (Lausitzer Kartonagen)',
            str_contains($host, 'jakodan') => 'Jakodan A/S',
            str_contains($host, 'amazon') => 'Amazon Business DE',
            default => ucfirst(preg_replace('/^www\./', '', $host)),
        };

        return [
            'name' => Str::limit($readableName, 110, ''),
            'sku' => 'PKG-' . strtoupper(Str::random(6)),
            'category' => $category,
            'description' => $scrapedDescription ?: "Automatisch analysierte Verpackung von {$supplierName} ({$lengthMm} × {$widthMm} × {$heightMm} mm).",
            'supplier_name' => $supplierName,
            'product_supplier_id' => str_contains($host, 'westpack') ? $westpackSupplier?->id : (str_contains($host, 'karton.eu') ? $kartonSupplier?->id : null),
            'length_mm' => $lengthMm,
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
            'weight_grams' => $isGiftBox ? 20 : ($isEnvelope ? 15 : 120),
            'primary_material_type' => $isEnvelope ? 'composite' : 'paper',
            'primary_weight_grams' => $isGiftBox ? 18 : ($isEnvelope ? 12 : 105),
            'secondary_material_type' => $isEnvelope ? 'paper' : 'plastic',
            'secondary_weight_grams' => $isGiftBox ? 2 : ($isEnvelope ? 3 : 15),
            'pack_quantity' => $isGiftBox ? 48 : 100,
            'pack_price_cents' => $isGiftBox ? 2640 : 2900,
            'unit_cost_cents' => $isGiftBox ? 55 : 29,
            'stock_quantity' => $isGiftBox ? 48 : 100,
            'is_upsell_option' => $isGiftBox,
            'upsell_price_cents' => $isGiftBox ? 290 : 0,
        ];
    }

    public function render()
    {
        $products = Product::with('packagings')
            ->where('status', '!=', 'archived')
            ->where('type', 'physical')
            ->orderBy('name')
            ->get();

        $catalogQuery = ProductPackagingItem::with(['supplier', 'productPackagings'])
            ->orderByDesc('is_upsell_option')
            ->orderBy('category')
            ->orderBy('name');

        if ($this->catalogCategoryFilter !== 'all') {
            $catalogQuery->where('category', $this->catalogCategoryFilter);
        }

        if (!empty(trim($this->catalogSearch))) {
            $term = '%' . trim($this->catalogSearch) . '%';
            $catalogQuery->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', $term)
                    ->orWhere('sku', 'LIKE', $term)
                    ->orWhere('supplier_name', 'LIKE', $term)
                    ->orWhere('description', 'LIKE', $term);
            });
        }

        $catalogItems = $catalogQuery->get();
        $allCatalogItems = ProductPackagingItem::where('is_active', true)->orderBy('name')->get();

        // KPI-Statistiken für den Header
        $configuredProductsCount = $products->filter(fn ($p) => $p->packagings->sum('weight_grams') > 0)->count();
        $grossbriefCompatibleCount = $allCatalogItems->filter(fn ($i) => $i->height_mm > 0 && $i->height_mm <= 20)->count();
        $upsellBoxesCount = $allCatalogItems->where('is_upsell_option', true)->count();

        return view('livewire.shop.product.product-packaging-configurator', [
            'products' => $products,
            'packagings' => $this->product ? $this->product->packagings()->with('packagingItem')->orderBy('material_type')->get() : collect(),
            'availableTypes' => ProductPackaging::getMaterialTypes(),
            'catalogItems' => $catalogItems,
            'allCatalogItems' => $allCatalogItems,
            'categories' => ProductPackagingItem::getCategories(),
            'suppliers' => ProductSupplier::orderBy('name')->get(),
            'configuredProductsCount' => $configuredProductsCount,
            'grossbriefCompatibleCount' => $grossbriefCompatibleCount,
            'upsellBoxesCount' => $upsellBoxesCount,
        ]);
    }
}
