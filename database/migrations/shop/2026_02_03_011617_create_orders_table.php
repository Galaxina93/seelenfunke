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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique(); // Wichtig für die Anzeige im Backend

            // Verknüpfung zum Kunden (falls eingeloggt), sonst NULL (Gast)
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Status-Felder (genutzt in den Filtern und Badges)
            $table->string('status')->default('pending');
            $table->boolean('is_express')->default(false);
            $table->date('deadline')->nullable();

            $table->string('payment_status')->default('unpaid');
            $table->string('payment_method')->default('stripe');
            $table->text('payment_url')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();

            $table->string('email'); // Wichtig für Kommunikation & Suche

            // Adressen als JSON (Wichtig: Das Order Model castet diese automatisch zu Arrays)
            $table->json('billing_address');
            $table->json('shipping_address')->nullable();

            // Summen in Cent (Integer)
            $table->integer('subtotal_price');
            $table->integer('tax_amount');
            $table->integer('shipping_price')->default(0);
            $table->integer('total_price');


            $table->integer('volume_discount')->default(0);
            $table->string('coupon_code')->nullable(); // Speichert den Code (z.B. "SOMMER20")
            $table->integer('discount_amount')->default(0); // Speichert den Rabatt in Cent

            $table->text('notes')->nullable(); // Für interne Notizen im Backend

            $table->text('cancellation_reason')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
