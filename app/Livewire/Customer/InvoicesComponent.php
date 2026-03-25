<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Order\Order;

class InvoicesComponent extends Component
{
    public function render()
    {
        $user = Auth::guard('customer')->user();

        // Alle Bestellungen laden als Referenz für die UI (Bestellnummer-Anzeige)
        $orders = Order::where('customer_id', $user->id)
            ->get();

        // Invoices direkt über den Kunden laden (inkludiert auch Gutschriften ohne direkte Order-Zuweisung)
        $invoices = \App\Models\Accounting\Invoice::where('customer_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Payload für das Frontend JSZip Script generieren
        $invoicesPayload = $invoices->map(function($inv) {
            return [
                'id' => $inv->id,
                'name' => ($inv->isCreditNote() ? 'Gutschrift_' : 'Rechnung_') . ($inv->invoice_number ?? $inv->id) . '.pdf',
                'url' => route('invoice.download', $inv->id)
            ];
        })->values()->toJson();

        return view('livewire.customer.invoices-component', [
            'invoices' => $invoices,
            'invoicesPayload' => $invoicesPayload,
            'orders' => $orders
        ])->layout('components.layouts.customer_layout');
    }
}
