<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Suppliers
        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('company_name')->nullable();
            
            // Address Data
            $table->string('street')->nullable();
            $table->string('house_number')->nullable();
            $table->string('zip')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('country_code', 2)->nullable(); // For Flag UI (e.g. DE, CN)
            
            // Contact Data
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            
            // Business Data
            $table->string('tax_id')->nullable();
            $table->string('vat_id')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('iban')->nullable();
            $table->string('bic')->nullable();
            $table->string('customer_number')->nullable();
            
            // Conditions
            $table->string('payment_terms')->nullable();
            $table->integer('minimum_order_value')->nullable(); // in Cent
            $table->integer('shipping_costs')->nullable(); // in Cent
            
            // Lead times & Logistics
            $table->integer('lead_time_land_days')->nullable();
            $table->integer('lead_time_air_days')->nullable();
            $table->integer('lead_time_sea_days')->nullable();
            $table->integer('lead_time_train_days')->nullable();
            $table->string('shipping_method')->nullable();
            $table->json('dynamic_links')->nullable(); // [{"title": "WeChat", "url": "..."}]
            $table->timestamps();
        });

        // 2. Products
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamps();
            $table->softDeletes();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('physical')->index();
            $table->boolean('is_personalizable')->default(true);
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft')->index();
            $table->longText('description')->nullable();
            $table->text('short_description')->nullable();

            $table->integer('price');
            $table->integer('compare_at_price')->nullable();
            $table->string('tax_class')->default('standard');

            $table->foreignUuid('product_supplier_id')->nullable()->constrained('product_suppliers')->nullOnDelete();
            $table->string('reorder_url', 1024)->nullable();            
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable();
            $table->string('brand')->nullable();

            $table->boolean('track_quantity')->default(true);
            $table->integer('quantity')->default(0);
            $table->boolean('continue_selling_when_out_of_stock')->default(false);

            $table->integer('weight')->nullable(); // in Gramm
            $table->integer('packaging_weight')->nullable(); // Tara in Gramm
            $table->integer('height')->nullable(); // in mm
            $table->integer('width')->nullable();  // in mm
            $table->integer('length')->nullable(); // in mm
            $table->string('shipping_class')->nullable();

            $table->json('media_gallery')->nullable();
            $table->json('attributes')->nullable();
            $table->json('variants_data')->nullable();
            $table->json('tier_pricing')->nullable();
            $table->json('configurator_settings')->nullable();

            $table->integer('purchase_price')->default(0);
            $table->integer('laser_runtime_minutes')->nullable();
            $table->integer('electricity_wear_factor')->default(1);
            $table->integer('packaging_cost')->nullable();
            $table->integer('shipping_cost')->nullable();
            $table->decimal('marketing_cost_percent', 5, 2)->default(15.00);
            $table->integer('delivery_time_days')->default(14);

            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();

            $table->integer('completion_step')->default(1);
            $table->string('preview_image_path')->nullable();

            $table->string('three_d_model_path')->nullable();
            $table->string('three_d_background_path')->nullable();

            $table->string('digital_download_path')->nullable();
            $table->string('digital_filename')->nullable();
        });

        // 3. Tier Prices
        Schema::create('product_tier_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('qty');
            $table->decimal('percent', 5, 2);
            $table->timestamps();
        });

        // 4. Categories
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('physical');
            $table->string('color')->nullable();
            $table->timestamps();
        });

        // 5. Pivot Table
        Schema::create('product_product_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // 6. Attributes
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->nullable();
            $table->string('type')->default('physical');
            $table->timestamps();
        });

        // 7. Reviews
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->integer('rating');
            $table->string('title')->nullable();
            $table->text('content');
            $table->json('media')->nullable();
            $table->string('status')->default('approved'); // approved, pending, rejected
            $table->timestamps();
        });

        // 8. Templates
        Schema::create('product_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->json('configuration')->nullable();
            $table->string('preview_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('holiday')->nullable();
            $table->timestamps();
        });

        // 9. Niche Items
        Schema::create('product_niche_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('platform');
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('sales_volume')->default(0)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('review_count')->default(0)->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->text('url')->nullable();
            $table->integer('niche_score')->default(0);
            $table->json('raw_data')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();
        });

        // 10. Niche Crawler Runs
        Schema::create('product_niche_crawler_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('admin_id')->nullable();
            $table->string('name');
            $table->string('keyword')->nullable();
            $table->string('platform')->nullable();
            $table->json('products_data')->nullable(); // Alle 40 gescannten Produkte als Array.
            $table->text('ai_recommendation')->nullable(); // Speichert das finale Text-Urteil der KI.
            $table->unsignedBigInteger('ai_agent_id')->nullable();
            $table->timestamps();
        });

        // 11. Losses
        Schema::create('product_losses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->index();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            
            $table->uuid('product_supplier_id')->nullable();
            $table->foreign('product_supplier_id')->references('id')->on('product_suppliers')->onDelete('set null');

            $table->integer('quantity'); // Anzahl an kaputten Einheiten
            $table->integer('cost_value'); // Theoretischer Wertverlust in Cents (Einkaufspreis * Menge)
            $table->text('reason')->nullable(); // Grund für Schwund (z.B. Laser kaputt, Splitter, Falschlieferung)
            
            $table->timestamp('reported_to_supplier_at')->nullable();
            $table->timestamp('refund_received_at')->nullable();
            
            $table->string('recorded_by')->nullable(); // UUID des Admins
            $table->timestamps();
        });

        // 12. Packagings
        Schema::create('product_packagings', function (Blueprint $table) {
            $table->id();
            $table->uuid('product_id')->index();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->string('material_type'); // Enum/String key: 'paper', 'plastic', 'glass', etc.
            $table->integer('weight_grams'); // Gewicht in Gramm
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_packagings');
        Schema::dropIfExists('product_losses');
        Schema::dropIfExists('product_niche_crawler_runs');
        Schema::dropIfExists('product_niche_items');
        Schema::dropIfExists('product_templates');
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('product_product_category');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('product_tier_prices');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_suppliers');
    }
};
