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
        $natuerlichVerpacken = ProductSupplier::where('name', 'LIKE', '%Natürlich verpacken%')->first();

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

        // 7. Geschenkpapier Schwarz-Gold (700 x 500 x 1 mm Zuschnitt von 2x 0,70 x 10 m Wenderolle Art.-Nr. 2821171260)
        ProductPackagingItem::updateOrCreate(
            ['sku' => 'PKG-WRAP-BLACK-GOLD'],
            [
                'name' => 'Geschenkpapier Schwarz-Gold',
                'category' => 'accessory',
                'description' => 'Beidseitig bedrucktes Wende-Geschenkpapier Schwarz & Gold (Natuerlich verpacken Art.-Nr. 2821171260, Order 22026114411-NV, 2x Rolle 0,70 x 10 m = 20 Laufmeter fuer ca. 130 Geschenkeinwicklungen). Optional +2,90 EUR im Konfigurator.',
                'supplier_name' => 'Natürlich verpacken',
                'product_supplier_id' => $natuerlichVerpacken?->id,
                'reorder_url' => 'https://www.natuerlich-verpacken.de/zweifarbiges-geschenkpapier-schwarz-gold-rolle-kaufen',
                'length_mm' => 700.0,
                'width_mm' => 500.0,
                'height_mm' => 1.0,
                'weight_grams' => 8,
                'primary_material_type' => 'paper',
                'primary_weight_grams' => 8,
                'secondary_material_type' => null,
                'secondary_weight_grams' => 0,
                'pack_quantity' => 130,
                'pack_price_cents' => 2771,
                'unit_cost_cents' => 10,
                'stock_quantity' => 130,
                'min_stock_threshold' => 25,
                'is_upsell_option' => true,
                'upsell_price_cents' => 290,
                'is_active' => true,
            ]
        );

        // 8. Vollpapp-Versandtasche DIN A5 (250 x 175 x 5 mm, Verpacking.com Art.-Nr. 3895-001)
        ProductPackagingItem::updateOrCreate(
            ['sku' => 'PKG-ENV-VOUCHER'],
            [
                'name' => 'Vollpapp-Versandtasche DIN A5',
                'category' => 'shipping_envelope',
                'description' => 'Stabile Vollpapp-Versandtasche DIN A5 selbstklebend weiss (Verpacking.com Art.-Nr. 3895-001, Innenmass 167 x 240 mm, 100er Einheit). Knickschutz fuer Post-Geschenkgutscheine im Edel-Umschlag.',
                'supplier_name' => 'Verpacking.com',
                'product_supplier_id' => null,
                'reorder_url' => 'https://www.verpacking.com/Versandtasche-Vollpappe-167x240-mm-DIN-A5-selbstklebend-weiss',
                'length_mm' => 250.0,
                'width_mm' => 175.0,
                'height_mm' => 5.0,
                'weight_grams' => 45,
                'primary_material_type' => 'paper',
                'primary_weight_grams' => 45,
                'secondary_material_type' => null,
                'secondary_weight_grams' => 0,
                'pack_quantity' => 100,
                'pack_price_cents' => 3500,
                'unit_cost_cents' => 35,
                'stock_quantity' => 100,
                'min_stock_threshold' => 20,
                'is_upsell_option' => false,
                'upsell_price_cents' => 0,
                'is_active' => true,
            ]
        );

        // 9. Schwarze Kraftpapier-Schmucktüte Klein (100 x 60 x 1 mm, Amazon ASIN B0DYHWRDFF, 200 Stk.)
        $smallBlackPaperBag = ProductPackagingItem::updateOrCreate(
            ['sku' => 'PKG-BAG-PAPER-100X60'],
            [
                'name' => 'Schwarze Kraftpapier-Schmucktüte (10 × 6 cm)',
                'category' => 'filler',
                'description' => '100 % plastikfreie, mattschwarze Kraftpapier-Schmucktuete 100 x 60 mm inkl. Klebeband (Amazon ASIN B0DYHWRDFF, Order 306-9003625-9724350, 200 Stk.). Standard-Innenverpackung fuer Anhaenger, Talisman, Pfote und Herz ohne Schmuckbox.',
                'supplier_name' => 'Amazon (REBECASKYENS / MERVYN GLYNN HUNTER LTD)',
                'product_supplier_id' => null,
                'reorder_url' => 'https://www.amazon.de/dp/B0DYHWRDFF',
                'length_mm' => 100.0,
                'width_mm' => 60.0,
                'height_mm' => 1.0,
                'weight_grams' => 2,
                'primary_material_type' => 'paper',
                'primary_weight_grams' => 2,
                'secondary_material_type' => null,
                'secondary_weight_grams' => 0,
                'pack_quantity' => 200,
                'pack_price_cents' => 1141,
                'unit_cost_cents' => 6,
                'stock_quantity' => 200,
                'min_stock_threshold' => 30,
                'is_upsell_option' => false,
                'upsell_price_cents' => 0,
                'is_active' => true,
            ]
        );

        // 10. Schwarzer Papierflachbeutel Mittel (165 x 130 x 1 mm, Frau WUNDERVoll ASIN B0CPJT35H8, 100 Stk.)
        $mediumBlackPaperBag = ProductPackagingItem::updateOrCreate(
            ['sku' => 'PKG-BAG-PAPER-165X130'],
            [
                'name' => 'Schwarzer Papierflachbeutel (13 × 16,5 cm)',
                'category' => 'filler',
                'description' => '100 % plastikfreier, mattschwarzer Papierflachbeutel 130 x 165 mm + 20 mm Umfaltlasche (Frau WUNDERVoll Amazon ASIN B0CPJT35H8, Order 306-3879066-2697958, 100 Stk.). Standard-Innenverpackung fuer 18,5 cm ID-Armbaender ohne Schmuckbox.',
                'supplier_name' => 'Amazon (Frau WUNDERVoll)',
                'product_supplier_id' => null,
                'reorder_url' => 'https://www.amazon.de/dp/B0CPJT35H8',
                'length_mm' => 165.0,
                'width_mm' => 130.0,
                'height_mm' => 1.0,
                'weight_grams' => 3,
                'primary_material_type' => 'paper',
                'primary_weight_grams' => 3,
                'secondary_material_type' => null,
                'secondary_weight_grams' => 0,
                'pack_quantity' => 100,
                'pack_price_cents' => 839,
                'unit_cost_cents' => 8,
                'stock_quantity' => 100,
                'min_stock_threshold' => 20,
                'is_upsell_option' => false,
                'upsell_price_cents' => 0,
                'is_active' => true,
            ]
        );

        // 11. Goldenes Geschenkband (10x 91,44 m Spulen im Bestand, flache Kreuzsicherung fuer Schmuckbox)
        ProductPackagingItem::updateOrCreate(
            ['sku' => 'PKG-RIBBON-GOLD'],
            [
                'name' => 'Goldenes Geschenkband',
                'category' => 'accessory',
                'description' => 'Goldfarbenes Geschenkband auf Massivholz-Bandhalter (10 Spulen a 91,44 m = 914 m, Order PO-076-06811572188790774). Standard-Sicherung um Schmuckboxen flach gegen Aufspringen im Versand zu sichern.',
                'supplier_name' => 'Bestand Manufaktur (Temu / Victory Party)',
                'product_supplier_id' => null,
                'reorder_url' => null,
                'length_mm' => 500.0,
                'width_mm' => 5.0,
                'height_mm' => 1.0,
                'weight_grams' => 1,
                'primary_material_type' => 'plastic',
                'primary_weight_grams' => 1,
                'secondary_material_type' => null,
                'secondary_weight_grams' => 0,
                'pack_quantity' => 1800,
                'pack_price_cents' => 1252,
                'unit_cost_cents' => 1,
                'stock_quantity' => 1800,
                'min_stock_threshold' => 200,
                'is_upsell_option' => false,
                'upsell_price_cents' => 0,
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

            $innerPaperBag = null;
            if (str_contains($slug, 'echtgold')) {
                $targetItem = $frankfurtBox;
            } elseif (str_contains($slug, 'kristall') || str_contains($slug, 'schiefer') || str_contains($name, 'kristall') || str_contains($name, 'schiefer')) {
                $targetItem = $crystalBox;
            } elseif (str_contains($slug, 'weizenglas') || str_contains($name, 'glas')) {
                $targetItem = $normalBox;
            } else {
                $targetItem = $bubbleEnvelope;
                if (str_contains($slug, 'armband') || str_contains($name, 'armband')) {
                    $innerPaperBag = $mediumBlackPaperBag;
                } elseif (!str_contains($slug, 'gutschein') && !str_contains($name, 'gutschein')) {
                    $innerPaperBag = $smallBlackPaperBag;
                }
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

            if ($innerPaperBag) {
                $product->packagings()->create([
                    'packaging_item_id' => $innerPaperBag->id,
                    'material_type' => $innerPaperBag->primary_material_type,
                    'label' => $innerPaperBag->name,
                    'weight_grams' => $innerPaperBag->primary_weight_grams,
                ]);
            }

            $product->packaging_weight = (int) $targetItem->weight_grams + ($innerPaperBag ? (int) $innerPaperBag->weight_grams : 0);
            $totalUnitCostCents = (int) $targetItem->unit_cost_cents + ($innerPaperBag ? (int) $innerPaperBag->unit_cost_cents : 0);
            if ($totalUnitCostCents > 0) {
                $product->packaging_cost = $totalUnitCostCents;
            }
            $product->saveQuietly();
        }

        // Falls benutzerdefinierte ProductPackaging-Zeilen mit anderen Katalog-Items verknuepft sind, Labels ebenfalls sauber nachziehen
        foreach ([$mediumBox, $largeBox] as $extraBox) {
            ProductPackaging::where('packaging_item_id', $extraBox->id)->update(['label' => $extraBox->name]);
        }
    }
}
