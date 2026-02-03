<?php

namespace App\Livewire\Shop;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Livewire\Component;

class InvoicePreview extends Component
{
    public $invoiceId;
    public $showModal = false;

    protected $listeners = ['openInvoicePreview' => 'loadInvoice'];

    public function loadInvoice($id)
    {
        $this->invoiceId = $id;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->invoiceId = null;
    }

    public function cancelInvoice(InvoiceService $service)
    {
        $invoice = Invoice::find($this->invoiceId);
        if(!$invoice || $invoice->status === 'cancelled') return;

        try {
            $service->cancelInvoice($invoice);
            session()->flash('success', 'Stornierung erfolgt.');
            $this->closeModal();
            $this->dispatch('refreshComponent');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $invoice = $this->invoiceId ? Invoice::find($this->invoiceId) : null;

        $totalsPreview = [
            'net' => 0,
            'tax' => 0,
            'gross' => 0
        ];

        if($invoice) {
            // Fix: Check if item is object (getItemsAttribute map) or array (from database)
            $items = $invoice->items;
            foreach($items as $item) {
                $totalPrice = is_object($item) ? $item->total_price : ($item['total_price'] ?? 0);
                $taxRate = is_object($item) ? ($item->tax_rate ?? 19) : ($item['tax_rate'] ?? 19);

                $line = (float)$totalPrice;
                $taxDiv = 1 + ($taxRate / 100);
                $net = $line / $taxDiv;

                $totalsPreview['net'] += $net;
                $totalsPreview['tax'] += ($line - $net);
                $totalsPreview['gross'] += $line;
            }
        }

        return view('livewire.shop.invoice-preview', compact('invoice', 'totalsPreview'));
    }
}
