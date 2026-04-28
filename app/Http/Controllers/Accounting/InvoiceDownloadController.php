<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingInvoice;
use App\Services\InvoiceService;

class InvoiceDownloadController extends Controller
{
    /**
     * Download the specified invoice PDF.
     *
     * @param  \App\Models\Accounting\AccountingInvoice  $invoice
     * @param  \App\Services\InvoiceService  $service
     * @return \Illuminate\Http\Response
     */
    public function download(AccountingInvoice $invoice, InvoiceService $service)
    {
        // Security Gate: Darf der User das sehen?
        // Admin darf alles, Customer nur seine eigenen
        if (auth()->guard('admin')->check()) {
            // ok
        } elseif (auth()->guard('customer')->check() && auth()->guard('customer')->id() === $invoice->customer_id) {
            // ok
        } else {
            abort(403);
        }

        $pdf = $service->generatePdf($invoice);

        $filenamePrefix = 'Rechnung_';
        if ($invoice->type === 'cancellation') {
            $filenamePrefix = 'Storno_';
        } elseif ($invoice->type === 'credit_note') {
            $filenamePrefix = 'Gutschrift_';
        }

        return $pdf->download($filenamePrefix . $invoice->invoice_number . '.pdf');
    }
}
