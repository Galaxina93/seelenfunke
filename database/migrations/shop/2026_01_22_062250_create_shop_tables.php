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

        // 4. Warenkörbe
        Schema::create('carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_id')->nullable()->index();

            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('coupon_code')->nullable();

            $table->timestamps();
        });

        // 3. Warenkorb Positionen
        Schema::create('cart_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();

            $table->integer('quantity')->default(1);
            $table->integer('unit_price');
            $table->json('configuration')->nullable();

            $table->timestamps();
        });

        // 4. Bestellungen
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique();

            // Verweis auf Customers (UUID)
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->string('email');

            $table->json('billing_address');
            $table->json('shipping_address')->nullable();

            // Summen
            $table->integer('subtotal_price');
            $table->integer('tax_amount');
            $table->integer('shipping_price')->default(0);
            $table->integer('total_price');

            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. Bestellpositionen
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('product_name');
            $table->integer('quantity');
            $table->integer('unit_price');
            $table->integer('total_price');
            $table->json('configuration')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
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
