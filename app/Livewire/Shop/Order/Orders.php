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

    // Standard: Custom Workflow (Offene oben, Express oben, Erledigte unten)
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

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'paymentFilter' => ['except' => ''],
        'sortField' => ['except' => 'default_workflow'],
        'sortDirection' => ['except' => 'asc']
    ];

    // Reset aller Filter auf Standard
    public function resetFilters()
    {
        $this->reset(['search', 'statusFilter', 'paymentFilter', 'sortField', 'sortDirection']);
        $this->resetPage();
    }

    // --- COMPUTED: PRIORITÄTS-ORDER (Für Funki Header) ---
    public function getPriorityOrderProperty()
    {
        // Holt genau EINE Order, die am wichtigsten ist
        return Order::query()
            ->whereIn('status', ['pending', 'processing']) // Nur offene
            ->orderByRaw("CASE WHEN status IN ('completed', 'cancelled', 'refunded') THEN 1 ELSE 0 END ASC")
            ->orderBy('is_express', 'desc') // Express zuerst
            ->orderByRaw("CASE WHEN deadline IS NULL THEN 1 ELSE 0 END ASC") // Terminierte zuerst
            ->orderBy('deadline', 'asc') // Nächster Termin zuerst
            ->orderBy('created_at', 'asc') // Älteste zuerst (FIFO)
            ->first();
    }

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

        // Order laden (Eager Loading für Performance)
        $this->selectedOrder = Order::with(['items.product', 'invoices'])->find($id);

        // Daten für das Bearbeitungs-Formular laden
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
            // Bestand zurückbuchen, wenn die Order vorher nur "eingegangen" war
            if ($this->selectedOrder->status !== 'cancelled' && $this->selectedOrder->status === 'pending') {
                foreach ($this->selectedOrder->items as $item) {
                    if ($item->product) $item->product->restoreStock($item->quantity);
                }
                session()->flash('info', 'Bestand wurde zurückgebucht.');
            }

            $this->validate([
                'cancellationReason' => 'required|string|min:5|max:500',
            ]);

            // Update
            $this->selectedOrder->update([
                'status' => 'cancelled',
                'payment_status' => $this->payment_status,
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

    // --- ACTIONS: SCHNELL-UPDATE (Direkt in der Tabelle) ---

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
                'orders' => collect(),
                'stats' => []
            ]);
        }

        // B) Listen-Modus (Single Table)
        $query = Order::query();

        // 1. Suche (Über alle relevanten Felder)
        if ($this->search) {
            $searchTerm = '%' . $this->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('order_number', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhere('billing_address->last_name', 'like', $searchTerm)
                    ->orWhere('billing_address->first_name', 'like', $searchTerm)
                    ->orWhere('billing_address->company', 'like', $searchTerm);
            });
        }

        // 2. Filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->paymentFilter) {
            $query->where('payment_status', $this->paymentFilter);
        }

        // 3. Sortierung (Core Logic)
        if ($this->sortField === 'default_workflow') {
            $this->applyWorkflowSort($query);
        } else {
            $this->applyCustomSort($query);
        }

        // 4. Statistiken (Immer aktuell, basierend auf Gesamt-DB)
        $stats = [
            'total' => Order::count(),
            'open' => Order::whereIn('status', ['pending', 'processing'])->count(),
            'open_express' => Order::whereIn('status', ['pending', 'processing'])->where('is_express', true)->count(),
            'revenue_today' => Order::whereDate('created_at', today())->sum('total_price'),
            'revenue_month' => Order::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('total_price'),
            'avg_cart' => Order::where('status', 'completed')->avg('total_price') ?? 0,
        ];

        // 5. Query ausführen (Pagination)
        $orders = $query->paginate(20);

        return view('livewire.shop.order.orders', [
            'orders' => $orders,
            'stats' => $stats
        ]);
    }

    /**
     * WORKFLOW SORTIERUNG:
     * 1. Status: Offene zuerst (0), Erledigte unten (1)
     * 2. Typ: Express zuerst (DESC)
     * 3. Termin: Nächste Deadline zuerst (ASC), NULL Deadlines dahinter
     * 4. Alter: Älteste Aufträge zuerst (FIFO, ASC)
     */
    private function applyWorkflowSort($query)
    {
        // 1. Gruppierung: Wichtiges oben (Pending/Processing/Shipped), Rest unten
        $query->orderByRaw("CASE WHEN status IN ('completed', 'cancelled', 'refunded') THEN 1 ELSE 0 END ASC");

        // 2. Express vor Standard
        $query->orderBy('is_express', 'desc');

        // 3. Deadline: Das Nächstgelegene (kleinstes Datum) zuerst.
        // Trick: MySQL sortiert NULL bei ASC ganz nach oben. Das wollen wir nicht.
        // Orders OHNE Deadline sollen hinter Orders MIT Deadline (innerhalb der gleichen Prio-Gruppe).
        $query->orderByRaw("CASE WHEN deadline IS NULL THEN 1 ELSE 0 END ASC");
        $query->orderBy('deadline', 'asc');

        // 4. Datum: Älteste zuerst (FIFO)
        $query->orderBy('created_at', 'asc');
    }

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
