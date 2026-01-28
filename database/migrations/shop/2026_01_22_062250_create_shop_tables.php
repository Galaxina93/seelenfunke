<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Produkte
        Schema::create('products', function (Blueprint $table) {
            // 1. Grundstruktur
            $table->uuid('id')->primary();
            $table->timestamps();
            $table->softDeletes(); // Wichtig für GoBD & Wiederherstellung

            // 2. Basisdaten
            $table->string('name');
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->text('short_description')->nullable();

            // Verbesserung: Status als Enum (Datenbank-Level Validierung)
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft')->index();

            // 3. Preis & Steuer (tax_rate entfernt!)
            $table->integer('price'); // Preis in Cent
            $table->integer('compare_at_price')->nullable(); // Preis in Cent
            $table->integer('cost_per_item')->nullable(); // Preis in Cent

            // Referenz auf Steuerlogik statt festem Satz
            $table->string('tax_class')->default('standard');
            $table->boolean('tax_included')->default(true); // Brutto vs Netto Eingabe

            // 4. Lager & Identifikation
            // SKU nullable für Drafts, aber unique wenn gesetzt
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable();
            $table->string('brand')->nullable();
            $table->boolean('track_quantity')->default(true);
            $table->integer('quantity')->default(0);
            $table->boolean('continue_selling_when_out_of_stock')->default(false);

            // 5. Versanddaten (Erweitert um Maße & Klasse)
            $table->boolean('is_physical_product')->default(true);
            $table->integer('weight')->nullable(); // in Gramm
            $table->integer('height')->nullable(); // in mm
            $table->integer('width')->nullable();  // in mm
            $table->integer('length')->nullable(); // in mm
            $table->string('shipping_class')->nullable(); // z.B. 'sperrgut', 'brief'

            // 6. JSON-Felder (Akzeptabel für MVP/Single-Product Shops)
            $table->json('media_gallery')->nullable();
            $table->json('attributes')->nullable();
            $table->json('tier_pricing')->nullable();
            $table->json('configurator_settings')->nullable(); // Für deinen Konfigurator

            // 7. SEO
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();

            // Meta
            $table->integer('completion_step')->default(1);
            $table->string('preview_image_path')->nullable();
        });
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // z.B. "Standard DE"
            $table->decimal('rate', 5, 2); // z.B. 19.00
            $table->string('country_code', 2)->default('DE'); // ISO Code
            $table->string('tax_class')->default('standard'); // Verknüpfung zum Produkt
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        DB::table('tax_rates')->insert([
            ['name' => 'Standard DE', 'rate' => 19.00, 'tax_class' => 'standard', 'is_default' => true],
            ['name' => 'Ermäßigt DE', 'rate' => 7.00, 'tax_class' => 'reduced', 'is_default' => false],
        ]);

        // Gutscheine
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->enum('type', ['fixed', 'percent']); // Festbetrag oder Prozent
            $table->integer('value'); // Betrag in Cent oder Prozent (ganzzahlig)
            $table->integer('min_order_value')->nullable(); // Mindestbestellwert in Cent
            $table->integer('usage_limit')->nullable(); // Wie oft insgesamt nutzbar
            $table->integer('used_count')->default(0);
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Newsletter
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('ip_address')->nullable(); // Wichtig für Nachweisbarkeit
            $table->boolean('privacy_accepted')->default(false); // Checkbox Status
            $table->timestamp('subscribed_at')->useCurrent();

            // Für Double-Opt-In (DOI) Prozess
            $table->boolean('is_verified')->default(false);
            $table->string('verification_token')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });

        // Warenkörbe
        Schema::create('carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_id')->nullable()->index();

            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('coupon_code')->nullable();

            $table->timestamps();
        });

        // Warenkorb Positionen
        Schema::create('cart_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();

            $table->integer('quantity')->default(1);
            $table->integer('unit_price');
            $table->json('configuration')->nullable();

            $table->timestamps();
        });

        // Bestellungen
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique(); // Wichtig für die Anzeige im Backend

            // Verknüpfung zum Kunden (falls eingeloggt), sonst NULL (Gast)
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Status-Felder (genutzt in den Filtern und Badges)
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
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

        // Bestellpositionen
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Löscht Items, wenn Bestellung gelöscht wird
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();

            // Falls das Produkt gelöscht wird, bleibt die Position erhalten (product_id wird null)
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('product_name'); // Snapshot des Namens zum Kaufzeitpunkt
            $table->integer('quantity');
            $table->integer('unit_price'); // Preis zum Kaufzeitpunkt
            $table->integer('total_price');

            // Konfiguration (Gravur etc.) als JSON
            $table->json('configuration')->nullable();

            $table->timestamps();
        });

        // Rechnung
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Referenzen
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Wenn dies eine Stornorechnung ist, verweisen wir auf das Original
            $table->foreignUuid('parent_id')->nullable()->constrained('invoices')->nullOnDelete();

            // Belegdaten
            $table->string('invoice_number')->unique(); // z.B. RE-2024-1001
            $table->enum('type', ['invoice', 'credit_note', 'cancellation']); // Rechnung, Gutschrift, Storno
            $table->string('status')->default('draft'); // draft, paid, cancelled, sent

            // Datum
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Snapshot der Adressen (JSON), damit Änderungen am User die Rechnung nicht verfälschen
            $table->json('billing_address');
            $table->json('shipping_address')->nullable();

            // Beträge (Integer / Cents)
            $table->integer('subtotal');
            $table->integer('tax_amount');
            $table->integer('shipping_cost')->default(0);
            $table->integer('total');

            // Stripe Referenz (optional für Abgleich)
            $table->string('stripe_payment_intent_id')->nullable();

            $table->text('notes')->nullable();
            $table->text('footer_text')->nullable(); // Für individuelle Hinweise auf der PDF

            $table->timestamps();
            $table->softDeletes();
        });

        // Calculator Anfragen
        Schema::create('quote_requests', function (Blueprint $table) {
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
            $table->foreignUuid('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();

            // Summen (Cent)
            $table->integer('net_total');
            $table->integer('tax_total');
            $table->integer('gross_total');

            // Flags
            $table->boolean('is_express')->default(false);
            $table->date('deadline')->nullable();

            $table->text('admin_notes')->nullable(); // Interne Notizen
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('quote_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quote_request_id')->constrained('quote_requests')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('product_name');
            $table->integer('quantity');
            $table->integer('unit_price'); // Einzelpreis Netto/Brutto je nach Logik (hier Kalkulator-Preis)
            $table->integer('total_price');

            $table->json('configuration')->nullable(); // Hier liegen die Dateipfade drin!

            $table->timestamps();
        });

        // Staffelpreise
        Schema::create('product_tier_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();

            $table->integer('qty'); // Ab Menge X (z.B. 5)
            $table->decimal('percent', 5, 2); // Rabatt in Prozent (z.B. 5.00)

            $table->timestamps();
        });
    }

    public function down(): void
    {

        Schema::dropIfExists('product_tier_prices');
        Schema::dropIfExists('quote_request_items');
        Schema::dropIfExists('quote_requests');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('products');
    }
};
