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
        $isAuthorized = false;

        // 1. Admin & Mitarbeiter dürfen alle Rechnungen einsehen
        if (\App\Services\AI\AiAuthHelper::isAdmin() || auth()->guard('employee')->check() || auth()->guard('admin')->check()) {
            $isAuthorized = true;
        } 
        // 2. Eingeloggter Kunde darf seine eigenen Rechnungen einsehen
        elseif (\App\Services\AI\AiAuthHelper::isCustomer() && \App\Services\AI\AiAuthHelper::getCustomerId() === $invoice->customer_id) {
            $isAuthorized = true;
        } 
        // 3. Gültige kryptografische URL-Signatur (z. B. für Gastkunden via Mail)
        elseif (request()->hasValidSignature()) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            abort(403, 'Zugriff verweigert.');
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
