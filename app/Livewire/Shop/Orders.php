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
    public $dateFilter = ''; // 'today', 'week', 'month'

    // Sortierung
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    protected $queryString = ['search', 'statusFilter', 'paymentFilter', 'sortField', 'sortDirection'];

    // --- SHORTCUT ACTIONS ---

    public function markAsPaid($orderId)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->update(['payment_status' => 'paid']);
            // Optional: Rechnung generieren & Mail senden Logik hier
            session()->flash('success', "Bestellung {$order->order_number} als BEZAHLT markiert.");
        }
    }

    public function updateStatus($orderId, $newStatus)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->update(['status' => $newStatus]);
            session()->flash('success', "Status von {$order->order_number} auf " . strtoupper($newStatus) . " geändert.");
        }
    }

    public function delete($id)
    {
        // Soft Delete
        $order = Order::find($id);
        if ($order) {
            $order->delete();
            session()->flash('success', 'Bestellung in den Papierkorb verschoben.');
        }
    }

    // --- SORTIERUNG ---
    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function render()
    {
        $query = Order::query();

        // 1. Suche
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('order_number', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%')
                    // JSON Suche (MySQL Syntax) - Optional, falls Performance ok
                    ->orWhere('billing_address->last_name', 'like', '%' . $this->search . '%');
            });
        }

        // 2. Filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->paymentFilter) {
            $query->where('payment_status', $this->paymentFilter);
        }
        if ($this->dateFilter) {
            if ($this->dateFilter === 'today') $query->whereDate('created_at', today());
            if ($this->dateFilter === 'week') $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            if ($this->dateFilter === 'month') $query->whereMonth('created_at', now()->month);
        }

        // 3. Sortierung
        $orders = $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

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
