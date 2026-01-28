<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf; // Annahme: DomPDF ist installiert, sonst View rendern

class InvoiceService
{
    /**
     * Erstellt eine rechtssichere Rechnung aus einer bezahlten Bestellung.
     * Friert die Daten zum Zeitpunkt der Erstellung ein.
     */
    public function createFromOrder(Order $order): ?Invoice
    {
        // 1. Idempotenz-Check: Gibt es schon eine Rechnung?
        $existing = Invoice::where('order_id', $order->id)
            ->where('type', 'invoice')
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($existing) return $existing;

        return DB::transaction(function () use ($order) {
            $invoiceNumber = $this->generateInvoiceNumber();

            // 2. Erstellung & Datensnapshot
            $invoice = Invoice::create([
                'order_id' => $order->id,
                'customer_id' => $order->customer_id, // Kann null sein bei Gast
                'invoice_number' => $invoiceNumber,
                'type' => 'invoice',
                'status' => 'paid', // Da wir nur nach Payment erstellen
                'invoice_date' => now(),
                'due_date' => now(), // Sofort fällig/bezahlt
                'paid_at' => now(),

                // SNAPSHOT: Wir kopieren die Adressen hart, damit sie sich bei Profiländerung nicht ändern
                'billing_address' => $order->billing_address,
                'shipping_address' => $order->shipping_address,

                // WICHTIG: Beträge aus der Order übernehmen
                'subtotal' => $order->subtotal_price,
                'tax_amount' => $order->tax_amount,
                'shipping_cost' => $order->shipping_price,
                'total' => $order->total_price,
                'notes' => $order->notes,
            ]);

            return $invoice;
        });
    }

    /**
     * Erstellt eine Stornorechnung (Gutschrift).
     * Referenziert die Originalrechnung, verändert sie aber nicht.
     */
    public function cancelInvoice(Invoice $originalInvoice): Invoice
    {
        return DB::transaction(function () use ($originalInvoice) {

            // 1. Original als storniert markieren (aber nicht löschen/ändern!)
            $originalInvoice->update(['status' => 'cancelled']);

            // 2. Neue Stornorechnungsnummer
            $stornoNumber = $this->generateInvoiceNumber(prefix: 'STO');

            // 3. Storno-Dokument erstellen (Negative Werte)
            $creditNote = Invoice::create([
                'parent_id' => $originalInvoice->id, // Referenz
                'order_id' => $originalInvoice->order_id,
                'customer_id' => $originalInvoice->customer_id,
                'invoice_number' => $stornoNumber,
                'type' => 'cancellation',
                'status' => 'paid', // Storno ist "abgeschlossen"
                'invoice_date' => now(),
                'due_date' => now(),
                'paid_at' => now(),

                // Gleiche Adressen wie Original
                'billing_address' => $originalInvoice->billing_address,
                'shipping_address' => $originalInvoice->shipping_address,

                // NEGATIVE BETRÄGE für Buchhaltung
                'subtotal' => -1 * abs($originalInvoice->subtotal),
                'tax_amount' => -1 * abs($originalInvoice->tax_amount),
                'shipping_cost' => -1 * abs($originalInvoice->shipping_cost),
                'total' => -1 * abs($originalInvoice->total),

                'notes' => "Storno zur Rechnung Nr. " . $originalInvoice->invoice_number,
            ]);

            return $creditNote;
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

    /**
     * Atomare Nummernerzeugung: RE-2026-1001
     */
    private function generateInvoiceNumber($prefix = 'RE')
    {
        $year = date('Y');

        // Locking um Race Conditions zu vermeiden (vereinfacht)
        return DB::transaction(function() use ($prefix, $year) {
            $latest = Invoice::where('invoice_number', 'like', "$prefix-$year-%")
                ->lockForUpdate()
                ->orderByRaw('LENGTH(invoice_number) DESC') // Damit 100 nicht vor 99 kommt (String sortierung)
                ->orderBy('invoice_number', 'desc')
                ->first();

            if (!$latest) {
                $next = 1000;
            } else {
                $parts = explode('-', $latest->invoice_number);
                $next = intval(end($parts)) + 1;
            }

            return "$prefix-$year-$next";
        });
    }
}
