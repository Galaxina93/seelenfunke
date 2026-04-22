<?php

namespace App\Livewire\Shop\Accounting;

use App\Models\Accounting\AccountingInvoice;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class AccountingInvoicePreview extends Component
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
        $invoice = AccountingInvoice::find($this->invoiceId);
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

    /**
     * NEU: Ermöglicht den Download direkt aus der Vorschau
     * Berücksichtigt die GoBD-konforme Archivierung
     */
    public function downloadPdf(InvoiceService $service)
    {
        $invoice = AccountingInvoice::findOrFail($this->invoiceId);

        // Prüfung auf physisches Archiv ( storage/app/invoices )
        $fileName = "buchhaltung/invoices/{$invoice->invoice_number}.pdf";
        if (Storage::disk('local')->exists($fileName)) {
            return Storage::disk('local')->download($fileName);
        }

        // Fallback: On-the-fly generieren
        return response()->streamDownload(function () use ($service, $invoice) {
            echo $service->generatePdf($invoice)->output();
        }, "Rechnung_{$invoice->invoice_number}.pdf");
    }

    public function render()
    {
        $invoice = $this->invoiceId ? AccountingInvoice::find($this->invoiceId) : null;

        $totalsPreview = [
            'net' => 0,
            'tax' => 0,
            'gross' => 0
        ];

        if($invoice) {
            // Fix: Check if item is object (getItemsAttribute map) or array (from database)
            $items = $invoice->items;
            $defaultTax = (float)shop_setting('default_tax_rate', 19.0);
            foreach($items as $item) {
                $totalPrice = is_object($item) ? $item->total_price : ($item['total_price'] ?? 0);
                $taxRate = is_object($item) ? ($item->tax_rate ?? $defaultTax) : ($item['tax_rate'] ?? $defaultTax);

                $line = (float)$totalPrice;
                $taxDiv = 1 + ($taxRate / 100);
                $net = $line / $taxDiv;

                $totalsPreview['net'] += $net;
                $totalsPreview['tax'] += ($line - $net);
                $totalsPreview['gross'] += $line;
            }
        }

        return view('livewire.shop.accounting.accounting-invoice-preview', compact('invoice', 'totalsPreview'));
    }
}
