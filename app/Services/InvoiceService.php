<?php

namespace App\Services;

use App\Models\Accounting\AccountingInvoice;
use App\Models\Order\OrderOrder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    /**
     * Erstellt eine rechtssichere Rechnung aus einer Bestellung.
     */
    public function createFromOrder(OrderOrder $order): ?AccountingInvoice
    {
        return retry(5, function () use ($order) {
            $existing = AccountingInvoice::where('order_id', $order->id)
                ->where('type', 'invoice')
                ->where('status', '!=', 'cancelled')
                ->first();

            if ($existing) return $existing;

            return DB::transaction(function () use ($order) {
                $invoiceNumber = $this->generateInvoiceNumber();

                // [FIX] Status dynamisch anhand der Order ermitteln
                $isPaid = ($order->payment_status === 'paid' && $order->payment_method !== 'invoice');

                $invoiceStatus = $isPaid ? 'paid' : 'open';
                $paidAt = $isPaid ? now() : null;

                // Fälligkeit berechnen (z.B. 7 Tage bei Rechnungskauf)
                // Nur wenn noch nicht bezahlt.
                $dueDays = 7;
                $dueDate = now()->addDays($dueDays);

                $invoice = AccountingInvoice::create([
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'invoice_number' => $invoiceNumber,
                    'type' => 'invoice',

                    // [FIX] Hier nutzen wir jetzt die dynamischen Werte
                    'status' => $invoiceStatus,
                    'invoice_date' => now(),
                    'delivery_date' => $order->created_at, // Leistungsdatum = Bestelldatum
                    'due_date' => $dueDate,
                    'due_days' => $dueDays,
                    'paid_at' => $paidAt, // Ist null, wenn nicht bezahlt -> PDF zeigt Bankdaten an

                    'subject' => 'Rechnung zu Bestellung #' . $order->order_number,
                    'billing_address' => $order->billing_address,
                    'shipping_address' => $order->shipping_address,
                    'subtotal' => $order->subtotal_price,
                    'tax_amount' => $order->tax_amount,
                    'shipping_cost' => $order->shipping_price,
                    'express_price' => $order->express_price ?? 0,
                    'discount_amount' => $order->discount_amount,
                    'volume_discount' => $order->volume_discount,
                    'total' => $order->total_price,
                    'notes' => $order->notes,
                    'is_e_invoice' => false,
                    'is_small_business' => $order->is_small_business ?? app(\App\Services\Tax\TaxSettingService::class)->isCurrentSmallBusiness(),
                ]);

                // PDF sofort nach Erstellung physisch speichern
                $this->storePdf($invoice);

                return $invoice;
            });
        }, 100);
    }

    /**
     * Erstellt eine Stornorechnung (Gutschrift).
     */
    public function cancelInvoice(AccountingInvoice $originalInvoice): AccountingInvoice
    {
        if (in_array($originalInvoice->type, ['cancellation', 'credit_note'], true)) {
            throw new \InvalidArgumentException('Eine Stornorechnung / Gutschrift kann nicht erneut storniert werden.');
        }
        if ($originalInvoice->status === 'cancelled' || $originalInvoice->status === 'draft') {
            throw new \InvalidArgumentException('Diese Rechnung kann nicht storniert werden.');
        }

        return retry(5, function () use ($originalInvoice) {
            return DB::transaction(function () use ($originalInvoice) {
                $alreadyCancelled = AccountingInvoice::where('parent_id', $originalInvoice->id)
                    ->where('type', 'cancellation')
                    ->lockForUpdate()
                    ->exists();
                if ($alreadyCancelled) {
                    throw new \InvalidArgumentException('Zu dieser Rechnung existiert bereits eine Stornorechnung.');
                }

                $isPaid = $originalInvoice->status === 'paid' || $originalInvoice->paid_at !== null;
                $originalInvoice->update([
                    'status' => 'cancelled',
                    'paid_at' => $isPaid ? ($originalInvoice->paid_at ?? now()) : null,
                ]);
                $stornoNumber = $this->generateInvoiceNumber(prefix: 'STO');

                $invoice = AccountingInvoice::create([
                    'parent_id' => $originalInvoice->id,
                    'order_id' => $originalInvoice->order_id,
                    'customer_id' => $originalInvoice->customer_id,
                    'invoice_number' => $stornoNumber,
                    'type' => 'cancellation',
                    'status' => $isPaid ? 'paid' : 'cancelled',
                    'invoice_date' => now(),
                    'delivery_date' => now(),
                    'due_date' => now(),
                    'paid_at' => $isPaid ? now() : null,
                    'subject' => 'Gutschrift zur Rechnung ' . $originalInvoice->invoice_number,
                    'header_text' => 'Hiermit erhalten Sie eine Gutschrift.',
                    'footer_text' => 'Der Betrag wird Ihnen erstattet.',
                    'billing_address' => $originalInvoice->billing_address,
                    'shipping_address' => $originalInvoice->shipping_address,
                    'subtotal' => -1 * abs($originalInvoice->subtotal),
                    'tax_amount' => -1 * abs($originalInvoice->tax_amount),
                    'shipping_cost' => -1 * abs($originalInvoice->shipping_cost),
                    'express_price' => -1 * abs($originalInvoice->express_price ?? 0),
                    'discount_amount' => -1 * abs($originalInvoice->discount_amount),
                    'volume_discount' => -1 * abs($originalInvoice->volume_discount),
                    'total' => -1 * abs($originalInvoice->total),
                    'notes' => "Storno zur Rechnung Nr. " . $originalInvoice->invoice_number,
                    'custom_items' => $originalInvoice->custom_items ?? [],
                    'is_e_invoice' => (bool)($originalInvoice->is_e_invoice ?? false),
                    'is_small_business' => $originalInvoice->is_small_business ?? app(\App\Services\Tax\TaxSettingService::class)->isSmallBusinessForYear($originalInvoice->invoice_date?->year ?? now()->year),
                ]);

                // Stornobeleg ebenfalls archivieren
                $this->storePdf($invoice);

                // Synchronize order cancellation if order exists and is not yet cancelled
                if (
                    $originalInvoice->order
                    && !in_array($originalInvoice->order->status, ['cancelled', 'refunded'], true)
                    && $originalInvoice->order->payment_status !== 'refunded'
                ) {
                    $prevStatus = $originalInvoice->order->status;
                    $prevPaymentStatus = $originalInvoice->order->payment_status;
                    $originalInvoice->order->handleCancellation('Rechnungsstornierung ' . $originalInvoice->invoice_number, $prevStatus, $prevPaymentStatus);
                    $originalInvoice->order->update(['status' => 'cancelled']);
                }

                return $invoice;
            });
        }, 100);
    }

    /**
     * Erstellt eine manuelle Gutschrift (Rechnungskorrektur) unabhängig von einer existierenden Rechnung.
     * Stellt sicher, dass die übergebenen positiven Beträge intern als Minus für die Statistik verbucht werden.
     */
    public function createCreditNote(array $data): AccountingInvoice
    {
        return retry(5, function () use ($data) {
            return DB::transaction(function () use ($data) {
                $stornoNumber = $this->generateInvoiceNumber(prefix: 'GUT');

                // Formatiere Beträge ins Negative für korrekte Auswertung
                $subtotal = -1 * abs($data['subtotal'] ?? 0);
                $tax_amount = -1 * abs($data['tax_amount'] ?? 0);
                $express_price = -1 * abs($data['express_price'] ?? 0);
                $total = -1 * abs($data['total'] ?? 0);

                $invoice = AccountingInvoice::create([
                    'customer_id' => $data['customer_id'] ?? null,
                    'invoice_number' => $stornoNumber,
                    'type' => 'credit_note', // Gutschrift
                    'status' => 'paid', // Ist direkt wirksam
                    'invoice_date' => now(),
                    'delivery_date' => now(),
                    'due_date' => now(),
                    'paid_at' => now(),
                    'subject' => $data['subject'] ?? 'Gutschrift / Rechnungskorrektur',
                    'header_text' => $data['header_text'] ?? 'Hiermit erhalten Sie eine Gutschrift.',
                    'footer_text' => $data['footer_text'] ?? 'Der Betrag wird Ihnen wie vereinbart erstattet oder verrechnet.',
                    'billing_address' => $data['billing_address'] ?? [],
                    'shipping_address' => $data['shipping_address'] ?? [],
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax_amount,
                    'shipping_cost' => 0,
                    'express_price' => $express_price,
                    'discount_amount' => 0,
                    'volume_discount' => 0,
                    'total' => $total,
                    'notes' => $data['notes'] ?? '',
                    'custom_items' => $data['custom_items'] ?? [], // Manueller Posten-Array
                    'is_e_invoice' => false,
                    'is_small_business' => $data['is_small_business'] ?? app(\App\Services\Tax\TaxSettingService::class)->isCurrentSmallBusiness(),
                ]);

                // PDF generieren und speichern
                $this->storePdf($invoice);

                return $invoice;
            });
        }, 100);
    }

    /**
     * Generiert PDF Stream/Download
     */
    public function generatePdf(AccountingInvoice $invoice)
    {
        // Items müssen explizit geladen werden
        $invoice->load('order.items');

        // Für manuelle Rechnungen die custom_items nutzen, sonst order items
        $items = $invoice->order ? $invoice->order->items : collect($invoice->custom_items)->map(fn($i) => (object)$i);

        $pdf = Pdf::loadView('global.mails.invoice_pdf_template', [
            'invoice' => $invoice,
            'items' => $items,
            'isStorno' => $invoice->type === 'cancellation'
        ])->setPaper('a4')
          ->setOption('isRemoteEnabled', false)
          ->setOption('isHtml5ParserEnabled', true);

        return $pdf;
    }

    /**
     * NEU: Speichert das PDF physisch im Storage ab
     */
    public function storePdf(AccountingInvoice $invoice): string
    {
        try {
            $pdf = $this->generatePdf($invoice);
            $safeInvoiceNumber = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$invoice->invoice_number);
            $fileName = "buchhaltung/invoices/{$safeInvoiceNumber}.pdf";

            // Speichert die Datei ausschließlich im privaten 'local' disk (storage/app/buchhaltung/invoices) für GoBD
            Storage::disk('local')->put($fileName, $pdf->output());

            // Falls E-Rechnung aktiv: XML generieren und beifügen
            if ($invoice->is_e_invoice) {
                $this->generateEInvoiceXml($invoice);
            }

            return $fileName;
        } catch (\Exception $e) {
            Log::error("PDF Speicherung fehlgeschlagen für {$invoice->invoice_number}: " . $e->getMessage());
            return "";
        }
    }

    /**
     * E-RECHNUNG: Generiert strukturierte XML Daten (ZUGFeRD / XRechnung)
     */
    public function generateEInvoiceXml(AccountingInvoice $invoice)
    {
        return app(AccountingXmlInvoiceService::class)->generate($invoice);
    }

    /**
     * Entwürfe physisch und aus DB löschen
     */
    public function deleteDraft(AccountingInvoice $invoice)
    {
        if ($invoice->status !== 'draft') return false;

        $safeInvoiceNumber = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$invoice->invoice_number);
        $fileName = "buchhaltung/invoices/{$safeInvoiceNumber}.pdf";
        if (Storage::disk('local')->exists($fileName)) {
            Storage::disk('local')->delete($fileName);
        }
        if (Storage::disk('public')->exists($fileName)) {
            Storage::disk('public')->delete($fileName);
        }

        return $invoice->forceDelete();
    }

    public function getNextInvoiceNumber($prefix = 'RE')
    {
        return $this->generateInvoiceNumber($prefix);
    }

    public function generateInvoiceNumber($prefix = 'RE')
    {
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', strtoupper((string)$prefix)) ?: 'RE';
        $year = date('Y');
        return DB::transaction(function() use ($prefix, $year) {
            $escapedPrefix = addcslashes($prefix, '%_\\');
            $latest = AccountingInvoice::where('invoice_number', 'like', "{$escapedPrefix}-$year-%")
                ->lockForUpdate()
                ->orderByRaw('LENGTH(invoice_number) DESC')
                ->orderBy('invoice_number', 'desc')
                ->first();

            if (!$latest) {
                $next = 1000;
            } else {
                // FIX: Explode Ergebnis erst in Variable speichern, da end() eine Referenz erwartet
                $parts = explode('-', $latest->invoice_number);
                $lastPart = end($parts);
                $next = intval($lastPart) + 1;
            }

            return "$prefix-$year-$next";
        });
    }

    /**
     * Erzeugt einen EPC-QR-Code (GiroCode, EPC069-12 v2) Payload für SEPA-Überweisungen.
     */
    public function generateGiroCodePayload(
        AccountingInvoice $invoice,
        string $ownerName,
        string $ownerIban,
        string $ownerBic = ''
    ): string {
        $cleanBic = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $ownerBic));
        $cleanOwnerName = mb_substr(trim($ownerName) ?: 'Mein Seelenfunke', 0, 70);
        $cleanIban = strtoupper(preg_replace('/\s+/', '', $ownerIban));
        $amountEur = number_format(max(0.01, abs((float)$invoice->total) / 100), 2, '.', '');
        $remittance = 'Rechnung ' . $invoice->invoice_number;
        if ($invoice->order && !empty($invoice->order->order_number)) {
            $remittance .= ' / Best. ' . $invoice->order->order_number;
        }
        $remittance = mb_substr($remittance, 0, 140);

        $lines = [
            'BCD',
            '002',
            '1',
            'SCT',
            $cleanBic,
            $cleanOwnerName,
            $cleanIban,
            'EUR' . $amountEur,
            '',
            '',
            $remittance,
        ];

        return implode("\n", $lines);
    }

    /**
     * Erzeugt einen EPC-GiroCode als base64 SVG Data-URI (kompatibel mit DomPDF ohne isRemoteEnabled).
     */
    public function generateGiroCodeSvgDataUri(
        AccountingInvoice $invoice,
        string $ownerName,
        string $ownerIban,
        string $ownerBic = '',
        int $size = 110
    ): string {
        $payload = $this->generateGiroCodePayload($invoice, $ownerName, $ownerIban, $ownerBic);
        return $this->generateQrCodeSvgDataUri($payload, $size);
    }

    /**
     * Erzeugt einen Kundenportal- / Beleg-QR-Code für bereits bezahlte Rechnungen.
     */
    public function generatePortalQrSvgDataUri(
        AccountingInvoice $invoice,
        string $ownerWeb = 'www.mein-seelenfunke.de',
        int $size = 95
    ): string {
        $cleanHost = preg_replace('#^https?://#i', '', trim($ownerWeb));
        $url = 'https://' . rtrim($cleanHost, '/') . '/invoices?ref=' . urlencode((string)$invoice->invoice_number);
        return $this->generateQrCodeSvgDataUri($url, $size);
    }

    /**
     * Erzeugt einen beliebigen QR-Code als base64 SVG Data-URI via BaconQrCode.
     */
    public function generateQrCodeSvgDataUri(string $payload, int $size = 110): string
    {
        try {
            $renderer = new ImageRenderer(
                new RendererStyle($size, 1),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);
            $svg = $writer->writeString($payload);
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable $e) {
            Log::warning('QR-Code Generierung fehlgeschlagen: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Erzeugt einen 1D Barcode (Code-128 Subset B) als base64 SVG Data-URI (kompatibel mit DomPDF).
     */
    public function generateCode128BarcodeSvgDataUri(string $text, int $height = 28, float $barWidth = 1.15): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        // Offizielle Code-128 Bitmuster (0..106)
        $patterns = [
            '11011001100', '11001101100', '11001100110', '10010011000', '10010001100',
            '10001001100', '10011001000', '10011000100', '10001100100', '11001001000',
            '11001000100', '11000100100', '10110011100', '10011011100', '10011001110',
            '10111001100', '10011101100', '10011100110', '11001110010', '11001011100',
            '11001001110', '11011100100', '11001110100', '11101101110', '11101001100',
            '11100101100', '11100100110', '11101100100', '11100110100', '11100110010',
            '11011011000', '11011000110', '11000110110', '10100011000', '10001011000',
            '10001000110', '10110001000', '10001101000', '10001100010', '11010001000',
            '11000101000', '11000100010', '10110111000', '10110001110', '10001101110',
            '10111011000', '10111000110', '10001110110', '11101110110', '11010001110',
            '11000101110', '11011101000', '11011100010', '11011101110', '11101011000',
            '11101000110', '11100010110', '11101101000', '11101100010', '11100011010',
            '11101111010', '11001000010', '11110001010', '10100110000', '10100001100',
            '10010110000', '10010000110', '10000101100', '10000100110', '10110010000',
            '10110000100', '10011010000', '10011000010', '10000110100', '10000110010',
            '11000010010', '11001010000', '11110111010', '11000010100', '10001111010',
            '10100111100', '10010111100', '10010011110', '10111100100', '10011110100',
            '10011110010', '11110100100', '11110010100', '11110010010', '11011011110',
            '11011110110', '11110110110', '10101111000', '10100011110', '10001011110',
            '10111101000', '10111100010', '11110101000', '11110100010', '10111011110',
            '10111101110', '11101011110', '11110101110', '11010000100', '11010010000',
            '11010011100', '1100011101011',
        ];

        // Start Code B = 104
        $codes = [104];
        $checksum = 104;
        $len = strlen($text);
        $weight = 1;

        for ($i = 0; $i < $len; $i++) {
            $ord = ord($text[$i]);
            if ($ord < 32 || $ord > 126) {
                continue;
            }
            $val = $ord - 32;
            $codes[] = $val;
            $checksum += $val * $weight;
            $weight++;
        }

        $codes[] = $checksum % 103;
        $codes[] = 106; // Stop Code

        $binary = '';
        foreach ($codes as $code) {
            $binary .= $patterns[$code] ?? '';
        }

        $quietZone = 10;
        $totalModules = strlen($binary) + ($quietZone * 2);
        $totalWidth = round($totalModules * $barWidth, 2);

        $rects = '';
        $binLen = strlen($binary);
        $i = 0;
        while ($i < $binLen) {
            if ($binary[$i] === '1') {
                $run = 1;
                while (($i + $run) < $binLen && $binary[$i + $run] === '1') {
                    $run++;
                }
                $x = round(($quietZone + $i) * $barWidth, 2);
                $w = round($run * $barWidth, 2);
                $rects .= sprintf('<rect x="%s" y="0" width="%s" height="%d" fill="#111827"/>', $x, $w, $height);
                $i += $run;
            } else {
                $i++;
            }
        }

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%s" height="%d" viewBox="0 0 %s %d">%s</svg>',
            $totalWidth,
            $height,
            $totalWidth,
            $height,
            $rects
        );

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
