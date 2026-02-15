<?php

namespace App\Livewire\Global\Widgets;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Financial\FinanceCostItem;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\Product\Product;

class HealthCheck extends Component
{
    use WithFileUploads;

    public $uploadFile;
    public array $stockUpdate = []; // Produkt-ID => Neuer Wert
    public ?string $expandedHealthKey = null;

    public function mount(): void
    {
        // Initialer Check passiert bei Bedarf über Computed Property
    }

    #[Computed]
    public function performAllChecks(): array
    {
        if (!auth()->guard('admin')->check()) return [];

        return array_filter([
            'inventory' => $this->checkInventory(),
            'special_issues' => $this->checkSpecialIssues(),
            'contracts' => $this->checkContracts(),
        ]);
    }

    private function checkInventory(): array {
        $threshold = shop_setting('inventory_low_stock_threshold', 5);
        $lowStockProducts = Product::where('type', 'physical')
            ->where('track_quantity', true)
            ->where('quantity', '<', $threshold)
            ->where('status', 'active')
            ->get();

        return [
            'title' => 'Lagerbestand',
            'status' => $lowStockProducts->count() > 0 ? 'danger' : 'success',
            'message' => $lowStockProducts->count() > 0 ? $lowStockProducts->count() . " Artikel unter Limit!" : "Lagerbestände optimal.",
            'icon' => 'bi-box-seam',
            'count' => $lowStockProducts->count(),
            'data' => $lowStockProducts
        ];
    }

    private function checkSpecialIssues(): array {
        $missing = FinanceSpecialIssue::where(function($query) {
            $query->whereNull('file_paths')->orWhere('file_paths', '[]')->orWhere('file_paths', '');
        })->orderBy('execution_date', 'desc')->get();

        return [
            'title' => 'Sonderausgaben',
            'status' => $missing->count() > 0 ? 'danger' : 'success',
            'message' => $missing->count() > 0 ? $missing->count() . " Positionen ohne Beleg." : "Alle Ausgaben belegt.",
            'icon' => 'bi-receipt',
            'count' => $missing->count(),
            'data' => $missing
        ];
    }

    private function checkContracts(): array {
        $missing = FinanceCostItem::whereNull('contract_file_path')->with('group')->get();

        return [
            'title' => 'Verträge',
            'status' => $missing->count() > 0 ? 'danger' : 'success',
            'message' => $missing->count() > 0 ? $missing->count() . " Unterlagen fehlen." : "Dokumente vollständig.",
            'icon' => 'bi-file-earmark-text',
            'count' => $missing->count(),
            'data' => $missing
        ];
    }

    public function updateStock($productId)
    {
        $newQty = $this->stockUpdate[$productId] ?? null;
        if ($newQty === null || $newQty < 0) return;

        $product = Product::find($productId);
        if ($product) {
            $product->update(['quantity' => $newQty]);
            unset($this->stockUpdate[$productId]);
            session()->flash('success', 'Bestand aktualisiert.');
        }
    }

    public function uploadContract($itemId)
    {
        $this->validate(['uploadFile' => 'required|file|max:10240']);
        $item = FinanceCostItem::find($itemId);
        if ($item) {
            $path = $this->uploadFile->store('contracts', 'public');
            $item->update(['contract_file_path' => $path]);
            $this->reset('uploadFile');
            session()->flash('success', 'Vertrag erfolgreich hochgeladen.');
        }
    }

    public function uploadSpecialReceipt($issueId)
    {
        $this->validate(['uploadFile' => 'required|file|max:10240']);
        $issue = FinanceSpecialIssue::find($issueId);
        if ($issue) {
            $path = $this->uploadFile->store('financial/receipts', 'public');
            $files = $issue->file_paths ?? [];
            $files[] = $path;
            $issue->update(['file_paths' => $files]);
            $this->reset('uploadFile');
            session()->flash('success', 'Beleg erfolgreich hochgeladen.');
        }
    }

    public function toggleHealthCard($key)
    {
        // Wenn die angeklickte Kachel bereits offen ist, schließen (null setzen).
        // Andernfalls die neue Kachel öffnen.
        if ($this->expandedHealthKey === $key) {
            $this->expandedHealthKey = null;
        } else {
            $this->expandedHealthKey = $key;
        }
    }

    public function render()
    {
        return view('livewire.global.widgets.health-check');
    }
}
