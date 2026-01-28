<?php

namespace App\Livewire\Customer;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

class Orders extends Component
{
    use WithPagination;

    public $search = '';

    // ID der ausgewählten Order (wenn null => Listenansicht)
    public $selectedOrderId = null;

    #[Layout('components.layouts.backend_layout', ['guard' => 'customer'])]
    public function render()
    {
        // A) Detail-Ansicht Modus
        if ($this->selectedOrderId) {
            $selectedOrder = Order::with(['items', 'invoices'])
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
    }

    public function resetView()
    {
        $this->selectedOrderId = null;
    }
}
