<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use Livewire\Component;

class OrderDetail extends Component
{
    public Order $order;

    // Status Bearbeitung
    public $status;
    public $payment_status;
    public $notes;

    // Tracking (Neu)
    public $tracking_number;
    public $tracking_url;

    public function mount($id)
    {
        $this->order = Order::with('items.product')->findOrFail($id);
        $this->status = $this->order->status;
        $this->payment_status = $this->order->payment_status;
        $this->notes = $this->order->notes;

        // Annahme: Tracking Info speichern wir in den notes oder einem extra Feld/JSON
        // Hier vereinfacht: Wir zeigen es nur an oder speichern es in notes
    }

    public function saveStatus()
    {
        $this->order->update([
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'notes' => $this->notes
        ]);

        session()->flash('success', 'Bestelldetails aktualisiert.');
    }

    public function render()
    {
        return view('livewire.shop.order-detail');
    }
}
