<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Referenzen - order_id muss nullable sein für manuelle Rechnungen
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Wenn dies eine Stornorechnung ist, verweisen wir auf das Original
            $table->foreignUuid('parent_id')->nullable()->constrained('invoices')->nullOnDelete();

            // Belegdaten
            $table->string('invoice_number')->unique(); // z.B. RE-2024-1001
            $table->string('reference_number')->nullable(); // NEU: Referenznummer
            $table->enum('type', ['invoice', 'credit_note', 'cancellation'])->default('invoice');
            $table->string('status')->default('paid'); // draft, paid, cancelled, sent
            $table->boolean('is_e_invoice')->default(false); // NEU: E-Rechnung Switch

            // Datum & Zeitraum
            $table->date('invoice_date');
            $table->date('delivery_date')->nullable(); // NEU: Lieferdatum/Leistungsdatum
            $table->date('due_date')->nullable();
            $table->integer('due_days')->default(14); // NEU: Zahlungsziel in Tagen
            $table->timestamp('paid_at')->nullable();

            // Texte
            $table->string('subject')->nullable(); // NEU: Betreff
            $table->text('header_text')->nullable(); // NEU: Kopftext
            $table->text('footer_text')->nullable(); // NEU: Fußtext (vorhanden, jetzt erweitert)

            // Snapshot der Adressen (JSON)
            $table->json('billing_address');
            $table->json('shipping_address')->nullable();

            // Beträge (Integer / Cents)
            $table->integer('subtotal');
            $table->integer('tax_amount');
            $table->integer('shipping_cost')->default(0);

            // Rabatt-Spalten für Gutscheine und Mengenrabatte
            $table->integer('discount_amount')->default(0);
            $table->integer('volume_discount')->default(0);

            $table->integer('total');

            // Flexible Posten für manuelle Rechnungen (ohne Order-Bezug)
            // Erweitert um tax_rate pro Position
            $table->json('custom_items')->nullable();

            // Stripe Referenz
            $table->string('stripe_payment_intent_id')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
