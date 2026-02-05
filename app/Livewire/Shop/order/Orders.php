<?php

namespace App\Livewire\Shop\order;

use App\Models\Order;
use App\Models\OrderItem;
use Livewire\Component;
use Livewire\WithPagination;

class Orders extends Component
{
    use WithPagination;

    // --- FILTER & SORTIERUNG ---
    public $search = '';
    public $statusFilter = '';
    public $paymentFilter = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    // --- STATE FÜR DETAIL-ANSICHT ---
    public $selectedOrderId = null;
    public $selectedOrderItemId = null;

    // --- STATE FÜR BEARBEITUNG ---
    public ?Order $selectedOrder = null;

    // Formular-Felder
    public $status;
    public $payment_status;
    public $notes;
    public $cancellationReason = '';

    protected $queryString = ['search', 'statusFilter', 'paymentFilter'];

    // --- ACTIONS: DETAIL ANSICHT ---

    public function openDetail($id)
    {
        $this->selectedOrderId = $id;

        // 1. Order laden
        $this->selectedOrder = Order::with(['items.product', 'invoices'])->find($id);

        // 2. Daten laden
        if ($this->selectedOrder) {
            $this->status = $this->selectedOrder->status;
            $this->payment_status = $this->selectedOrder->payment_status;
            $this->notes = $this->selectedOrder->notes;
            // Sicherstellen, dass es ein String ist
            $this->cancellationReason = $this->selectedOrder->cancellation_reason ?? '';

            if ($this->selectedOrder->items->isNotEmpty()) {
                $this->selectedOrderItemId = $this->selectedOrder->items->first()->id;
            }
        }
    }

    public function closeDetail()
    {
        $this->selectedOrderId = null;
        $this->selectedOrderItemId = null;
        $this->selectedOrder = null;
        $this->reset(['status', 'payment_status', 'notes', 'cancellationReason']);
    }

    public function selectItemForPreview($itemId)
    {
        $this->selectedOrderItemId = $itemId;
    }

    // --- ACTIONS: SPEICHERN (FIXED) ---

    public function saveStatus()
    {
        if (!$this->selectedOrder) return;

        // Fall: Stornierung
        if ($this->status === 'cancelled') {

            // Sicherheitscheck: Nur Bestand zurückgeben, wenn...
            // 1. Die Bestellung vorher noch nicht storniert war
            // 2. UND die Bestellung noch NICHT in Bearbeitung war (da sonst schon graviert)
            if ($this->selectedOrder->status !== 'cancelled' && $this->selectedOrder->status === 'pending') {
                foreach ($this->selectedOrder->items as $item) {
                    if ($item->product) {
                        $item->product->restoreStock($item->quantity);
                    }
                }
                session()->flash('info', 'Bestand wurde zurückgebucht (da noch nicht in Bearbeitung).');
            } else {
                session()->flash('warning', 'Storniert ohne Bestandsrückbuchung (da bereits in Bearbeitung oder bereits storniert).');
            }

            $this->validate([
                'cancellationReason' => 'required|string|min:5|max:500',
            ], [
                'cancellationReason.required' => 'Bitte gib einen Grund für die Stornierung an.',
                'cancellationReason.min' => 'Der Grund ist zu kurz (min. 5 Zeichen).',
            ]);

            // Model Methode aufrufen (Stellt Status auf cancelled & speichert Grund)
            // Falls du cancel() im Model noch nicht hast, geht auch:
            // $this->selectedOrder->update(['status' => 'cancelled', 'cancellation_reason' => $this->cancellationReason]);
            $this->selectedOrder->cancel($this->cancellationReason);

            // Payment Status & Notizen auch aktualisieren
            $this->selectedOrder->update([
                'payment_status' => $this->payment_status,
                'notes' => $this->notes
            ]);

            // WICHTIG: Das Model neu laden, damit die View merkt, dass es gespeichert wurde!
            $this->selectedOrder->refresh();

            session()->flash('success', 'Bestellung erfolgreich storniert.');

        } else {
            // Fall: Normales Update
            $this->selectedOrder->update([
                'status' => $this->status,
                'payment_status' => $this->payment_status,
                'notes' => $this->notes,
                'cancellation_reason' => null // Grund entfernen, falls Status geändert wurde
            ]);

            // Auch hier neu laden
            $this->selectedOrder->refresh();

            session()->flash('success', 'Bestelldetails aktualisiert.');
        }
    }

    // --- ACTIONS: LIST VIEW / SCHNELL-AKTIONEN ---

    public function updateStatus($orderId, $newStatus)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->update(['status' => $newStatus]);

            // Falls Detailansicht offen ist -> Sync
            if ($this->selectedOrder && $this->selectedOrder->id == $orderId) {
                $this->status = $newStatus;
                $this->selectedOrder->refresh();
            }
            session()->flash('success', "Status auf '$newStatus' geändert.");
        }
    }

    public function markAsPaid($orderId)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->update(['payment_status' => 'paid']);

            if ($this->selectedOrder && $this->selectedOrder->id == $orderId) {
                $this->payment_status = 'paid';
                $this->selectedOrder->refresh();
            }
            session()->flash('success', 'Bestellung als bezahlt markiert.');
        }
    }

    public function delete($orderId)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->delete();
            $this->closeDetail();
            session()->flash('success', 'Bestellung in den Papierkorb verschoben.');
        }
    }

    // --- PROPERTIES & HELPER ---

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
        // 1. DETAIL ANSICHT
        if ($this->selectedOrderId && $this->selectedOrder) {
            return view('livewire.shop.order.orders', [
                'order' => $this->selectedOrder,
                'orders' => [],
                'stats' => []
            ]);
        }

        // 2. LISTEN ANSICHT
        $query = Order::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('order_number', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%')
                    ->orWhere('billing_address->last_name', 'like', '%' . $this->search . '%')
                    ->orWhere('billing_address->first_name', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter) $query->where('status', $this->statusFilter);
        if ($this->paymentFilter) $query->where('payment_status', $this->paymentFilter);

        // --- SORTIER-LOGIK ---
        if ($this->sortField === 'customer') {
            $query->orderBy('billing_address->last_name', $this->sortDirection)
                ->orderBy('billing_address->first_name', $this->sortDirection);
        } elseif ($this->sortField === 'total') {
            $query->orderBy('total_price', $this->sortDirection);
        } elseif ($this->sortField === 'payment') {
            $query->orderBy('payment_status', $this->sortDirection);
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        $orders = $query->paginate(10);

        return view('livewire.shop.order.orders', [
            'orders' => $orders,
            'stats' => [
                'total' => Order::count(),
                'open' => Order::whereIn('status', ['pending', 'processing'])->count(),
                'revenue_today' => Order::whereDate('created_at', today())->sum('total_price'),
            ]
        ]);
    }
}
