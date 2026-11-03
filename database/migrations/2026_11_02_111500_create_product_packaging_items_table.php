<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_packaging_items')) {
            Schema::create('product_packaging_items', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('sku')->nullable()->index();
                $table->string('category')->default('shipping_box'); // gift_box, shipping_envelope, shipping_box, filler, accessory
                $table->text('description')->nullable();

                $table->string('supplier_name')->nullable();
                $table->uuid('product_supplier_id')->nullable()->index();
                $table->foreign('product_supplier_id')->references('id')->on('product_suppliers')->onDelete('set null');

                $table->text('reorder_url')->nullable();
                $table->text('image_url')->nullable();

                // Maße in Millimetern (für Post-Schablonen-Check: Großbrief <= 20mm, Maxibrief <= 50mm)
                $table->decimal('length_mm', 8, 1)->nullable();
                $table->decimal('width_mm', 8, 1)->nullable();
                $table->decimal('height_mm', 8, 1)->nullable();

                // Gewicht & LUCID-Aufschlüsselung pro 1 Stück (in Gramm)
                $table->integer('weight_grams')->default(15);
                $table->string('primary_material_type')->default('paper');
                $table->integer('primary_weight_grams')->default(15);
                $table->string('secondary_material_type')->nullable();
                $table->integer('secondary_weight_grams')->default(0);

                // Einkauf & Gebindekalkulation (in Cents netto)
                $table->integer('pack_quantity')->default(1);
                $table->integer('pack_price_cents')->default(0);
                $table->integer('unit_cost_cents')->default(0);

                // Lagerbestand & Upsell-Option im Shop
                $table->integer('stock_quantity')->default(0);
                $table->integer('min_stock_threshold')->default(10);
                $table->boolean('is_upsell_option')->default(false);
                $table->integer('upsell_price_cents')->default(0);
                $table->boolean('is_active')->default(true);

                $table->timestamps();
            });
        }

        if (Schema::hasTable('product_packagings')) {
            Schema::table('product_packagings', function (Blueprint $table) {
                if (!Schema::hasColumn('product_packagings', 'packaging_item_id')) {
                    $table->unsignedBigInteger('packaging_item_id')->nullable()->after('product_id')->index();
                    $table->foreign('packaging_item_id')
                        ->references('id')
                        ->on('product_packaging_items')
                        ->onDelete('set null');
                }
                if (!Schema::hasColumn('product_packagings', 'label')) {
                    $table->string('label')->nullable()->after('material_type');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_packagings')) {
            Schema::table('product_packagings', function (Blueprint $table) {
                if (Schema::hasColumn('product_packagings', 'packaging_item_id')) {
                    $table->dropForeign(['packaging_item_id']);
                    $table->dropColumn('packaging_item_id');
                }
                if (Schema::hasColumn('product_packagings', 'label')) {
                    $table->dropColumn('label');
                }
            });
        }

        Schema::dropIfExists('product_packaging_items');
    }
};
