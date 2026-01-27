<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use Livewire\Component;
use Livewire\WithPagination;

class Orders extends Component
{
    use WithPagination;

    // Filter
    public $search = '';
    public $statusFilter = '';
    public $paymentFilter = '';
    public $dateFilter = '';

    // Sortierung
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    // --- NEU: Modal State ---
    public $showDetailModal = false;
    public $selectedOrderId = null;

    protected $queryString = ['search', 'statusFilter', 'paymentFilter', 'sortField', 'sortDirection'];

    // --- ACTIONS ---

    // NEU: Bestellung ins Modal laden
    public function openDetail($id)
    {
        $this->selectedOrderId = $id;
        $this->showDetailModal = true;
    }

    public function closeDetail()
    {
        $this->showDetailModal = false;
        $this->selectedOrderId = null;
    }

    public function markAsPaid($orderId)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->update(['payment_status' => 'paid']);
            session()->flash('success', "Bestellung {$order->order_number} als BEZAHLT markiert.");
            // Falls Modal offen ist, aktualisieren wir es indirekt durch re-render
        }
    }

    public function updateStatus($orderId, $newStatus)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->update(['status' => $newStatus]);
            session()->flash('success', "Status aktualisiert.");
        }
    }

    public function delete($id)
    {
        $order = Order::find($id);
        if ($order) {
            $order->delete();
            $this->closeDetail(); // Falls Modal offen war
            session()->flash('success', 'Bestellung gelöscht.');
        }
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

    // Computed Property für das Modal (lädt Daten nur wenn nötig)
    public function getSelectedOrderProperty()
    {
        if (!$this->selectedOrderId) return null;
        return Order::with('items')->find($this->selectedOrderId);
    }

    public function render()
    {
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
        if ($this->dateFilter) {
            if ($this->dateFilter === 'today') $query->whereDate('created_at', today());
            if ($this->dateFilter === 'week') $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            if ($this->dateFilter === 'month') $query->whereMonth('created_at', now()->month);
        }

        $orders = $query->orderBy($this->sortField, $this->sortDirection)->paginate(10);

        return view('livewire.shop.orders', [
            'orders' => $orders,
            'stats' => [
                'total' => Order::count(),
                'open' => Order::where('status', 'pending')->orWhere('status', 'processing')->count(),
                'revenue_today' => Order::whereDate('created_at', today())->where('payment_status', 'paid')->sum('total_price'),
            ]
        ]);
    }
}
