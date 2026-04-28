<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique(); // Wichtig für die Anzeige im Backend

            // Verknüpfung zum Kunden (falls eingeloggt), sonst NULL (Gast)
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Status-Felder (genutzt in den Filtern und Badges)
            $table->string('status')->default('pending');
            $table->boolean('is_express')->default(false);

            

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
            $table->integer('express_price')->default(0); // NEU: Speichert den berechneten Express-Aufpreis in Cent

            $table->text('notes')->nullable(); // Für interne Notizen im Backend

            $table->text('cancellation_reason')->nullable();

            // UTM Tracking
            $table->string('utm_source_first')->nullable();
            $table->string('utm_campaign_first')->nullable();
            $table->string('utm_medium_first')->nullable();
            $table->string('utm_source_last')->nullable();
            $table->string('utm_campaign_last')->nullable();
            $table->string('utm_medium_last')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('order_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Spalten explizit als UUID definieren
            $table->uuid('order_id');
            $table->uuid('product_id');

            // Constraints separat definieren
            $table->foreign('order_id')
                ->references('id')
                ->on('order_orders') // Sicherstellen, dass die Tabelle 'orders' existiert!
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

            $table->string('product_name');
            $table->integer('quantity');
            $table->integer('completed_quantity')->default(0);
            $table->integer('unit_price');
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->integer('total_price');
            $table->json('configuration')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->string('config_fingerprint')->nullable();

            $table->timestamps();
        });

        Schema::create('order_quote_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('quote_number')->unique(); // z.B. AN-2024-001

            // NEU: Für den öffentlichen Link
            $table->string('token')->unique()->nullable();
            $table->timestamp('expires_at')->nullable();

            // Kontaktdaten (Snapshot)
            $table->string('email');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('company')->nullable();
            $table->string('phone')->nullable(); // Wichtig für Rückfragen

            // Status
            $table->enum('status', ['open', 'replied', 'converted', 'rejected'])->default('open');

            // Verknüpfung (falls bereits Kunde, sonst null)
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Wenn umgewandelt, hier die Order ID speichern
            $table->foreignUuid('converted_order_id')->nullable()->constrained('order_orders')->nullOnDelete();

            // Summen (Cent)
            $table->integer('net_total');
            $table->integer('tax_total');
            $table->integer('gross_total');

            $table->integer('shipping_price')->default(0);
            $table->integer('volume_discount')->default(0);
            $table->integer('express_price')->default(0); // NEU: Speichert den berechneten Express-Aufpreis in Cent

            // Flags
            $table->boolean('is_express')->default(false);


            $table->text('admin_notes')->nullable(); // Interne Notizen
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('order_quote_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quote_request_id')->constrained('order_quote_requests')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('product_name');
            $table->integer('quantity');
            $table->integer('unit_price'); // Einzelpreis Netto/Brutto je nach Logik (hier Kalkulator-Preis)
            $table->integer('total_price');

            $table->json('configuration')->nullable(); // Hier liegen die Dateipfade drin!

            $table->timestamps();
        });

        Schema::create('order_revocations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('order_number');
            $table->text('items')->nullable();
            $table->json('attachments')->nullable();
            $table->string('status')->default('pending'); // 'pending', 'processed', 'declined'
            $table->timestamp('legal_check_at')->nullable();
            $table->string('product_type')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('customer_notified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('order_orders')->cascadeOnDelete();
            
            $table->string('tracking_number'); // e.g. DHL piececode
            $table->string('shipping_label_path')->nullable(); // stored PDF label
            $table->string('carrier')->default('DHL'); // In case other carriers are added later
            $table->string('status')->default('shipped'); // status of this specific package (e.g., shipped, transit, delivered)
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_shipments');
        Schema::dropIfExists('order_revocations');
        Schema::dropIfExists('order_quote_request_items');
        Schema::dropIfExists('order_quote_requests');
        Schema::dropIfExists('order_order_items');
        Schema::dropIfExists('order_orders');
    }
};
