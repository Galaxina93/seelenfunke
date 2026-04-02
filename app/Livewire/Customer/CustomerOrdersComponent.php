<?php

namespace App\Livewire\Customer;


use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem; // Anpassen, falls dein Model anders heißt
use Livewire\Attributes\On;

class CustomerOrdersComponent extends Component
{
    use WithPagination;

    public $searchOrder = '';
    public $selectedOrderId = null;
    public $previewItemId = null;
    public $editItemId = null;
    public $showSuccessMessageFor = null;

    // Wenn gesucht wird, springen wir zurück auf Seite 1
    public function updatingSearchOrder()
    {
        $this->resetPage();
    }

    public function showOrder($id)
    {
        $this->selectedOrderId = $id;
        $this->previewItemId = null;
    }

    public function resetOrderView()
    {
        $this->selectedOrderId = null;
        $this->previewItemId = null;
    }

    public function openPreview($itemId)
    {
        $this->editItemId = null; // Close edit if open
        if ($this->previewItemId === $itemId) {
            $this->previewItemId = null; // Toggle zum Schließen
        } else {
            $this->previewItemId = $itemId;
        }
    }

    public function openEdit($itemId)
    {
        $this->previewItemId = null; // Close preview if open
        if ($this->editItemId === $itemId) {
            $this->editItemId = null; // Toggle zum Schließen
        } else {
            $this->editItemId = $itemId;
        }
    }

    #[On('order-item-updated')]
    public function handleItemUpdated($itemId)
    {
        $this->editItemId = null;
        $this->showSuccessMessageFor = $itemId;
    }

    // Computed Property für die ausgewählte Bestellung
    public function getSelectedOrderProperty()
    {
        if (!$this->selectedOrderId) return null;
        return OrderOrder::with(['items.product', 'invoices', 'shipments'])->find($this->selectedOrderId);
    }

    // Computed Property für das Vorschaubild (Manufaktur Details)
    public function getPreviewItemProperty()
    {
        if (!$this->previewItemId) return null;
        return OrderOrderItem::with('product')->find($this->previewItemId);
    }

    // Computed Property für die Edit-Funktion
    public function getEditItemProperty()
    {
        if (!$this->editItemId) return null;
        return OrderOrderItem::with('product')->find($this->editItemId);
    }

    public function render()
    {
        $user = Auth::guard('customer')->user();

        $query = OrderOrder::where('customer_id', $user->id)
            ->with(['items.product', 'shipments']); // Eager Loading für Performance

        if (!empty($this->searchOrder)) {
            $query->where('order_number', 'like', '%' . $this->searchOrder . '%');
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('livewire.customer.customer-orders-component', [
            'orders' => $orders,
            'selectedOrder' => $this->selectedOrder,
        ])->layout('components.layouts.customer_layout');
    }
}
