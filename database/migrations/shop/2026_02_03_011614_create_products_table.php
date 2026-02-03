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

            // 4. Lager & Identifikation
            // SKU nullable für Drafts, aber unique wenn gesetzt
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable();
            $table->string('brand')->nullable();
            $table->boolean('track_quantity')->default(true);
            $table->integer('quantity')->default(0);
            $table->boolean('continue_selling_when_out_of_stock')->default(false);

            // 5. Steuer
            $table->string('tax_class')->default('standard');

            // 6. Versanddaten (Erweitert um Maße & Klasse)
            $table->boolean('is_physical_product')->default(true);
            $table->integer('weight')->nullable(); // in Gramm
            $table->integer('height')->nullable(); // in mm
            $table->integer('width')->nullable();  // in mm
            $table->integer('length')->nullable(); // in mm
            $table->string('shipping_class')->nullable(); // z.B. 'sperrgut', 'brief'

            // 7. JSON-Felder (Akzeptabel für MVP/Single-Product Shops)
            $table->json('media_gallery')->nullable();
            $table->json('attributes')->nullable();
            $table->json('tier_pricing')->nullable();
            $table->json('configurator_settings')->nullable(); // Für deinen Konfigurator

            // 8. SEO
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();

            // Meta
            $table->integer('completion_step')->default(1);
            $table->string('preview_image_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
