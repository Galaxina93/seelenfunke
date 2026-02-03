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
            $table->enum('type', ['invoice', 'credit_note', 'cancellation'])->default('invoice');
            $table->string('status')->default('paid'); // draft, paid, cancelled, sent

            // Datum
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();

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
            $table->json('custom_items')->nullable();

            // Stripe Referenz
            $table->string('stripe_payment_intent_id')->nullable();

            $table->text('notes')->nullable();
            $table->text('footer_text')->nullable();

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
