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

            // 2. Basisdaten & Typisierung
            $table->string('name');
            $table->string('slug')->unique();

            // NEU: Produkttyp statt Boolean (physical, digital, service)
            $table->string('type')->default('physical')->index();

            $table->enum('status', ['draft', 'active', 'archived'])->default('draft')->index();

            $table->longText('description')->nullable();
            $table->text('short_description')->nullable();

            // 3. Preis & Steuer
            $table->integer('price'); // Preis in Cent
            $table->integer('compare_at_price')->nullable(); // Preis in Cent
            $table->integer('cost_per_item')->nullable(); // Preis in Cent
            $table->string('tax_class')->default('standard');

            // 4. Lager & Identifikation
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable();
            $table->string('brand')->nullable();

            // Lagerhaltung (für Digital oft irrelevant, aber optional möglich für Limitierungen)
            $table->boolean('track_quantity')->default(true);
            $table->integer('quantity')->default(0);
            $table->boolean('continue_selling_when_out_of_stock')->default(false);

            // 5. Physische Attribute (Nur relevant wenn type = physical)
            // is_physical_product WURDE ENTFERNT
            $table->integer('weight')->nullable(); // in Gramm
            $table->integer('height')->nullable(); // in mm
            $table->integer('width')->nullable();  // in mm
            $table->integer('length')->nullable(); // in mm
            $table->string('shipping_class')->nullable();

            // 6. JSON-Felder
            $table->json('media_gallery')->nullable();
            $table->json('attributes')->nullable();
            $table->json('tier_pricing')->nullable();
            $table->json('configurator_settings')->nullable();

            // 7. SEO
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();

            // Meta
            $table->integer('completion_step')->default(1);
            $table->string('preview_image_path')->nullable();

            // Speichert den internen Pfad zur geschützten Datei
            $table->string('digital_download_path')->nullable();
            // Optional: Originaler Dateiname für den Download (z.B. "Mein-Ebook.pdf")
            $table->string('digital_filename')->nullable();
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
