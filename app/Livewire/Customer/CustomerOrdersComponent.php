<?php

namespace App\Livewire\Customer;


use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem; // Anpassen, falls dein Model anders heißt
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class CustomerOrdersComponent extends Component
{
    use WithPagination;

    public $searchOrder = '';
    #[Locked]
    public $selectedOrderId = null;
    #[Locked]
    public $previewItemId = null;
    #[Locked]
    public $editItemId = null;
    public $showSuccessMessageFor = null;

    public function mount()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (request()->has('order')) {
            $orderId = request()->get('order');
            $order = OrderOrder::where('id', $orderId)->where('customer_id', $user->id)->first();
            if ($order) {
                $this->selectedOrderId = $order->id;
            }
        }
        if (request()->has('edit')) {
            $editId = request()->get('edit');
            $belongs = OrderOrderItem::where('id', $editId)->whereHas('order', function ($q) use ($user) {
                $q->where('customer_id', $user->id);
            })->exists();
            if ($belongs) {
                $this->editItemId = $editId;
            }
        }
    }

    // Wenn gesucht wird, springen wir zurück auf Seite 1
    public function updatingSearchOrder()
    {
        $this->resetPage();
    }

    public function showOrder($id)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $exists = OrderOrder::where('id', $id)->where('customer_id', $user->id)->exists();
        if (!$exists) {
            $this->selectedOrderId = null;
            $this->previewItemId = null;
            return;
        }

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
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $belongs = OrderOrderItem::where('id', $itemId)->whereHas('order', function ($q) use ($user) {
            $q->where('customer_id', $user->id);
        })->exists();

        if (!$belongs) {
            $this->previewItemId = null;
            return;
        }

        if ($this->previewItemId === $itemId) {
            $this->previewItemId = null; // Toggle zum Schließen
        } else {
            $this->previewItemId = $itemId;
        }
    }

    public function openEdit($itemId)
    {
        $this->previewItemId = null; // Close preview if open
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $belongs = OrderOrderItem::where('id', $itemId)->whereHas('order', function ($q) use ($user) {
            $q->where('customer_id', $user->id);
        })->exists();

        if (!$belongs) {
            $this->editItemId = null;
            return;
        }

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

    public function downloadDigitalFile($itemId)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            abort(403);
        }

        $item = OrderOrderItem::where('id', $itemId)
            ->whereHas('order', function ($q) use ($user) {
                $q->where('customer_id', $user->id);
            })->first();

        if (!$item || !$item->product || $item->product->type !== 'digital' || empty($item->product->digital_download_path)) {
            session()->flash('error', 'Datei nicht verfügbar.');
            return;
        }

        $downloadPath = ltrim(str_replace('\\', '/', (string) $item->product->digital_download_path), '/');
        if (
            $downloadPath === ''
            || str_contains($downloadPath, '..')
            || str_contains($downloadPath, "\0")
            || (!str_starts_with($downloadPath, 'produkte/products-secure/') && !str_starts_with($downloadPath, 'products-secure/'))
        ) {
            session()->flash('error', 'Ungültiger Dateipfad.');
            return;
        }

        // Downloads stehen erst nach vollständigem Zahlungseingang zur Verfügung
        if ($item->order->payment_status !== 'paid') {
            session()->flash('error', 'Der Download steht erst nach vollständigem Zahlungseingang zur Verfügung.');
            return;
        }

        if (
            !\Illuminate\Support\Facades\Storage::disk('local')->exists($downloadPath)
            || is_link(\Illuminate\Support\Facades\Storage::disk('local')->path($downloadPath))
        ) {
            session()->flash('error', 'Datei nicht gefunden.');
            return;
        }

        $safeDownloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) ($item->product->digital_filename ?: 'Download'))) ?: 'Download';

        return \Illuminate\Support\Facades\Storage::disk('local')->download(
            $downloadPath, 
            $safeDownloadName
        );
    }

    public function confirmDelivery($orderId)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $order = OrderOrder::where('id', $orderId)
            ->where('customer_id', $user->id)
            ->first();

        if (!$order) {
            session()->flash('error', 'Bestellung nicht gefunden.');
            return;
        }

        if ($order->status !== 'shipped') {
            session()->flash('error', 'Die Bestellung kann nur im Status "Unterwegs" als zugestellt bestätigt werden.');
            return;
        }

        // Alle Sendungen auf delivered setzen
        $order->shipments()->update(['status' => 'delivered']);

        // Bestellung auf completed setzen
        $order->update(['status' => 'completed']);

        session()->flash('delivery_confirmed', 'Vielen Dank! Ihre Bestellung wurde als zugestellt bestätigt. Wir wünschen Ihnen ganz viel Freude mit Ihren Seelenfunke-Unikaten! ✨');
    }

    // Computed Property für die ausgewählte Bestellung (strikt mandantengeschützt)
    public function getSelectedOrderProperty()
    {
        if (!$this->selectedOrderId) return null;
        $user = Auth::guard('customer')->user();
        if (!$user) return null;

        return OrderOrder::with([
                'items.product',
                'invoices' => fn($q) => $q->where('status', '!=', 'draft'),
                'shipments'
            ])
            ->where('customer_id', $user->id)
            ->find($this->selectedOrderId);
    }

    // Computed Property für das Vorschaubild (Manufaktur Details, strikt mandantengeschützt)
    public function getPreviewItemProperty()
    {
        if (!$this->previewItemId) return null;
        $user = Auth::guard('customer')->user();
        if (!$user) return null;

        return OrderOrderItem::whereHas('order', function ($q) use ($user) {
            $q->where('customer_id', $user->id);
        })->with('product')->find($this->previewItemId);
    }

    // Computed Property für die Edit-Funktion (strikt mandantengeschützt)
    public function getEditItemProperty()
    {
        if (!$this->editItemId) return null;
        $user = Auth::guard('customer')->user();
        if (!$user) return null;

        return OrderOrderItem::whereHas('order', function ($q) use ($user) {
            $q->where('customer_id', $user->id);
        })->with('product')->find($this->editItemId);
    }

    public function render()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            abort(403);
        }

        $query = OrderOrder::where('customer_id', $user->id)
            ->with(['items.product', 'shipments']); // Eager Loading für Performance

        if (!empty($this->searchOrder)) {
            $escapedSearch = addcslashes((string) $this->searchOrder, '%_\\');
            $query->where('order_number', 'like', '%' . $escapedSearch . '%');
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('livewire.customer.customer-orders-component', [
            'orders' => $orders,
            'selectedOrder' => $this->selectedOrder,
        ])->layout('components.layouts.customer_layout');
    }
}
