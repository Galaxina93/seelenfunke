<?php

namespace App\Livewire\Shop;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceService;
use Livewire\Component;
use Livewire\WithPagination;

class Invoices extends Component
{
    use WithPagination;

    public $search = '';
    public $filterType = ''; // invoice, cancellation

    // --- ACTIONS ---

    /**
     * Generiert Rechnungen für alle bezahlten Bestellungen, die noch keine haben.
     * (Nützlicher Bulk-Action Button)
     */
    public function generateForPaidOrders(InvoiceService $service)
    {
        $orders = Order::where('payment_status', 'paid')
            ->whereDoesntHave('invoices') // Relation im Order Model muss existieren: public function invoices() { return $this->hasMany(Invoice::class); }
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            $service->createFromOrder($order);
            $count++;
        }

        session()->flash('success', "$count Rechnungen wurden generiert.");
    }

    public function render()
    {
        $query = Invoice::query()->with('order');

        if ($this->search) {
            $query->where('invoice_number', 'like', '%'.$this->search.'%')
                ->orWhere('billing_address->last_name', 'like', '%'.$this->search.'%');
        }

        if ($this->filterType) {
            $query->where('type', $this->filterType);
        }

        $invoices = $query->latest('invoice_date')->paginate(15);

        return view('livewire.shop.invoices', [
            'invoices' => $invoices
        ]);
    }
}
