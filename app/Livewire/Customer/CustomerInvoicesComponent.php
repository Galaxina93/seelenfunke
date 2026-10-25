<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Order\OrderOrder;

class CustomerInvoicesComponent extends Component
{
    public function mount()
    {
        if (!Auth::guard('customer')->check()) {
            abort(403);
        }
    }

    public function render()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            abort(403);
        }

        // Alle Bestellungen laden als Referenz für die UI (Bestellnummer-Anzeige)
        $orders = OrderOrder::where('customer_id', $user->id)
            ->get();
        $orderIds = $orders->pluck('id')->all();

        // Invoices direkt über den Kunden oder seine zugehörigen Bestellungen laden (niemals jedoch unfertige Entwürfe)
        $invoices = \App\Models\Accounting\AccountingInvoice::where('status', '!=', 'draft')
            ->where(function ($q) use ($user, $orderIds) {
                $q->where('customer_id', $user->id);
                if (!empty($orderIds)) {
                    $q->orWhereIn('order_id', $orderIds);
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Payload für das Frontend JSZip Script generieren
        $invoicesPayload = $invoices->map(function($inv) {
            $prefix = $inv->type === 'cancellation' ? 'Storno_' : ($inv->isCreditNote() ? 'Gutschrift_' : 'Rechnung_');
            return [
                'id' => $inv->id,
                'name' => $prefix . ($inv->invoice_number ?? $inv->id) . '.pdf',
                'url' => route('invoice.download', $inv->id)
            ];
        })->values()->toJson();

        return view('livewire.customer.customer-invoices-component', [
            'invoices' => $invoices,
            'invoicesPayload' => $invoicesPayload,
            'orders' => $orders
        ])->layout('components.layouts.customer_layout');
    }
}
