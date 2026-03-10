<?php

namespace App\Jobs;

use App\Models\Order\Order;
use App\Services\InvoiceService;
use App\Services\NativeXmlInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderMailToCustomer;
use App\Mail\OrderMailToAdmin;

class ProcessOrderDocumentsAndMails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $order;

    /**
     * Create a new job instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle(InvoiceService $invoiceService, NativeXmlInvoiceService $xmlService): void
    {
        // 1. Rechnung & Dokumente zentral erstellen
        $pdfPath = null;
        $xmlPath = null;

        try {
            // Invoice Model in DB erstellen
            $invoice = $invoiceService->createFromOrder($this->order);

            // A) PDF Generieren
            $pdfPath = storage_path("app/public/invoices/{$invoice->invoice_number}.pdf");

            if ($invoice && !file_exists($pdfPath)) {
                $pdf = $invoiceService->generatePdf($invoice);
                if (!file_exists(dirname($pdfPath))) {
                    mkdir(dirname($pdfPath), 0755, true);
                }
                file_put_contents($pdfPath, $pdf->output());
            }

            // B) XML Generieren (Nur für gewerbliche Kunden)
            $isBusiness = false;
            // Wichtig: Nur wenn im Profil explizit is_business auf 1/true steht, dann XML erzeugen
            if ($this->order->customer && $this->order->customer->profile && $this->order->customer->profile->is_business) {
                $isBusiness = true;
            }

            if ($invoice && $isBusiness) {
                try {
                    $relativePath = $xmlService->generate($invoice);
                    $xmlPath = storage_path("app/{$relativePath}");
                } catch (\Exception $e) {
                    Log::error("XML-Generierung fehlgeschlagen für {$this->order->order_number}: " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            Log::error("Rechnungserstellung fehlgeschlagen für {$this->order->order_number}: " . $e->getMessage());
        }

        // 2. Mails versenden mit zentralisierten Daten
        try {
            // Relationen zur Sicherheit im Hintergrund-Prozess nachladen
            $this->order->loadMissing(['items.product', 'customer']);
            $mailData = $this->order->toFormattedArray();

            // Sammle alle Snapshots der konfigurierten Artikel
            $snapshotPaths = [];
            foreach ($this->order->items as $item) {
                if (!empty($item->configuration['snapshot_path'])) {
                    $paths = is_array($item->configuration['snapshot_path']) 
                                ? array_values($item->configuration['snapshot_path']) 
                                : [$item->configuration['snapshot_path']];
                                
                    foreach ($paths as $path) {
                        $fullPath = storage_path('app/public/' . $path);
                        if (file_exists($fullPath)) {
                            $snapshotPaths[] = $fullPath;
                        }
                    }
                }
            }

            // Da wir hier schon im Job sind, können wir send() nutzen.
            // Falls deine Mailables 'ShouldQueue' nutzen, werden sie sonst "doppelt" gequeued. Das ist aber kein Problem.
            Mail::to($this->order->email)
                ->send(new OrderMailToCustomer($mailData, $pdfPath, $xmlPath, $snapshotPaths));

            Mail::to('kontakt@mein-seelenfunke.de')
                ->send(new OrderMailToAdmin($mailData, $pdfPath, $xmlPath, $snapshotPaths));

        } catch (\Exception $e) {
            Log::error("Checkout Mail Fehler für {$this->order->order_number}: " . $e->getMessage());
        }
    }
}
