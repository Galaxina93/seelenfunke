<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Erstellt eine Rechnung aus einer Bestellung.
     */
    public function createFromOrder(Order $order): Invoice
    {
        // Prüfen ob schon existiert
        $existing = Invoice::where('order_id', $order->id)->where('type', 'invoice')->first();
        if ($existing) return $existing;

        return DB::transaction(function () use ($order) {
            $invoiceNumber = $this->generateInvoiceNumber();

            $invoice = Invoice::create([
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'invoice_number' => $invoiceNumber,
                'type' => 'invoice',
                'status' => $order->payment_status === 'paid' ? 'paid' : 'draft',
                'invoice_date' => now(),
                'due_date' => now()->addDays(14),
                'paid_at' => $order->payment_status === 'paid' ? now() : null,
                'billing_address' => $order->billing_address,
                'shipping_address' => $order->shipping_address,
                'subtotal' => $order->subtotal_price,
                'tax_amount' => $order->tax_amount,
                'shipping_cost' => $order->shipping_price,
                'total' => $order->total_price,
                // Hier könnte man die Stripe ID aus den Order-Metadaten holen, falls gespeichert
            ]);

            return $invoice;
        });
    }

    /**
     * Storniert eine Rechnung und erstellt eine Gutschrift (Stornorechnung).
     */
    public function cancelInvoice(Invoice $originalInvoice): Invoice
    {
        if ($originalInvoice->isCreditNote()) {
            throw new \Exception("Eine Gutschrift kann nicht storniert werden.");
        }

        return DB::transaction(function () use ($originalInvoice) {
            // 1. Original als 'cancelled' markieren (aber nicht löschen!)
            $originalInvoice->update(['status' => 'cancelled']);

            // 2. Stornorechnung erstellen
            $stornoNumber = $this->generateInvoiceNumber('STO'); // z.B. STO-2024-001

            $creditNote = Invoice::create([
                'order_id' => $originalInvoice->order_id,
                'customer_id' => $originalInvoice->customer_id,
                'parent_id' => $originalInvoice->id,
                'invoice_number' => $stornoNumber,
                'type' => 'cancellation', // oder 'credit_note'
                'status' => 'paid', // Storno ist sofort "abgeschlossen"
                'invoice_date' => now(),
                'billing_address' => $originalInvoice->billing_address,
                'shipping_address' => $originalInvoice->shipping_address,
                // Negative Beträge für die Buchhaltung
                'subtotal' => -$originalInvoice->subtotal,
                'tax_amount' => -$originalInvoice->tax_amount,
                'shipping_cost' => -$originalInvoice->shipping_cost,
                'total' => -$originalInvoice->total,
                'notes' => "Storno zur Rechnung Nr. " . $originalInvoice->invoice_number,
            ]);

            // Optional: Stripe Refund Logik hier triggern, falls gewünscht

            return $creditNote;
        });
    }

    /**
     * Generiert eine fortlaufende Nummer: JAHR-NUMMER (z.B. 2024-1005)
     * Verhindert Race Conditions durch DB Locking oder Atomic Checks wäre ideal,
     * hier vereinfacht für MVP.
     */
    private function generateInvoiceNumber($prefix = 'RE')
    {
        $year = date('Y');
        $latest = Invoice::where('invoice_number', 'like', "$prefix-$year-%")
            ->orderBy('id', 'desc')
            ->first();

        if (!$latest) {
            $number = 1000;
        } else {
            // Extrahiere Nummer (letzter Teil nach dem letzten Bindestrich)
            $parts = explode('-', $latest->invoice_number);
            $number = intval(end($parts)) + 1;
        }

        return "$prefix-$year-$number";
    }
}
