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
            // Invoice Model in DB erstellen (idempotent bei Queue-Retries)
            $invoice = $this->order->invoices()->where('type', 'invoice')->first();
            if (!$invoice) {
                $invoice = $invoiceService->createFromOrder($this->order);
            }

            // A) PDF Generieren & über InvoiceService GoBD-konform archivieren
            if ($invoice) {
                $storedPdfRelativePath = $invoiceService->storePdf($invoice);
                $pdfPath = \Illuminate\Support\Facades\Storage::disk('local')->path($storedPdfRelativePath);
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
                    $xmlPath = \Illuminate\Support\Facades\Storage::disk('local')->path($relativePath);
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
            $publicRoot = realpath(storage_path('app/public'));
            $resolveSafeSnapshot = function ($rawPath) use ($publicRoot): ?string {
                if (!is_string($rawPath) || $rawPath === '' || str_contains($rawPath, '..') || str_contains($rawPath, "\0")) {
                    return null;
                }
                $clean = ltrim(str_replace('\\', '/', $rawPath), '/');
                $normPath = str_starts_with($clean, 'snapshots/') ? 'system/' . $clean : $clean;
                $ext = strtolower(pathinfo($normPath, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    return null;
                }
                $fullPath = storage_path('app/public/' . $normPath);
                if (!file_exists($fullPath) || !is_file($fullPath)) {
                    return null;
                }
                $real = realpath($fullPath);
                if (!$real || !$publicRoot || !str_starts_with($real, $publicRoot . DIRECTORY_SEPARATOR)) {
                    return null;
                }
                return $real;
            };

            foreach ($this->order->items as $item) {
                if (!empty($item->configuration['snapshot_path'])) {
                    $configSnapshots = $item->configuration['snapshot_path'];
                    $cleanProductName = \Illuminate\Support\Str::slug($item->product_name);
                    
                    if (is_array($configSnapshots)) {
                        foreach ($configSnapshots as $key => $path) {
                            $fullPath = $resolveSafeSnapshot($path);
                            if ($fullPath) {
                                $sideName = ($key === 'back') ? 'Rückseite' : 'Vorderseite';
                                $filename = $cleanProductName . '-' . $itemIndex . '-' . $sideName . '-Sicherung.jpg';
                                $snapshotPaths[$filename] = $fullPath;
                            }
                        }
                    } else {
                        $fullPath = $resolveSafeSnapshot($configSnapshots);
                        if ($fullPath) {
                            $filename = $cleanProductName . '-' . $itemIndex . '-Vorderseite-Sicherung.jpg';
                            $snapshotPaths[$filename] = $fullPath;
                        }
                    }
                }
                $itemIndex++;
            }

            // 2a. Kundenbestätigung direkt synchron via SMTP senden
            try {
                Mail::to($this->order->email)
                    ->sendNow(new NewOrderMailToCustomer($mailData, $pdfPath, $xmlPath, $snapshotPaths));

                // Klartext-Initialpasswort sofort nach erfolgreichem Versand aus der Datenbank entfernen
                if ($this->order->customer && !empty($this->order->customer->temporary_password)) {
                    $this->order->customer->update(['temporary_password' => null]);
                }
            } catch (\Throwable $customerEx) {
                Log::error("Kunden-Bestellbestätigung konnte nicht gesendet werden für {$this->order->order_number}: " . $customerEx->getMessage());
            }

            // 2a-2. Separate Geschenkgutschein-E-Mails im Hintergrund-Job versenden (entlastet den Checkout-Request bei hohem Traffic von DomPDF & SMTP)
            try {
                $emailVouchers = \App\Models\Marketing\MarketingGiftVoucher::whereIn('order_item_id', $this->order->items->pluck('id'))
                    ->where('is_active', true)
                    ->where('delivery_method', 'email')
                    ->get();

                foreach ($emailVouchers as $voucher) {
                    $recipientTarget = $voucher->recipient_email ?: $this->order->email;
                    if (!empty($recipientTarget)) {
                        Mail::to($recipientTarget)
                            ->sendNow(new \App\Mail\NewGiftVoucherToCustomer($voucher));
                    }
                }
            } catch (\Throwable $voucherMailEx) {
                Log::error("Geschenkgutschein-E-Mail konnte nicht gesendet werden für {$this->order->order_number}: " . $voucherMailEx->getMessage());
            }

            // Initialpasswort niemals an Admin-Sammelpostfach weiterleiten
            $mailData['customer_temporary_password'] = null;
            $mailData['customer_needs_password_change'] = false;

            // 2b. Admin-Benachrichtigung direkt synchron via SMTP senden
            if (shop_setting('send_order_email_to_admin', true)) {
                try {
                    $adminEmail = shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
                    Mail::to($adminEmail)
                        ->sendNow(new NewOrderMailToAdmin($mailData, $pdfPath, $xmlPath, $snapshotPaths));
                } catch (\Throwable $adminEx) {
                    Log::error("Admin-Bestellbestätigung konnte nicht gesendet werden für {$this->order->order_number}: " . $adminEx->getMessage());
                }
            }

            // Push-Benachrichtigung an Admins senden
            try {
                $firebase = resolve(\App\Services\FirebaseService::class);
                $firebase->sendToAdmins(
                    "Neue Bestellung eingegangen! 🎉",
                    "Bestellung #" . $this->order->order_number . " von " . $this->order->customer_name . " (" . number_format($this->order->total_price / 100, 2, ',', '.') . " €)",
                    [
                        'open_tab' => '1', // Der Index des neuen "Bestellungen" Tabs (nach Zentrum)
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

