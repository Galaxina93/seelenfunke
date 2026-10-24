<?php

namespace App\Livewire\Shop\Product;

use Livewire\Attributes\Layout;

use App\Models\Product\Product;
use App\Models\Product\ProductLoss;
use App\Models\Product\ProductSupplier;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Traits\WithDepartmentTheming;

#[Layout('components.layouts.backend_layout')]
class ProductFracture extends Component
{
    use WithPagination, WithDepartmentTheming;

    public string $themingDepartment = 'Produkte';

    // Workflow state
    public $lossModalOpen = false;
    public $currentStep = 1;

    // Form data
    public $lossProductId = null;
    public $lossQuantity = 1;
    public $lossReason = '';
    public $lossSupplierId = null;

    // View data for Step 3
    public $selectedSupplier = null;
    
    // Inline Edit state
    #[\Livewire\Attributes\Locked]
    public $editLossId = null;
    public $editLossQuantity = 1;
    public $editLossReason = '';

    public function boot(): void
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\Illuminate\Support\Facades\Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\Illuminate\Support\Facades\Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function openLossModal()
    {
        $this->reset(['lossProductId', 'lossQuantity', 'lossReason', 'editLossId']);
        $this->lossQuantity = 1;
        $this->lossModalOpen = true;
    }

    public function createLossRecord()
    {
        $this->validate([
            'lossProductId' => 'required',
            'lossQuantity' => 'required|integer|min:1|max:100000',
            'lossReason' => 'required|string|min:3|max:1000'
        ]);

        $created = \Illuminate\Support\Facades\DB::transaction(function () {
            $product = Product::where('id', $this->lossProductId)->lockForUpdate()->firstOrFail();

            if ($product->quantity < $this->lossQuantity) {
                $this->addError('lossQuantity', 'Nicht genug Bestand vorhanden.');
                $this->currentStep = 1;
                return false;
            }

            $costValue = ($product->purchase_price ?? 0) * $this->lossQuantity;

            ProductLoss::create([
                'product_id' => $product->id,
                'product_supplier_id' => null,
                'quantity' => $this->lossQuantity,
                'cost_value' => $costValue,
                'reason' => $this->lossReason,
                'recorded_by' => auth('admin')->id(),
                // Leave reported and refund timestamps null initially
            ]);

            $product->reduceStock($this->lossQuantity);
            return true;
        });

        if (!$created) {
            return;
        }
        
        $this->dispatch('toast', message: 'Schadensmeldung erfasst und Lagerbestand aktualisiert.', type: 'success');
        $this->closeLossModal();
    }

    public function assignSupplier($id, $supplierId)
    {
        $loss = ProductLoss::findOrFail($id);
        $validSupplierId = (!empty($supplierId) && ProductSupplier::where('id', $supplierId)->exists()) ? $supplierId : null;
        $loss->update(['product_supplier_id' => $validSupplierId]);
        $this->dispatch('toast', message: 'Händler zugewiesen.', type: 'success');
    }

    public function unassignSupplier($id)
    {
        $loss = ProductLoss::findOrFail($id);
        // Cascade nulls if supplier is removed, we must also reset reported/refund status
        $loss->update([
            'product_supplier_id' => null,
            'reported_to_supplier_at' => null,
            'refund_received_at' => null,
        ]);
        $this->dispatch('toast', message: 'Händler-Verknüpfung gelöst.', type: 'info');
    }

    public function markAsReported($id)
    {
        $loss = ProductLoss::findOrFail($id);
        $loss->update(['reported_to_supplier_at' => now()]);
        $this->dispatch('toast', message: 'Als beim Händler reklamiert markiert.', type: 'success');
        
        if ($this->lossModalOpen) {
            $this->closeLossModal();
        }
    }

    public function undoReported($id)
    {
        $loss = ProductLoss::findOrFail($id);
        $loss->update(['reported_to_supplier_at' => null, 'refund_received_at' => null]);
        $this->dispatch('toast', message: 'Ticket-Status zurückgesetzt (Nicht gemeldet).', type: 'info');
    }

    public function markAsRefunded($id)
    {
        $loss = ProductLoss::findOrFail($id);
        $loss->update(['refund_received_at' => now()]);
        $this->dispatch('toast', message: 'Rückerstattung erfolgreich verbucht! Vorgang abgeschlossen.', type: 'success');
    }

    public function undoRefunded($id)
    {
        $loss = ProductLoss::findOrFail($id);
        $loss->update(['refund_received_at' => null]);
        $this->dispatch('toast', message: 'Erstattungs-Status zurückgesetzt.', type: 'info');
    }

    public function closeLossModal()
    {
        $this->lossModalOpen = false;
        $this->reset(['currentStep', 'lossProductId', 'lossQuantity', 'lossReason', 'lossSupplierId', 'selectedSupplier']);
    }

    public function startEditLoss($id)
    {
        $loss = ProductLoss::findOrFail($id);
        $this->editLossId = $id;
        $this->editLossQuantity = $loss->quantity;
        $this->editLossReason = $loss->reason;
    }

    public function cancelEditLoss()
    {
        $this->editLossId = null;
    }

    public function updateLoss()
    {
        $this->validate([
            'editLossQuantity' => 'required|integer|min:1|max:100000',
            'editLossReason' => 'required|string|min:3|max:1000'
        ]);

        $updated = \Illuminate\Support\Facades\DB::transaction(function () {
            $loss = ProductLoss::where('id', $this->editLossId)->lockForUpdate()->firstOrFail();
            $product = $loss->product_id ? Product::where('id', $loss->product_id)->lockForUpdate()->first() : null;

            if (!$product) {
                $this->addError('editLossQuantity', 'Produkt nicht mehr im System.');
                return false;
            }

            $difference = $this->editLossQuantity - $loss->quantity;
            
            if ($difference > 0 && $product->quantity < $difference) {
                $this->addError('editLossQuantity', 'Nicht genug Bestand.');
                return false;
            }

            if ($difference > 0) {
                $product->reduceStock($difference);
            } elseif ($difference < 0) {
                $product->restoreStock(abs($difference));
            }

            $costValue = ($product->purchase_price ?? 0) * $this->editLossQuantity;

            $loss->update([
                'quantity' => $this->editLossQuantity,
                'reason' => $this->editLossReason,
                'cost_value' => $costValue,
            ]);

            return true;
        });

        if (!$updated) {
            return;
        }

        $this->editLossId = null;
        $this->dispatch('toast', message: 'Schadensmeldung aktualisiert.', type: 'success');
    }

    public function deleteLoss($id)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
            $loss = ProductLoss::where('id', $id)->lockForUpdate()->firstOrFail();
            $product = $loss->product_id ? Product::where('id', $loss->product_id)->lockForUpdate()->first() : null;
            
            if ($product) {
                $product->restoreStock($loss->quantity);
            }

            $loss->delete();
        });
        $this->dispatch('toast', message: 'Eintrag storniert und Bestand wiederhergestellt.', type: 'info');
    }

    public function render()
    {
        $losses = ProductLoss::with(['product', 'supplier'])->latest()->paginate(10);
        $products = Product::where('status', 'active')->where('type', 'physical')->orderBy('name')->get();
        $suppliers = ProductSupplier::orderBy('name')->get();

        $metrics = [
            'total_open' => ProductLoss::whereNull('refund_received_at')->count(),
            'total_refunded_this_month' => ProductLoss::whereNotNull('refund_received_at')->where('refund_received_at', '>=', now()->startOfMonth())->sum('cost_value') / 100,
            'total_loss_this_month' => ProductLoss::where('created_at', '>=', now()->startOfMonth())->sum('cost_value') / 100,
            'total_loss_all_time' => ProductLoss::sum('cost_value') / 100,
        ];

        return view('livewire.shop.product.product-fracture', [
            'losses' => $losses,
            'products' => $products,
            'suppliers' => $suppliers,
            'metrics' => $metrics,
        ]);
    }
}
