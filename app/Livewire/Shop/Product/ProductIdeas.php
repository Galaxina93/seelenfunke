<?php

namespace App\Livewire\Shop\Product;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Product\ProductIdea;
use App\Models\Product\ProductSupplier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class ProductIdeas extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Produkte';

    public $ideas;
    public string $search = '';
    public string $statusFilter = 'all';

    // Form fields
    public int $rank = 10;
    public bool $is_top_priority = false;
    public string $title = '';
    public string $category = 'echtschmuck';
    public string $status = 'top_idea';
    public string $material_summary = '';
    public string $supplier_name = '';
    public ?string $product_supplier_id = null;

    // Euro string inputs for easy editing
    public string $purchase_price_gross_eur = '0,00';
    public string $purchase_price_net_eur = '0,00';
    public string $packaging_cost_eur = '1,06';
    public string $shipping_cost_eur = '4,50';
    public string $target_price_gross_eur = '0,00';

    public array $components_links = [];
    public string $notes = '';

    // Modal state
    public bool $isEditing = false;
    #[Locked]
    public ?string $editingId = null;
    public bool $showModal = false;

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount(): void
    {
        $this->ensureTableAndDefaultSeed();
        $this->loadIdeas();
    }

    protected function ensureTableAndDefaultSeed(): void
    {
        if (!Schema::hasTable('product_ideas')) {
            Schema::create('product_ideas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->integer('rank')->default(10)->index();
                $table->boolean('is_top_priority')->default(false)->index();
                $table->string('title');
                $table->string('category')->default('echtschmuck')->index();
                $table->string('status')->default('top_idea')->index();
                $table->string('material_summary')->nullable();
                $table->string('supplier_name')->nullable();
                $table->uuid('product_supplier_id')->nullable()->index();
                $table->foreign('product_supplier_id')->references('id')->on('product_suppliers')->nullOnDelete();
                $table->integer('purchase_price_net_cents')->default(0);
                $table->integer('purchase_price_gross_cents')->default(0);
                $table->integer('packaging_cost_cents')->default(106);
                $table->integer('shipping_cost_cents')->default(450);
                $table->integer('target_price_gross_cents')->default(0);
                $table->integer('expected_profit_cents')->default(0);
                $table->json('components_links')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (ProductIdea::count() === 0) {
            app(\Database\Seeders\ProductIdeaSeeder::class)->run();
        }
    }

    public function updatedSearch(): void
    {
        $this->loadIdeas();
    }

    public function setStatusFilter(string $filter): void
    {
        $allowed = ['all', 'top_idea', 'sample_needed', 'planned', 'ready', 'transferred'];
        $this->statusFilter = in_array($filter, $allowed, true) ? $filter : 'all';
        $this->loadIdeas();
    }

    public function loadIdeas(): void
    {
        $query = ProductIdea::with('supplier')
            ->orderByDesc('is_top_priority')
            ->orderBy('rank')
            ->orderBy('created_at');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('material_summary', 'like', $like)
                    ->orWhere('supplier_name', 'like', $like)
                    ->orWhere('notes', 'like', $like);
            });
        }

        $this->ideas = $query->get();
    }

    public function toggleTopPriority(string $id): void
    {
        $idea = ProductIdea::findOrFail($id);
        $idea->is_top_priority = !$idea->is_top_priority;
        if ($idea->is_top_priority && $idea->rank > 1) {
            $idea->rank = 1;
        }
        $idea->save();

        $this->dispatch('toast', message: $idea->is_top_priority ? 'Als Top-Favorit markiert.' : 'Top-Markierung entfernt.', type: 'success');
        $this->loadIdeas();
    }

    public function updateStatusQuick(string $id, string $newStatus): void
    {
        $allowed = ['top_idea', 'sample_needed', 'planned', 'ready', 'transferred', 'discarded'];
        if (!in_array($newStatus, $allowed, true)) {
            return;
        }

        $idea = ProductIdea::findOrFail($id);
        $idea->status = $newStatus;
        $idea->save();

        $this->dispatch('toast', message: 'Status aktualisiert.', type: 'success');
        $this->loadIdeas();
    }

    public function updatedPurchasePriceGrossEur($val): void
    {
        $grossCents = $this->parseEurToCents((string) $val);
        $netCents = (int) round($grossCents / 1.19);
        $this->purchase_price_net_eur = number_format($netCents / 100, 2, ',', '');
    }

    public function updatedPurchasePriceNetEur($val): void
    {
        $netCents = $this->parseEurToCents((string) $val);
        $grossCents = (int) round($netCents * 1.19);
        $this->purchase_price_gross_eur = number_format($grossCents / 100, 2, ',', '');
    }

    public function sumComponentPrices(): void
    {
        $totalGrossCents = 0;
        $totalNetCents = 0;

        foreach ($this->components_links as $idx => $comp) {
            $gCents = $this->parseEurToCents((string) ($comp['price_gross_eur'] ?? '0'));
            $nCents = $gCents > 0 ? (int) round($gCents / 1.19) : 0;
            $this->components_links[$idx]['price_gross_cents'] = $gCents;
            $this->components_links[$idx]['price_net_cents'] = $nCents;
            $totalGrossCents += $gCents;
            $totalNetCents += $nCents;
        }

        if ($totalGrossCents > 0) {
            $this->purchase_price_gross_eur = number_format($totalGrossCents / 100, 2, ',', '');
            $this->purchase_price_net_eur = number_format($totalNetCents / 100, 2, ',', '');
        }
    }

    public function addComponentRow(): void
    {
        if (count($this->components_links) >= 20) {
            return;
        }

        $this->components_links[] = [
            'title' => '',
            'sku' => '',
            'spec' => '',
            'price_gross_eur' => '0,00',
            'url' => '',
        ];
    }

    public function removeComponentRow(int $index): void
    {
        unset($this->components_links[$index]);
        $this->components_links = array_values($this->components_links);
        $this->sumComponentPrices();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->rank = ProductIdea::count() + 1;
        $this->addComponentRow();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function edit(string $id): void
    {
        $idea = ProductIdea::findOrFail($id);

        $this->editingId = $idea->id;
        $this->rank = (int) $idea->rank;
        $this->is_top_priority = (bool) $idea->is_top_priority;
        $this->title = (string) $idea->title;
        $this->category = (string) ($idea->category ?: 'echtschmuck');
        $this->status = (string) ($idea->status ?: 'top_idea');
        $this->material_summary = (string) ($idea->material_summary ?? '');
        $this->supplier_name = (string) ($idea->supplier_name ?? '');
        $this->product_supplier_id = $idea->product_supplier_id;

        $this->purchase_price_net_eur = number_format(((int) $idea->purchase_price_net_cents) / 100, 2, ',', '');
        $this->purchase_price_gross_eur = number_format(((int) $idea->purchase_price_gross_cents) / 100, 2, ',', '');
        $this->packaging_cost_eur = number_format(((int) $idea->packaging_cost_cents) / 100, 2, ',', '');
        $this->shipping_cost_eur = number_format(((int) $idea->shipping_cost_cents) / 100, 2, ',', '');
        $this->target_price_gross_eur = number_format(((int) $idea->target_price_gross_cents) / 100, 2, ',', '');

        $rawLinks = is_array($idea->components_links) ? $idea->components_links : [];
        $this->components_links = array_map(function ($row) {
            $gCents = (int) ($row['price_gross_cents'] ?? 0);
            return [
                'title' => (string) ($row['title'] ?? ''),
                'sku' => (string) ($row['sku'] ?? ''),
                'spec' => (string) ($row['spec'] ?? ''),
                'price_gross_eur' => number_format($gCents / 100, 2, ',', ''),
                'url' => (string) ($row['url'] ?? ''),
            ];
        }, $rawLinks);

        $this->notes = (string) ($idea->notes ?? '');

        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'rank' => 'required|integer|min:1|max:999',
            'category' => 'required|string|max:64',
            'status' => 'required|string|in:top_idea,sample_needed,planned,ready,transferred,discarded',
            'material_summary' => 'nullable|string|max:500',
            'supplier_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:15000',
            'components_links' => 'nullable|array|max:20',
            'components_links.*.title' => 'required|string|max:255',
            'components_links.*.url' => 'required|url|max:1000',
        ]);

        $normalizedComponents = [];
        foreach ($this->components_links as $comp) {
            if (trim((string) ($comp['title'] ?? '')) === '' && trim((string) ($comp['url'] ?? '')) === '') {
                continue;
            }
            $gCents = $this->parseEurToCents((string) ($comp['price_gross_eur'] ?? '0'));
            $nCents = (int) round($gCents / 1.19);
            $normalizedComponents[] = [
                'title' => trim((string) ($comp['title'] ?? '')),
                'sku' => trim((string) ($comp['sku'] ?? '')),
                'spec' => trim((string) ($comp['spec'] ?? '')),
                'price_gross_cents' => $gCents,
                'price_net_cents' => $nCents,
                'url' => trim((string) ($comp['url'] ?? '')),
            ];
        }

        $purchaseNetCents = $this->parseEurToCents($this->purchase_price_net_eur);
        $purchaseGrossCents = $this->parseEurToCents($this->purchase_price_gross_eur);
        $packagingNetCents = $this->parseEurToCents($this->packaging_cost_eur);
        $shippingNetCents = $this->parseEurToCents($this->shipping_cost_eur);
        $targetGrossCents = $this->parseEurToCents($this->target_price_gross_eur);

        $expectedProfitCents = ProductIdea::computeExpectedProfitCents(
            $targetGrossCents,
            $purchaseNetCents,
            $packagingNetCents,
            $shippingNetCents
        );

        $data = [
            'rank' => (int) $this->rank,
            'is_top_priority' => (bool) $this->is_top_priority,
            'title' => trim($this->title),
            'category' => $this->category,
            'status' => $this->status,
            'material_summary' => trim($this->material_summary) ?: null,
            'supplier_name' => trim($this->supplier_name) ?: null,
            'product_supplier_id' => $this->product_supplier_id ?: null,
            'purchase_price_net_cents' => $purchaseNetCents,
            'purchase_price_gross_cents' => $purchaseGrossCents,
            'packaging_cost_cents' => $packagingNetCents,
            'shipping_cost_cents' => $shippingNetCents,
            'target_price_gross_cents' => $targetGrossCents,
            'expected_profit_cents' => $expectedProfitCents,
            'components_links' => $normalizedComponents,
            'notes' => trim($this->notes) ?: null,
        ];

        if ($this->isEditing && $this->editingId) {
            ProductIdea::findOrFail($this->editingId)->update($data);
            $this->dispatch('toast', message: 'Produkt-Idee aktualisiert.', type: 'success');
        } else {
            ProductIdea::create($data);
            $this->dispatch('toast', message: 'Neue Produkt-Idee gespeichert.', type: 'success');
        }

        $this->showModal = false;
        $this->loadIdeas();
    }

    public function delete(string $id): void
    {
        ProductIdea::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Produkt-Idee entfernt.', type: 'info');
        $this->loadIdeas();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->rank = 10;
        $this->is_top_priority = false;
        $this->title = '';
        $this->category = 'echtschmuck';
        $this->status = 'top_idea';
        $this->material_summary = '';
        $this->supplier_name = '';
        $this->product_supplier_id = null;
        $this->purchase_price_gross_eur = '0,00';
        $this->purchase_price_net_eur = '0,00';
        $this->packaging_cost_eur = '1,06';
        $this->shipping_cost_eur = '4,50';
        $this->target_price_gross_eur = '0,00';
        $this->components_links = [];
        $this->notes = '';
        $this->showModal = false;
        $this->isEditing = false;
    }

    public function getLiveCalculatedProfitCentsProperty(): int
    {
        return ProductIdea::computeExpectedProfitCents(
            $this->parseEurToCents($this->target_price_gross_eur),
            $this->parseEurToCents($this->purchase_price_net_eur),
            $this->parseEurToCents($this->packaging_cost_eur),
            $this->parseEurToCents($this->shipping_cost_eur)
        );
    }

    protected function parseEurToCents(string $val): int
    {
        $clean = str_replace(['€', ' ', "\u{00A0}"], '', trim($val));
        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
        }
        $clean = str_replace(',', '.', $clean);
        return (int) round(((float) $clean) * 100);
    }

    public function render()
    {
        return view('livewire.shop.product.product-ideas', [
            'allSuppliers' => ProductSupplier::orderBy('name')->get(),
        ]);
    }
}
