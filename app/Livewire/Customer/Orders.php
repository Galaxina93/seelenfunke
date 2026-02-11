<?php

namespace App\Livewire\Customer;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

// Import für die Vorschau-Logik
// Für die previewItem Property

class Orders extends Component
{
    use WithPagination;

    public $search = '';

    // ID der ausgewählten Order (wenn null => Listenansicht)
    public $selectedOrderId = null;

    // NEU: ID des Items, das im Konfigurator angezeigt werden soll
    public $previewItemId = null;

    #[Layout('components.layouts.backend_layout', ['guard' => 'customer'])]
    public function render()
    {
        // A) Detail-Ansicht Modus
        if ($this->selectedOrderId) {
            $selectedOrder = Order::with(['items.product', 'invoices'])
                ->where('customer_id', Auth::guard('customer')->id())
                ->find($this->selectedOrderId);

            // Falls Order nicht gefunden (oder Zugriff verweigert), zurück zur Liste
            if (!$selectedOrder) {
                $this->selectedOrderId = null;
            }

            return view('livewire.customer.orders', [
                'viewMode' => 'detail',
                'order' => $selectedOrder
            ]);
        }

        // B) Listen-Ansicht Modus
        $query = Order::where('customer_id', Auth::guard('customer')->id());

        if ($this->search) {
            $query->where('order_number', 'like', '%' . $this->search . '%');
        }

        return view('livewire.customer.orders', [
            'viewMode' => 'list',
            'orders' => $query->latest()->paginate(10)
        ]);
    }

    // ACTIONS
    public function showOrder($id)
    {
        $this->selectedOrderId = $id;
        $this->previewItemId = null; // Reset bei neuer Order
    }

    public function resetView()
    {
        $this->selectedOrderId = null;
        $this->previewItemId = null;
    }

    // NEU: Steuerung der Konfigurator-Vorschau
    public function openPreview($itemId)
    {
        // Wenn die geklickte ID bereits die aktive Vorschau ist -> Schließen (auf null setzen)
        if ($this->previewItemId == $itemId) {
            $this->previewItemId = null;
        } else {
            // Ansonsten die neue ID setzen
            $this->previewItemId = $itemId;
        }
    }

    public function closePreview()
    {
        $this->previewItemId = null;
    }

    #[Computed]
    public function previewItem()
    {
        if (!$this->previewItemId) return null;
        return OrderItem::with('product')->find($this->previewItemId);
    }
}
