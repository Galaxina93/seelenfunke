<?php

namespace App\Livewire\Customer;


use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Models\Order\Order;
use App\Models\Order\OrderItem; // Anpassen, falls dein Model anders heißt

class OrdersComponent extends Component
{
    use WithPagination;

    public $searchOrder = '';
    public $selectedOrderId = null;
    public $previewItemId = null;

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
        if ($this->previewItemId === $itemId) {
            $this->previewItemId = null; // Toggle zum Schließen
        } else {
            $this->previewItemId = $itemId;
        }
    }

    // Computed Property für die ausgewählte Bestellung
    public function getSelectedOrderProperty()
    {
        if (!$this->selectedOrderId) return null;
        return Order::with(['items.product', 'invoices'])->find($this->selectedOrderId);
    }

    // Computed Property für das Vorschaubild (Manufaktur Details)
    public function getPreviewItemProperty()
    {
        if (!$this->previewItemId) return null;
        return OrderItem::with('product')->find($this->previewItemId);
    }

    public function render()
    {
        $user = Auth::guard('customer')->user();

        $query = Order::where('customer_id', $user->id)
            ->with(['items.product']); // Eager Loading für Performance

        if (!empty($this->searchOrder)) {
            $query->where('order_number', 'like', '%' . $this->searchOrder . '%');
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('livewire.customer.orders-component', [
            'orders' => $orders,
            'selectedOrder' => $this->selectedOrder,
        ])->layout('components.layouts.customer_layout');
    }
}
