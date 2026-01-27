<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use App\Models\OrderItem;
use Livewire\Component;
use Livewire\WithPagination;

class Orders extends Component
{
    use WithPagination;

    // Filter & Sortierung
    public $search = '';
    public $statusFilter = '';
    public $paymentFilter = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    // State für Detail-Ansicht
    public $selectedOrderId = null;
    public $selectedOrderItemId = null;

    protected $queryString = ['search', 'statusFilter', 'paymentFilter'];

    // --- ACTIONS ---

    public function openDetail($id)
    {
        $this->selectedOrderId = $id;

        // Automatisch das erste Item für den Configurator auswählen
        $order = Order::with('items')->find($id);
        if ($order && $order->items->isNotEmpty()) {
            $this->selectedOrderItemId = $order->items->first()->id;
        }
    }

    public function closeDetail()
    {
        $this->selectedOrderId = null;
        $this->selectedOrderItemId = null;
    }

    public function selectItemForPreview($itemId)
    {
        $this->selectedOrderItemId = $itemId;
    }

    // FEHLERBEHEBUNG 3: Die fehlenden Methoden wurden hinzugefügt

    public function markAsPaid($orderId)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->update(['payment_status' => 'paid']);
            // Optional: Rechnungsgenerierung hier triggern oder Event dispatchen
            session()->flash('success', 'Bestellung als bezahlt markiert.');
        }
    }

    public function updateStatus($orderId, $newStatus)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->update(['status' => $newStatus]);
            session()->flash('success', "Status auf '$newStatus' geändert.");
        }
    }

    public function delete($orderId)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->delete(); // Soft Delete (da Trait im Model)
            $this->closeDetail(); // Zurück zur Liste
            session()->flash('success', 'Bestellung wurde in den Papierkorb verschoben.');
        }
    }

    // --- PROPERTIES ---

    public function getSelectedOrderProperty()
    {
        return Order::with('items.product')->find($this->selectedOrderId);
    }

    public function getPreviewItemProperty()
    {
        if (!$this->selectedOrderId || !$this->selectedOrderItemId) return null;
        return OrderItem::with('product')->find($this->selectedOrderItemId);
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    // --- RENDER ---

    public function render()
    {
        // Detail View Mode
        if ($this->selectedOrderId) {
            return view('livewire.shop.orders', [
                'orders' => [],
                'stats' => []
            ]);
        }

        // List View Mode
        $query = Order::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('order_number', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%')
                    ->orWhere('billing_address->last_name', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter) $query->where('status', $this->statusFilter);
        if ($this->paymentFilter) $query->where('payment_status', $this->paymentFilter);

        $orders = $query->orderBy($this->sortField, $this->sortDirection)->paginate(10);

        return view('livewire.shop.orders', [
            'orders' => $orders,
            'stats' => [
                'total' => Order::count(),
                'open' => Order::whereIn('status', ['pending', 'processing'])->count(),
                'revenue_today' => Order::whereDate('created_at', today())->sum('total_price'),
            ]
        ]);
    }
}
