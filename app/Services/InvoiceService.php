<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceService
{
    /**
     * Erstellt eine rechtssichere Rechnung aus einer bezahlten Bestellung.
     */
    public function createFromOrder(Order $order): ?Invoice
    {
        $existing = Invoice::where('order_id', $order->id)
            ->where('type', 'invoice')
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($existing) return $existing;

        return DB::transaction(function () use ($order) {
            $invoiceNumber = $this->generateInvoiceNumber();

            $invoice = Invoice::create([
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'invoice_number' => $invoiceNumber,
                'type' => 'invoice',
                'status' => 'paid',
                'invoice_date' => now(),
                'delivery_date' => $order->created_at,
                'due_date' => now(),
                'due_days' => 0,
                'paid_at' => now(),
                'subject' => 'Rechnung zu Bestellung #' . $order->order_number,
                'billing_address' => $order->billing_address,
                'shipping_address' => $order->shipping_address,
                'subtotal' => $order->subtotal_price,
                'tax_amount' => $order->tax_amount,
                'shipping_cost' => $order->shipping_price,
                'discount_amount' => $order->discount_amount,
                'volume_discount' => $order->volume_discount,
                'total' => $order->total_price,
                'notes' => $order->notes,
                'is_e_invoice' => false,
            ]);

            return $invoice;
        });
    }

    /**
     * Erstellt eine Stornorechnung (Gutschrift).
     */
    public function cancelInvoice(Invoice $originalInvoice): Invoice
    {
        return DB::transaction(function () use ($originalInvoice) {
            $originalInvoice->update(['status' => 'cancelled']);
            $stornoNumber = $this->generateInvoiceNumber(prefix: 'STO');

            return Invoice::create([
                'parent_id' => $originalInvoice->id,
                'order_id' => $originalInvoice->order_id,
                'customer_id' => $originalInvoice->customer_id,
                'invoice_number' => $stornoNumber,
                'type' => 'cancellation',
                'status' => 'paid',
                'invoice_date' => now(),
                'delivery_date' => now(),
                'due_date' => now(),
                'paid_at' => now(),
                'subject' => 'Gutschrift zur Rechnung ' . $originalInvoice->invoice_number,
                'header_text' => 'Hiermit erhalten Sie eine Gutschrift.',
                'footer_text' => 'Der Betrag wird Ihnen erstattet.',
                'billing_address' => $originalInvoice->billing_address,
                'shipping_address' => $originalInvoice->shipping_address,
                'subtotal' => -1 * abs($originalInvoice->subtotal),
                'tax_amount' => -1 * abs($originalInvoice->tax_amount),
                'shipping_cost' => -1 * abs($originalInvoice->shipping_cost),
                'discount_amount' => -1 * abs($originalInvoice->discount_amount),
                'volume_discount' => -1 * abs($originalInvoice->volume_discount),
                'total' => -1 * abs($originalInvoice->total),
                'notes' => "Storno zur Rechnung Nr. " . $originalInvoice->invoice_number,
                'is_e_invoice' => $originalInvoice->is_e_invoice,
            ]);
        });
    }

    /**
     * Generiert PDF Stream/Download
     */
    public function generatePdf(Invoice $invoice)
    {
        // Items müssen explizit geladen werden
        $invoice->load('order.items');

        $pdf = Pdf::loadView('global.mails.invoice', [
            'invoice' => $invoice,
            'items' => $invoice->order->items, // Wir nutzen die OrderItems, da diese unveränderbar sein sollten
            'isStorno' => $invoice->type === 'cancellation'
        ]);

        return $pdf;
    }

    private function generateInvoiceNumber($prefix = 'RE')
    {
        $year = date('Y');
        return DB::transaction(function() use ($prefix, $year) {
            $latest = Invoice::where('invoice_number', 'like', "$prefix-$year-%")
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
}
