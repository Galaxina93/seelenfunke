<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Produkte
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Basisdaten
            $table->string('name');
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('status')->default('draft'); // active, draft, archived

            // Preis & Steuer
            $table->integer('price'); // Cent
            $table->integer('compare_at_price')->nullable();
            $table->integer('cost_per_item')->nullable();
            $table->string('tax_class')->default('standard'); // standard, reduced, zero
            $table->decimal('tax_rate', 5, 2)->default(19.00);
            $table->boolean('tax_included')->default(true);

            // Lager
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable();
            $table->boolean('track_quantity')->default(true);
            $table->integer('quantity')->default(0);
            $table->boolean('continue_selling_when_out_of_stock')->default(false);
            $table->string('brand')->nullable();

            // Versand
            $table->boolean('is_physical_product')->default(true);
            $table->integer('weight')->nullable();

            // Daten & Konfiguration
            $table->json('media_gallery')->nullable();
            $table->string('preview_image_path')->nullable();

            $table->json('attributes')->nullable();
            $table->json('tier_pricing')->nullable();
            $table->json('configurator_settings')->nullable();

            // SEO
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->integer('completion_step')->default(1);

            $table->softDeletes();
            $table->timestamps();
        });

        // 2. Warenkörbe
        Schema::create('carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_id')->nullable()->index();

            // Verweis auf Customers (UUID)
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

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
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('products');
    }
};
