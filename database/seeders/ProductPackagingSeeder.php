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
     * Seed the packaging material catalog (Tab 2) and link existing product packagings (Tab 1).
     */
    public function run(): void
    {
        $westpack = ProductSupplier::where('name', 'LIKE', '%Westpack%')->first();
        $kartonEu = ProductSupplier::where('name', 'LIKE', '%Karton.eu%')->first();

        // Entferne veraltete LESER- oder alte Test-SKUs
        ProductPackagingItem::whereIn('sku', ['PKG-LESER-0210', 'PKG-WP-30286060102'])
            ->where('sku', '!=', '30286060102')
            ->delete();
        ProductPackagingItem::where('name', 'LIKE', '%LESER%')->delete();

        // 1. Westpack Frankfurt ECO Schmuckschachtel 86 x 86 x 17 mm (Order #29000069521 - 96 Stk. mit Logo)
        $frankfurtBox = ProductPackagingItem::updateOrCreate(
            ['sku' => '30286060102'],
            [
                'name' => 'Frankfurt ECO Schmuckschachtel Anhänger/Armreif (mit Logo)',
                'category' => 'gift_box',
                'description' => 'Mattschwarze FSC-Schmuckschachtel inkl. Mein-Seelenfunke-Logodruck & schwarzer Schaumstoffeinlage (Order #29000069521: 96 Stk. à 1,06 € netto = 101,76 € netto / 145,95 € inkl. Versand & Steuer). 17 mm flach für 1,80 € Großbrief.',
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
                'pack_price_cents' => 10176, // 101,76 € netto (96 × 1,06 €)
                'unit_cost_cents' => 106,    // 1,06 € netto / Stück
                'stock_quantity' => 96,
                'min_stock_threshold' => 20,
                'is_upsell_option' => true,
                'upsell_price_cents' => 490, // +4,90 € als edle Geschenkbox-Option
                'is_active' => true,
            ]
        );

        // 2. Luftpolster-Versandtasche A/1 (Großbrief-Versandtasche für Anhänger & Schmuckboxen)
        $bubbleEnvelope = ProductPackagingItem::updateOrCreate(
            ['sku' => 'PKG-ENV-A1'],
            [
                'name' => 'Luftpolster-Versandtasche A/1 (Großbrief)',
                'category' => 'shipping_envelope',
                'description' => 'Gepolsterte Versandtasche (400er Karton von Karton.eu) für Schlüsselanhänger, Hundemarken, Herz-Medaillons und die 17 mm Frankfurt ECO Schmuckschachtel.',
                'supplier_name' => 'Karton.eu (Lausitzer Kartonagen)',
                'product_supplier_id' => $kartonEu?->id,
                'reorder_url' => 'https://www.karton.eu/Luftpolstertaschen-Weiss-Braun',
                'length_mm' => 175.0,
                'width_mm' => 120.0,
                'height_mm' => 10.0,
                'weight_grams' => 15,
                'primary_material_type' => 'composite',
                'primary_weight_grams' => 12,
                'secondary_material_type' => 'paper',
                'secondary_weight_grams' => 3,
                'pack_quantity' => 400,
                'pack_price_cents' => 5600,
                'unit_cost_cents' => 14,
                'stock_quantity' => 400,
                'min_stock_threshold' => 50,
                'is_upsell_option' => false,
                'upsell_price_cents' => 0,
                'is_active' => true,
            ]
        );

        // 3. Einwelliger Versandkarton Kristall & Schiefer (240 x 210 x 60 mm)
        $crystalBox = ProductPackagingItem::updateOrCreate(
            ['sku' => 'PKG-BOX-CRYSTAL'],
            [
                'name' => 'Versandkarton Kristall & Schiefer (inkl. Polster)',
                'category' => 'shipping_box',
                'description' => 'Einwelliger Versandkarton (240er Bestand von Karton.eu) für 3D-Kristalle und Schiefertafeln inkl. Luftpolsterfolie.',
                'supplier_name' => 'Karton.eu (Lausitzer Kartonagen)',
                'product_supplier_id' => $kartonEu?->id,
                'reorder_url' => 'https://www.karton.eu/Einwellige-Kartons',
                'length_mm' => 240.0,
                'width_mm' => 210.0,
                'height_mm' => 60.0,
                'weight_grams' => 150,
                'primary_material_type' => 'paper',
                'primary_weight_grams' => 130,
                'secondary_material_type' => 'plastic',
                'secondary_weight_grams' => 20,
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

        // 4. Versandkarton Trinkglas / Weizenglas (250 x 100 x 100 mm)
        $glassBox = ProductPackagingItem::updateOrCreate(
            ['sku' => 'PKG-BOX-GLASS'],
            [
                'name' => 'Versandkarton Weizenglas & Gravurglas',
                'category' => 'shipping_box',
                'description' => 'Stoßfester Versandkarton für gravierte Weizengläser inkl. Noppenfolien-Polsterung.',
                'supplier_name' => 'Karton.eu (Lausitzer Kartonagen)',
                'product_supplier_id' => $kartonEu?->id,
                'reorder_url' => 'https://www.karton.eu/Flaschenkartons-Glaskartons',
                'length_mm' => 250.0,
                'width_mm' => 100.0,
                'height_mm' => 100.0,
                'weight_grams' => 120,
                'primary_material_type' => 'paper',
                'primary_weight_grams' => 100,
                'secondary_material_type' => 'plastic',
                'secondary_weight_grams' => 20,
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

        // 5. Geschenkpapier-Service im einheitlichen, edlen Schwarz-Gold-Look (Für alle physischen Produkte buchbar)
        ProductPackagingItem::updateOrCreate(
            ['sku' => 'PKG-WRAP-BLACK-GOLD'],
            [
                'name' => 'Geschenkpapier (Edler Schwarz-Gold-Look)',
                'category' => 'accessory',
                'description' => 'Einheitliches, edles Geschenkpapier in Mattschwarz-Gold für alle physischen Manufaktur-Produkte (Optional als Geschenkverpackungs-Aufpreis +2,90 € buchbar).',
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
                'pack_price_cents' => 1000, // ca. 10,00 € für 10–25m Rolle (~100 Einschläge)
                'unit_cost_cents' => 10,    // ~0,10 € pro Geschenk-Einschlag
                'stock_quantity' => 100,
                'min_stock_threshold' => 20,
                'is_upsell_option' => true,
                'upsell_price_cents' => 290, // +2,90 € im Konfigurator (nur physische Produkte)
                'is_active' => true,
            ]
        );

        // Verknüpfe bestehende ProductPackaging-Einträge (Tab 1) mit dem Katalog (Tab 2)
        // und synchronisiere die Schwarz-Gold-Geschenkverpackungs-Option ausschließlich auf physischen Produkten
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

        $products = Product::where('type', 'physical')->get();
        foreach ($products as $product) {
            $slug = (string) $product->slug;
            $name = mb_strtolower((string) $product->name);

            if (str_contains($slug, 'kristall') || str_contains($slug, 'schiefer') || str_contains($name, 'kristall') || str_contains($name, 'schiefer')) {
                ProductPackaging::where('product_id', $product->id)
                    ->whereNull('packaging_item_id')
                    ->update([
                        'packaging_item_id' => $crystalBox->id,
                        'label' => $crystalBox->name,
                    ]);
            } elseif (str_contains($slug, 'weizenglas') || str_contains($name, 'glas')) {
                ProductPackaging::where('product_id', $product->id)
                    ->whereNull('packaging_item_id')
                    ->update([
                        'packaging_item_id' => $glassBox->id,
                        'label' => $glassBox->name,
                    ]);
            } else {
                ProductPackaging::where('product_id', $product->id)
                    ->whereNull('packaging_item_id')
                    ->update([
                        'packaging_item_id' => $bubbleEnvelope->id,
                        'label' => $bubbleEnvelope->name,
                    ]);
            }
        }
    }
}
