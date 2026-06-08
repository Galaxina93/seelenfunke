<?php

namespace App\Jobs;

use App\Models\Order\OrderOrder;
use App\Services\InvoiceService;
use App\Services\AccountingXmlInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewOrderMailToCustomer;
use App\Mail\NewOrderMailToAdmin;

class ProcessOrderDocumentsAndMails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $order;

    /**
     * Create a new job instance.
     */
    public function __construct(OrderOrder $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle(InvoiceService $invoiceService, AccountingXmlInvoiceService $xmlService): void
    {
        // 1. Rechnung & Dokumente zentral erstellen
        $pdfPath = null;
        $xmlPath = null;

        try {
            // Invoice Model in DB erstellen
            $invoice = $invoiceService->createFromOrder($this->order);

            // A) PDF Generieren
            $pdfPath = storage_path("app/public/buchhaltung/invoices/{$invoice->invoice_number}.pdf");

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
            $itemIndex = 1;
            foreach ($this->order->items as $item) {
                if (!empty($item->configuration['snapshot_path'])) {
                    $configSnapshots = $item->configuration['snapshot_path'];
                    $cleanProductName = \Illuminate\Support\Str::slug($item->product_name);
                    
                    if (is_array($configSnapshots)) {
                        foreach ($configSnapshots as $key => $path) {
                            $fullPath = storage_path('app/public/' . $path);
                            if (file_exists($fullPath)) {
                                $sideName = ($key === 'back') ? 'Rückseite' : 'Vorderseite';
                                $filename = $cleanProductName . '-' . $itemIndex . '-' . $sideName . '-Sicherung.jpg';
                                $snapshotPaths[$filename] = $fullPath;
                            }
                        }
                    } else {
                        $fullPath = storage_path('app/public/' . $configSnapshots);
                        if (file_exists($fullPath)) {
                            $filename = $cleanProductName . '-' . $itemIndex . '-Vorderseite-Sicherung.jpg';
                            $snapshotPaths[$filename] = $fullPath;
                        }
                    }
                }
                $itemIndex++;
            }

            // Da wir hier schon im Job sind, können wir send() nutzen.
            // Falls deine Mailables 'ShouldQueue' nutzen, werden sie sonst "doppelt" gequeued. Das ist aber kein Problem.
            Mail::to($this->order->email)
                ->send(new NewOrderMailToCustomer($mailData, $pdfPath, $xmlPath, $snapshotPaths));

            Mail::to('kontakt@mein-seelenfunke.de')
                ->send(new NewOrderMailToAdmin($mailData, $pdfPath, $xmlPath, $snapshotPaths));

            // Push-Benachrichtigung an Admins senden
            try {
                $firebase = resolve(\App\Services\FirebaseService::class);
                $firebase->sendToAdmins(
                    "Neue Bestellung eingegangen! 🎉",
                    "Bestellung #" . $this->order->order_number . " von " . $this->order->customer_name . " (" . number_format($this->order->total_price / 100, 2, ',', '.') . " €)",
                    [
                        'open_tab' => '5', // Der Index des neuen "Bestellungen" Tabs
                        'order_id' => $this->order->id
                    ]
                );
            } catch (\Exception $fbEx) {
                \Illuminate\Support\Facades\Log::error("Firebase Push Fehler für {$this->order->order_number}: " . $fbEx->getMessage());
            }

        } catch (\Exception $e) {
            Log::error("Checkout Mail Fehler für {$this->order->order_number}: " . $e->getMessage());
        }
    }
}

