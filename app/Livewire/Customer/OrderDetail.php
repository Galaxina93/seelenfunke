<?php

namespace App\Livewire\Customer;

use App\Models\Order\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class OrderDetail extends Component
{
    public Order $order;

    public function mount($id)
    {
        // Sicherheitscheck: Gehört die Order dem User?
        $this->order = Order::with(['items', 'invoices'])
            ->where('customer_id', Auth::guard('customer')->id())
            ->findOrFail($id);
    }

    /**
     * Download Wrapper.
     * In einer echten App würde man hier einen Controller-Redirect machen,
     * um den Stream zu starten. Hier simulieren wir den Download-Link Generierung.
     */
    public function downloadInvoice($invoiceId)
    {
        return redirect()->route('invoice.download', $invoiceId);
    }

    #[Layout('components.layouts.backend_layout', ['guard' => 'customer'])]
    public function render()
    {
        return view('livewire.customer.order-detail');
    }
}
