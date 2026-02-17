<?php

namespace App\Livewire\Shop\Order;

use App\Mail\NewOrderShippedToCustomer;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithPagination;

class Orders extends Component
{
    use WithPagination;

    // --- FILTER & SORTIERUNG ---
    public $search = '';
    public $statusFilter = '';
    public $paymentFilter = '';

    // Standard: Created At, aber wir nutzen benutzerdefinierte Logik im Render
    public $sortField = 'default_workflow';
    public $sortDirection = 'asc';

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

    // STATUS FÜR DAS SICHERHEITS-MODAL
    public $confirmingShipmentId = null;

    protected $queryString = ['search', 'statusFilter', 'paymentFilter', 'sortField', 'sortDirection'];

    // --- ACTIONS: SORTIERUNG ---

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    // --- ACTIONS: DETAIL ANSICHT ---

    public function openDetail($id)
    {
        $this->selectedOrderId = $id;

        // 1. Order laden (Eager Loading für Performance)
        $this->selectedOrder = Order::with(['items.product', 'invoices'])->find($id);

        // 2. Daten laden
        if ($this->selectedOrder) {
            $this->status = $this->selectedOrder->status;
            $this->payment_status = $this->selectedOrder->payment_status;
            $this->notes = $this->selectedOrder->notes;
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

    // --- ACTIONS: SPEICHERN ---

    public function saveStatus()
    {
        if (!$this->selectedOrder) return;

        // Sicherheits-Modal für Versand
        if ($this->status === 'shipped' && $this->selectedOrder->status !== 'shipped') {
            $this->confirmingShipmentId = $this->selectedOrder->id;
            return;
        }

        // Fall: Stornierung
        if ($this->status === 'cancelled') {
            // Bestand prüfen & zurückbuchen wenn noch nicht in Arbeit
            if ($this->selectedOrder->status !== 'cancelled' && $this->selectedOrder->status === 'pending') {
                foreach ($this->selectedOrder->items as $item) {
                    if ($item->product) $item->product->restoreStock($item->quantity);
                }
                session()->flash('info', 'Bestand wurde zurückgebucht.');
            }

            $this->validate([
                'cancellationReason' => 'required|string|min:5|max:500',
            ]);

            // Status Update
            $this->selectedOrder->update([
                'status' => 'cancelled',
                'payment_status' => $this->payment_status, // Geldstatus kann abweichen (z.B. refunded)
                'notes' => $this->notes,
                'cancellation_reason' => $this->cancellationReason
            ]);

            $this->selectedOrder->refresh();
            session()->flash('success', 'Bestellung storniert.');

        } else {
            // Normales Update
            $this->selectedOrder->update([
                'status' => $this->status,
                'payment_status' => $this->payment_status,
                'notes' => $this->notes,
                'cancellation_reason' => null
            ]);

            $this->selectedOrder->refresh();
            session()->flash('success', 'Bestelldetails aktualisiert.');
        }
    }

    // --- ACTIONS: SCHNELL-UPDATE ---

    public function updateStatus($orderId, $newStatus)
    {
        $order = Order::find($orderId);
        if (!$order) return;

        if ($newStatus === 'shipped' && $order->status !== 'shipped') {
            $this->confirmingShipmentId = $orderId;
            return;
        }

        $order->update(['status' => $newStatus]);

        if ($this->selectedOrder && $this->selectedOrder->id == $orderId) {
            $this->status = $newStatus;
            $this->selectedOrder->refresh();
        }
        session()->flash('success', "Status aktualisiert.");
    }

    public function confirmShipment($sendMail = true)
    {
        if (!$this->confirmingShipmentId) return;

        $order = Order::find($this->confirmingShipmentId);
        if ($order) {
            $order->update(['status' => 'shipped']);

            if ($sendMail) {
                try {
                    Mail::to($order->email)->send(new NewOrderShippedToCustomer($order->toFormattedArray()));
                    session()->flash('success', 'Status geändert & Mail versendet! 🚀');
                } catch (\Exception $e) {
                    session()->flash('warning', 'Status geändert, Mail-Fehler: ' . $e->getMessage());
                }
            } else {
                session()->flash('success', 'Status auf Versendet gesetzt.');
            }

            if ($this->selectedOrder && $this->selectedOrder->id == $order->id) {
                $this->status = 'shipped';
                $this->selectedOrder->refresh();
            }
        }
        $this->confirmingShipmentId = null;
    }

    public function cancelShipment()
    {
        $this->confirmingShipmentId = null;
        if ($this->selectedOrder) {
            $this->status = $this->selectedOrder->status;
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
            session()->flash('success', 'Zahlung bestätigt.');
        }
    }

    public function delete($orderId)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->delete();
            $this->closeDetail();
            session()->flash('success', 'Bestellung gelöscht.');
        }
    }

    // --- COMPUTED PROPERTIES ---

    public function getPreviewItemProperty()
    {
        if (!$this->selectedOrderId || !$this->selectedOrderItemId) return null;
        return OrderItem::with('product')->find($this->selectedOrderItemId);
    }

    // --- RENDER ---

    public function render()
    {
        // A) Detail-Modus
        if ($this->selectedOrderId && $this->selectedOrder) {
            return view('livewire.shop.order.orders', [
                'orders' => [], 'activeOrders' => [], 'archivedOrders' => [], 'stats' => []
            ]);
        }

        // B) Listen-Modus
        $query = Order::query();

        // 1. Suche
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('order_number', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%')
                    ->orWhere('billing_address->last_name', 'like', '%' . $this->search . '%');
            });
        }

        // 2. Filter
        if ($this->statusFilter) $query->where('status', $this->statusFilter);
        if ($this->paymentFilter) $query->where('payment_status', $this->paymentFilter);

        // STATISTIKEN BERECHNEN (Vor dem Splitting)
        $stats = [
            'total' => Order::count(),
            'open' => Order::whereIn('status', ['pending', 'processing'])->count(),
            'open_express' => Order::whereIn('status', ['pending', 'processing'])->where('is_express', true)->count(),
            'revenue_today' => Order::whereDate('created_at', today())->sum('total_price'),
            'revenue_month' => Order::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('total_price'),
            'avg_cart' => Order::where('status', 'completed')->avg('total_price') ?? 0,
        ];

        // 3. SPLITTING & SORTIERUNG

        // --- A) Aktuelle Aufgaben (Wartend & In Bearbeitung) ---
        // Workflow: Hier IMMER "Express -> Älteste zuerst", außer User sortiert explizit anders
        $activeQuery = clone $query;
        $activeQuery->whereIn('status', ['pending', 'processing']);

        if ($this->sortField === 'default_workflow') {
            // Workflow Logik: Erst Express, dann Älteste (FIFO)
            $activeQuery->orderByDesc('is_express')
                ->orderBy('created_at', 'asc');
        } else {
            // Benutzerdefinierte Sortierung (Klick auf Spalte)
            $this->applyCustomSort($activeQuery);
        }

        // Aktive laden wir alle (Pagination hier oft störend für Workflow, max 100 sicherheitshalber)
        $activeOrders = $activeQuery->limit(100)->get();


        // --- B) Archiv (Versendet, Fertig, Storniert) ---
        // Workflow: Hier Standard "Neueste zuerst"
        $archivedQuery = clone $query;
        $archivedQuery->whereNotIn('status', ['pending', 'processing']);

        if ($this->sortField === 'default_workflow') {
            $archivedQuery->orderBy('created_at', 'desc'); // Historie: Neueste oben
        } else {
            $this->applyCustomSort($archivedQuery);
        }

        $archivedOrders = $archivedQuery->paginate(15);

        return view('livewire.shop.order.orders', [
            'activeOrders' => $activeOrders,
            'archivedOrders' => $archivedOrders,
            'orders' => $archivedOrders, // Fallback für Pagination Links
            'stats' => $stats
        ]);
    }

    // Hilfsfunktion für die dynamische Sortierung
    private function applyCustomSort($query)
    {
        switch ($this->sortField) {
            case 'customer':
                $query->orderBy('billing_address->last_name', $this->sortDirection);
                break;
            case 'total':
                $query->orderBy('total_price', $this->sortDirection);
                break;
            case 'payment':
                $query->orderBy('payment_status', $this->sortDirection);
                break;
            case 'status':
                $query->orderBy('status', $this->sortDirection);
                break;
            case 'order_number':
                $query->orderBy('order_number', $this->sortDirection);
                break;
            default:
                $query->orderBy($this->sortField, $this->sortDirection);
        }
    }
}
