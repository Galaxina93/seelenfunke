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
        $invoice = $this->invoiceId ? Invoice::with('order.items')->find($this->invoiceId) : null;
        return view('livewire.shop.invoice-preview', compact('invoice'));
    }
}
