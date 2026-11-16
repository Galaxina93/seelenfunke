<?php

namespace Database\Seeders;

use App\Models\Product\Product;
use App\Models\Product\ProductPackaging;
use App\Models\Product\ProductPackagingItem;
use App\Models\Product\ProductSupplier;
use Illuminate\Database\Seeder;

class ProductPackagingSeeder extends Seeder
{
    /**
     * Seed the packaging material catalog (Tab 2) and link product packagings (Tab 1).
     */
    public function run(): void
    {
        $westpack = ProductSupplier::where('name', 'LIKE', '%Westpack%')->first();
        $kartonEu = ProductSupplier::where('name', 'LIKE', '%Karton.eu%')->first();

        // Entferne veraltete LESER-, Test-SKUs und den ungenutzten Platzhalter PKG-BOX-GLASS
        $obsoleteIds = ProductPackagingItem::whereIn('sku', ['PKG-LESER-0210', 'PKG-WP-30286060102', 'PKG-BOX-GLASS'])
            ->where('sku', '!=', '30286060102')
            ->pluck('id')
            ->all();

        if (!empty($obsoleteIds)) {
            ProductPackaging::whereIn('packaging_item_id', $obsoleteIds)->update(['packaging_item_id' => null]);
            ProductPackagingItem::whereIn('id', $obsoleteIds)->delete();
        }
        ProductPackagingItem::where('name', 'LIKE', '%LESER%')->delete();

        // 1. Luftpolstertasche (260 x 180 x 10 mm, A5 braun, Karton.eu Art.-Nr. 135)
        $bubbleEnvelope = ProductPackagingItem::updateOrCreate(
            ['sku' => 'PKG-ENV-A1'],
            [
                'name' => 'Luftpolstertasche',
                'category' => 'shipping_envelope',
                'description' => 'Luftpolstertasche A5 braun (Karton.eu Art.-Nr. 135, 400er Einheit). Standard fuer kleine Einzelartikel (Anhaenger, Talisman, Hundemarke, ID-Armbaender, Gutschein).',
                'supplier_name' => 'Karton.eu (Lausitzer Kartonagen)',
                'product_supplier_id' => $kartonEu?->id,
                'reorder_url' => 'https://www.karton.eu/Luftpolstertaschen-Weiss-Braun',
                'length_mm' => 260.0,
                'width_mm' => 180.0,
                'height_mm' => 10.0,
                'weight_grams' => 20,
                'primary_material_type' => 'paper',
                'primary_weight_grams' => 16,
                'secondary_material_type' => 'plastic',
                'secondary_weight_grams' => 4,
                'pack_quantity' => 400,
                'pack_price_cents' => 5716,
                'unit_cost_cents' => 14,
                'stock_quantity' => 400,
                'min_stock_threshold' => 50,
                'is_upsell_option' => false,
                'upsell_price_cents' => 0,
                'is_active' => true,
            ]
        );

        // 2. Buchverpackung (Trophaee) (284 x 244 x 70 mm, Karton.eu Art.-Nr. 6227)
        $crystalBox = ProductPackagingItem::updateOrCreate(
            ['sku' => 'PKG-BOX-CRYSTAL'],
            [
                'name' => 'Buchverpackung (Trophäe)',
                'category' => 'shipping_box',
                'description' => 'Faltbare Wickel- und Buchverpackung (Karton.eu Art.-Nr. 6227, 240er Einheit) inkl. Polsterung. Standard fuer Der Seelen Kristall und Schiefer.',
                'supplier_name' => 'Karton.eu (Lausitzer Kartonagen)',
                'product_supplier_id' => $kartonEu?->id,
                'reorder_url' => 'https://www.karton.eu/Buchverpackungen',
                'length_mm' => 284.0,
                'width_mm' => 244.0,
                'height_mm' => 70.0,
                'weight_grams' => 155,
                'primary_material_type' => 'paper',
                'primary_weight_grams' => 140,
                'secondary_material_type' => 'plastic',
                'secondary_weight_grams' => 15,
                'pack_quantity' => 240,
                'pack_price_cents' => 14400,
                'unit_cost_cents' => 60,
                'stock_quantity' => 240,
                'min_stock_threshold' => 30,
                'is_upsell_option' => false,
                'upsell_price_cents' => 0,
                'is_active' => true,
            ]
        );

        // 3. Normaler Karton (300 x 230 x 150 mm, einwellig Karton.eu Art.-Nr. 2633)
        $normalBox = ProductPackagingItem::updateOrCreate(
            ['sku' => 'PKG-BOX-NORMAL-2633'],
            [
                'name' => 'Normaler Karton',
                'category' => 'shipping_box',
                'description' => 'Einwelliger Universal-Versandkarton (Karton.eu Art.-Nr. 2633, 140er Einheit). Fuer normale Mehr-Artikel-Bestellungen und mehrere Schmuckboxen.',
                'supplier_name' => 'Karton.eu (Lausitzer Kartonagen)',
                'product_supplier_id' => $kartonEu?->id,
                'reorder_url' => 'https://www.karton.eu/300x230x150-mm-einwellige-Kartons',
                'length_mm' => 300.0,
                'width_mm' => 230.0,
                'height_mm' => 150.0,
                'weight_grams' => 180,
                'primary_material_type' => 'paper',
                'primary_weight_grams' => 180,
                'secondary_material_type' => null,
                'secondary_weight_grams' => 0,
                'pack_quantity' => 140,
                'pack_price_cents' => 7000,
                'unit_cost_cents' => 50,
                'stock_quantity' => 140,
                'min_stock_threshold' => 20,
                'is_upsell_option' => false,
                'upsell_price_cents' => 0,
                'is_active' => true,
            ]
        );

        // 4. Mittlerer Karton (600 x 400 x 200 mm, zweiwellig BC-Welle Karton.eu Art.-Nr. 165)
        $mediumBox = ProductPackagingItem::updateOrCreate(
            ['sku' => 'PKG-BOX-MEDIUM-165'],
            [
                'name' => 'Mittlerer Karton',
                'category' => 'shipping_box',
                'description' => 'Zweiwelliger Versandkarton BC-Welle (Karton.eu Art.-Nr. 165, 20er Einheit). Fuer mittelgrosse Sammelbestellungen.',
                'supplier_name' => 'Karton.eu (Lausitzer Kartonagen)',
                'product_supplier_id' => $kartonEu?->id,
                'reorder_url' => 'https://www.karton.eu/600x400x200-mm-zweiwellige-Kartons',
                'length_mm' => 600.0,
                'width_mm' => 400.0,
                'height_mm' => 200.0,
                'weight_grams' => 750,
                'primary_material_type' => 'paper',
                'primary_weight_grams' => 750,
                'secondary_material_type' => null,
                'secondary_weight_grams' => 0,
                'pack_quantity' => 20,
                'pack_price_cents' => 5560,
                'unit_cost_cents' => 278,
                'stock_quantity' => 20,
                'min_stock_threshold' => 5,
                'is_upsell_option' => false,
                'upsell_price_cents' => 0,
                'is_active' => true,
            ]
        );

        // 5. Grosser Karton (600 x 400 x 400 mm, zweiwellig BC-Welle Karton.eu Art.-Nr. 069)
        $largeBox = ProductPackagingItem::updateOrCreate(
            ['sku' => 'PKG-BOX-LARGE-069'],
            [
                'name' => 'Großer Karton',
                'category' => 'shipping_box',
                'description' => 'Zweiwelliger Versandkarton BC-Welle (Karton.eu Art.-Nr. 069, 20er Einheit). Fuer grosse Sammelbestellungen.',
                'supplier_name' => 'Karton.eu (Lausitzer Kartonagen)',
                'product_supplier_id' => $kartonEu?->id,
                'reorder_url' => 'https://www.karton.eu/600x400x400-mm-zweiwellige-Kartons',
                'length_mm' => 600.0,
                'width_mm' => 400.0,
                'height_mm' => 400.0,
                'weight_grams' => 1040,
                'primary_material_type' => 'paper',
                'primary_weight_grams' => 1040,
                'secondary_material_type' => null,
                'secondary_weight_grams' => 0,
                'pack_quantity' => 20,
                'pack_price_cents' => 6480,
                'unit_cost_cents' => 324,
                'stock_quantity' => 20,
                'min_stock_threshold' => 5,
                'is_upsell_option' => false,
                'upsell_price_cents' => 0,
                'is_active' => true,
            ]
        );

        // 6. Schmuckbox (86 x 86 x 17 mm, Westpack Frankfurt ECO mit Logo)
        $frankfurtBox = ProductPackagingItem::updateOrCreate(
            ['sku' => '30286060102'],
            [
                'name' => 'Schmuckbox',
                'category' => 'gift_box',
                'description' => 'Mattschwarze FSC-Schmuckschachtel Frankfurt ECO inkl. Mein-Seelenfunke-Logo und schwarzer Schaumstoffeinlage (Westpack Order #29000069521, 96 Stk.).',
                'supplier_name' => 'Westpack A/S',
                'product_supplier_id' => $westpack?->id,
                'reorder_url' => 'https://www.westpack.com/eur_eu_ger/frankfurt-eco-jewellery-box-for-pendant-bangle-30286060102.html',
                'length_mm' => 86.0,
                'width_mm' => 86.0,
                'height_mm' => 17.0,
                'weight_grams' => 20,
                'primary_material_type' => 'paper',
                'primary_weight_grams' => 18,
                'secondary_material_type' => 'plastic',
                'secondary_weight_grams' => 2,
                'pack_quantity' => 96,
                'pack_price_cents' => 10176,
                'unit_cost_cents' => 106,
                'stock_quantity' => 96,
                'min_stock_threshold' => 20,
                'is_upsell_option' => true,
                'upsell_price_cents' => 490,
                'is_active' => true,
            ]
        );

        // 7. Geschenkpapier Schwarz-Gold (700 x 500 x 1 mm)
        ProductPackagingItem::updateOrCreate(
            ['sku' => 'PKG-WRAP-BLACK-GOLD'],
            [
                'name' => 'Geschenkpapier Schwarz-Gold',
                'category' => 'accessory',
                'description' => 'Edles Geschenkpapier in Mattschwarz-Gold fuer physische Manufaktur-Produkte (Optional +2,90 EUR im Konfigurator).',
                'supplier_name' => 'Handel / Manufaktur-Bedarf',
                'product_supplier_id' => null,
                'reorder_url' => null,
                'length_mm' => 700.0,
                'width_mm' => 500.0,
                'height_mm' => 1.0,
                'weight_grams' => 8,
                'primary_material_type' => 'paper',
                'primary_weight_grams' => 8,
                'secondary_material_type' => null,
                'secondary_weight_grams' => 0,
                'pack_quantity' => 100,
                'pack_price_cents' => 1000,
                'unit_cost_cents' => 10,
                'stock_quantity' => 100,
                'min_stock_threshold' => 20,
                'is_upsell_option' => true,
                'upsell_price_cents' => 290,
                'is_active' => true,
            ]
        );

        // Synchronisiere Addons auf physischen Produkten
        foreach (Product::withTrashed()->get() as $product) {
            $settings = is_array($product->configurator_settings) ? $product->configurator_settings : [];
            if ($product->isPhysical()) {
                $settings['addons'] = $product->getEffectiveAddons(false);
                $product->configurator_settings = $settings;
                $product->saveQuietly();
            } elseif (isset($settings['addons'])) {
                unset($settings['addons']);
                $product->configurator_settings = $settings;
                $product->saveQuietly();
            }
        }

        // Aktualisiere ProductPackaging-Zuordnungen und Labels auf die klaren Verpackungsnamen
        $products = Product::withTrashed()->where('type', 'physical')->get();
        foreach ($products as $product) {
            $slug = mb_strtolower((string) $product->slug);
            $name = mb_strtolower((string) $product->name);

            if (str_contains($slug, 'echtgold')) {
                $targetItem = $frankfurtBox;
            } elseif (str_contains($slug, 'kristall') || str_contains($slug, 'schiefer') || str_contains($name, 'kristall') || str_contains($name, 'schiefer')) {
                $targetItem = $crystalBox;
            } elseif (str_contains($slug, 'weizenglas') || str_contains($name, 'glas')) {
                $targetItem = $normalBox;
            } else {
                $targetItem = $bubbleEnvelope;
            }

            $product->packagings()->delete();

            if ($targetItem->primary_material_type && $targetItem->primary_weight_grams > 0) {
                $product->packagings()->create([
                    'packaging_item_id' => $targetItem->id,
                    'material_type' => $targetItem->primary_material_type,
                    'label' => $targetItem->name,
                    'weight_grams' => $targetItem->primary_weight_grams,
                ]);
            }

            if ($targetItem->secondary_material_type && $targetItem->secondary_weight_grams > 0) {
                $product->packagings()->create([
                    'packaging_item_id' => $targetItem->id,
                    'material_type' => $targetItem->secondary_material_type,
                    'label' => $targetItem->name . ' (Polster)',
                    'weight_grams' => $targetItem->secondary_weight_grams,
                ]);
            }

            $product->packaging_weight = (int) $targetItem->weight_grams;
            if ((int) $targetItem->unit_cost_cents > 0) {
                $product->packaging_cost = (int) $targetItem->unit_cost_cents;
            }
            $product->saveQuietly();
        }

        // Falls benutzerdefinierte ProductPackaging-Zeilen mit anderen Katalog-Items verknuepft sind, Labels ebenfalls sauber nachziehen
        foreach ([$mediumBox, $largeBox] as $extraBox) {
            ProductPackaging::where('packaging_item_id', $extraBox->id)->update(['label' => $extraBox->name]);
        }
    }
}
