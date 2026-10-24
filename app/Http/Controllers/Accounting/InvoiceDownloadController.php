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

        // 1. Admin darf alle Rechnungen einsehen; Mitarbeiter dürfen nur freigegebene Shop-Bestellrechnungen (order_id !== null, kein Entwurf) einsehen
        $isAdmin = \App\Services\AI\AiAuthHelper::isAdmin() || auth()->guard('admin')->check();
        $isEmployee = \App\Services\AI\AiAuthHelper::isEmployee() || auth()->guard('employee')->check();
        if ($isAdmin) {
            $isAuthorized = true;
        } elseif ($isEmployee && $invoice->order_id !== null && $invoice->status !== 'draft') {
            $isAuthorized = true;
        } 
        // 2. Eingeloggter Kunde darf seine eigenen freigegebenen Rechnungen (keine Entwürfe) einsehen
        elseif (\App\Services\AI\AiAuthHelper::isCustomer() && $invoice->customer_id !== null && \App\Services\AI\AiAuthHelper::getCustomerId() === $invoice->customer_id && $invoice->status !== 'draft') {
            $isAuthorized = true;
        } 
        // 3. Gültige kryptografische URL-Signatur (z. B. für Gastkunden via Mail, keine Entwürfe)
        elseif (request()->hasValidSignature() && $invoice->status !== 'draft') {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            abort(403, 'Zugriff verweigert.');
        }

        $filenamePrefix = 'Rechnung_';
        if ($invoice->type === 'cancellation') {
            $filenamePrefix = 'Storno_';
        } elseif ($invoice->type === 'credit_note') {
            $filenamePrefix = 'Gutschrift_';
        }
        $safeNumber = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $invoice->invoice_number);
        $filename = $filenamePrefix . $safeNumber . '.pdf';

        // 1. Wenn das GoBD-konforme PDF bereits im Archiv liegt, direkt ausliefern (schneller & unveränderlich)
        if ($invoice->has_archived_pdf) {
            $storagePath = ltrim(str_replace('\\', '/', (string) $invoice->pdf_storage_path), '/');
            if (
                $storagePath !== ''
                && !str_contains($storagePath, '..')
                && !str_contains($storagePath, "\0")
                && (str_starts_with($storagePath, 'buchhaltung/invoices/') || str_starts_with($storagePath, 'invoices/'))
                && !is_link(\Illuminate\Support\Facades\Storage::disk('local')->path($storagePath))
            ) {
                return \Illuminate\Support\Facades\Storage::disk('local')->download($storagePath, $filename);
            }
        }

        // 2. Fallback: PDF frisch rendern
        $pdf = $service->generatePdf($invoice);

        return $pdf->download($filename);
    }
}
