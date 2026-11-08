<?php

namespace Database\Seeders;

use App\Models\Product\ProductIdea;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ProductIdeaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!Schema::hasTable('product_ideas')) {
            Schema::create('product_ideas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->integer('rank')->default(10)->index();
                $table->boolean('is_top_priority')->default(false)->index();
                $table->string('title');
                $table->string('category')->default('echtschmuck')->index();
                $table->string('status')->default('top_idea')->index();
                $table->string('material_summary')->nullable();
                $table->string('supplier_name')->nullable();
                $table->uuid('product_supplier_id')->nullable()->index();
                $table->foreign('product_supplier_id')->references('id')->on('product_suppliers')->nullOnDelete();
                $table->integer('purchase_price_net_cents')->default(0);
                $table->integer('purchase_price_gross_cents')->default(0);
                $table->integer('packaging_cost_cents')->default(106);
                $table->integer('shipping_cost_cents')->default(450);
                $table->integer('target_price_gross_cents')->default(0);
                $table->integer('expected_profit_cents')->default(0);
                $table->json('components_links')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        $supSilberketten = \App\Models\Product\ProductSupplier::where('name', 'like', '%Silberketten-Store%')->first();
        $supMateria = \App\Models\Product\ProductSupplier::where('name', 'like', '%MATERIA%')->first();
        $supFbm = \App\Models\Product\ProductSupplier::where('name', 'like', '%Friedrich Binder%')->first();

        // TOP 1 PRODUKT-IDEE (Bereits in Atelier-Kollektion übertragen): Das Echtgold Seelen-Herz im Set (585er Gold-Herz + 333er Gold-Panzerkette)
        $setTargetGross = 34900; // 349,00 € Brutto-VK im Set
        $setPurchaseNet = 17059; // 112,61 € netto (Herz 585) + 57,98 € netto (Panzerkette 333) = 170,59 € netto
        $setPurchaseGross = 20300; // 134,00 € brutto + 69,00 € brutto = 203,00 € brutto
        $setProfitNet = ProductIdea::computeExpectedProfitCents($setTargetGross, $setPurchaseNet, 106, 450);

        ProductIdea::updateOrCreate(
            ['title' => 'Das Echtgold Seelen-Herz – 585er Gold-Herz & 333er Gold-Panzerkette (Komplett-Set)'],
            [
                'rank' => 1,
                'is_top_priority' => true,
                'category' => 'echtschmuck',
                'status' => 'transferred',
                'material_summary' => 'Anhänger: 585er Echtgold / 14 Karat (13 × 14 mm, 0,80 g, gestempelt 585) · Kette: 333er Echtgold Panzerkette (0,8 mm fein, gestempelt 333)',
                'supplier_name' => 'Silberketten-Store.de (Einzelabruf ab 1 Stk.)',
                'product_supplier_id' => $supSilberketten?->id,
                'purchase_price_net_cents' => $setPurchaseNet,
                'purchase_price_gross_cents' => $setPurchaseGross,
                'packaging_cost_cents' => 106, // 1,06 € netto (Schwarze Westpack Frankfurt ECO Schmuckschachtel mit Gold-Logo)
                'shipping_cost_cents' => 450,  // 4,50 € netto (Versichertes DHL-Paket bis 500 € Haftung)
                'target_price_gross_cents' => $setTargetGross,
                'expected_profit_cents' => $setProfitNet,
                'components_links' => [
                    [
                        'title' => 'Herz-Anhänger: 585er Echtgold (13 × 14 mm · 0,80 g · Art.-Nr. 235559.400)',
                        'sku' => '235559.400 / 30002023',
                        'spec' => '13 × 14 mm · 0,80 g · 585 Gelbgold (ungraviert bestellen)',
                        'price_gross_cents' => 13400,
                        'price_net_cents' => 11261,
                        'url' => 'https://www.silberketten-store.de/GOLDSCHMUCK/Kettenanhaenger/Gravuranhaenger-Herz-235559-400-585-Gold-10704',
                    ],
                    [
                        'title' => 'Passende Kette im Set: 333er Echtgold Panzerkette (0,8 mm fein)',
                        'sku' => 'Panzerkette 0,8 mm - 333 Gold',
                        'spec' => '0,8 mm Stärke · 333 Gelbgold · Federringverschluss',
                        'price_gross_cents' => 6900,
                        'price_net_cents' => 5798,
                        'url' => 'https://www.silberketten-store.de/GOLDSCHMUCK/Ketten/Panzerketten/Panzerkette-0-8mm-333-Gold',
                    ],
                ],
                'notes' => "✅ BEREITS IN DIE KOLLEKTION ÜBERTRAGEN (Bereich: Atelier Echtgold & Silber · SKU: MSF-ECHTGOLD-HERZ-001):\n"
                    . "• Im Shop aktiv unter „Atelier Echtgold & Silber“ mit Startbestand 0 (Auftragsbezogene Einzelanfertigung).\n"
                    . "• Bestell-Hinweis beim Händler: Auf der Produktseite das Dropdown „Gravur“ leer lassen („Bitte wählen Sie“), damit der blanke, gestempelte 585er Gold-Rohling ohne 5,00 € Gravur-Aufpreis geliefert wird.\n"
                    . "• Preis-Strategie: Komplett-Set (585er Gold-Herz + 333er Gold-Panzerkette + schwarze Westpack-Box + versicherter DHL-Versand) für 349,00 € brutto (~109,69 € Netto-Reingewinn pro Verkauf!).",
            ]
        );

        // IDEE 2: Das Silber-Seelenband – Figaro-Gravurarmband 4,0 mm (925 Sterling Silber)
        $b1TargetGross = 8990; // 89,90 € Brutto-VK
        $b1PurchaseNet = 1924; // 19,24 € netto (22,90 € brutto)
        $b1PurchaseGross = 2290;
        $b1ProfitNet = ProductIdea::computeExpectedProfitCents($b1TargetGross, $b1PurchaseNet, 106, 450);

        ProductIdea::updateOrCreate(
            ['title' => 'Das Silber-Seelenband – Figaro-Gravurarmband 4,0 mm (925 Sterling Silber)'],
            [
                'rank' => 2,
                'is_top_priority' => true,
                'category' => 'echtschmuck',
                'status' => 'top_idea',
                'material_summary' => '925 Sterling Silber (gestempelt 925) · 4,0 mm Figarokette · Gravurplatte 40 × 4 mm (beidseitig gravierbar) · Längen 16–22 cm · Karabiner',
                'supplier_name' => 'Silberketten-Store.de (Einzelabruf ab 1 Stk.)',
                'product_supplier_id' => $supSilberketten?->id,
                'purchase_price_net_cents' => $b1PurchaseNet,
                'purchase_price_gross_cents' => $b1PurchaseGross,
                'packaging_cost_cents' => 106,
                'shipping_cost_cents' => 450,
                'target_price_gross_cents' => $b1TargetGross,
                'expected_profit_cents' => $b1ProfitNet,
                'components_links' => [
                    [
                        'title' => 'Figaro-Gravurarmband 4,0 mm – 925 Sterling Silber (Gravurplatte 40 × 4 mm)',
                        'sku' => 'Figaro-Gravurarmband-4mm',
                        'spec' => '925 Silber · Platte 40 × 4 mm (beidseitig) · Längen 16 bis 22 cm wählbar',
                        'price_gross_cents' => 2290,
                        'price_net_cents' => 1924,
                        'url' => 'https://www.silberketten-store.de/SILBERSCHMUCK/Gravurartikel/Gravurarmbaender/Figaro-Gravurarmband-4mm',
                    ],
                ],
                'notes' => "🏆 TOP-FAVORIT ARMBAND (DAMEN & UNISEX – KEIN HERZ, STARKE ABWECHSLUNG IM ATELIER):\n"
                    . "• Warum dieses Armband perfekt ist: Elegantes 925er Echt-Silber ID-Armband mit 40 × 4 mm Gravurschild. Perfekt für Koordinaten, Namen, Jahrestage oder feine Handschrift-Gravuren mit dem 5W UV-Laser.\n"
                    . "• Extrem niedriger EK (nur 22,90 € brutto / 19,24 € netto) bei hohem Echtschmuck-Wahrnehmungswert (89,90 € VK -> über 50 € Netto-Reingewinn pro Armband!).",
            ]
        );

        // IDEE 3: Das Partner- & Herren-Seelenband – Massives Panzer-ID-Armband 5,5 mm (925 Sterling Silber)
        $b2TargetGross = 12900; // 129,00 € Brutto-VK
        $b2PurchaseNet = 4118;  // 41,18 € netto (49,00 € brutto)
        $b2PurchaseGross = 4900;
        $b2ProfitNet = ProductIdea::computeExpectedProfitCents($b2TargetGross, $b2PurchaseNet, 106, 450);

        ProductIdea::updateOrCreate(
            ['title' => 'Das Partner- & Herren-Seelenband – Massives Panzer-ID-Armband 5,5 mm (925 Sterling Silber)'],
            [
                'rank' => 3,
                'is_top_priority' => false,
                'category' => 'echtschmuck',
                'status' => 'top_idea',
                'material_summary' => '925 Sterling Silber massiv (gestempelt 925) · 5,5 mm Panzerkette (1,5 mm Stärke) · Gravurplatte 40 × 5,5 mm (beidseitig) · Längen 16–25 cm',
                'supplier_name' => 'Silberketten-Store.de (Einzelabruf ab 1 Stk.)',
                'product_supplier_id' => $supSilberketten?->id,
                'purchase_price_net_cents' => $b2PurchaseNet,
                'purchase_price_gross_cents' => $b2PurchaseGross,
                'packaging_cost_cents' => 106,
                'shipping_cost_cents' => 450,
                'target_price_gross_cents' => $b2TargetGross,
                'expected_profit_cents' => $b2ProfitNet,
                'components_links' => [
                    [
                        'title' => 'Massives Panzer-Gravurarmband 5,5 mm – 925 Sterling Silber (Platte 40 × 5,5 mm)',
                        'sku' => 'Gravurarmband-5-5mm',
                        'spec' => '925 Silber massiv · Platte 40 × 5,5 × 1,5 mm · Längen 16 bis 25 cm (Herren & Partner)',
                        'price_gross_cents' => 4900,
                        'price_net_cents' => 4118,
                        'url' => 'https://www.silberketten-store.de/SILBERSCHMUCK/Gravurartikel/Gravurarmbaender/Gravurarmband-5-5mm',
                    ],
                ],
                'notes' => "Starkes Herren-, Vatertags- & Partner-Armband im Atelier-Bereich:\n"
                    . "• Durch die 5,5 mm breite und 1,5 mm starke Gravurplatte wirkt das Armband sehr wertig und maskulin/modern.\n"
                    . "• Dank Längen von 16 cm bis 25 cm sowohl als Herren-Armband (20–23 cm) als auch als Partner-Set (Damen + Herren) verkaufbar.",
            ]
        );

        // IDEE 4: Das Vergoldete Kugel-Seelenband – Elastic Elements Gravurarmband (925 Silber vergoldet)
        $b3TargetGross = 13900; // 139,00 € Brutto-VK
        $b3PurchaseNet = 4193;  // 41,93 € netto (49,90 € brutto)
        $b3PurchaseGross = 4990;
        $b3ProfitNet = ProductIdea::computeExpectedProfitCents($b3TargetGross, $b3PurchaseNet, 106, 450);

        ProductIdea::updateOrCreate(
            ['title' => 'Das Vergoldete Kugel-Seelenband – Elastic Elements Gravurarmband (925 Silber vergoldet)'],
            [
                'rank' => 4,
                'is_top_priority' => false,
                'category' => 'echtschmuck',
                'status' => 'planned',
                'material_summary' => '925 Sterling Silber hochwertig vergoldet · ca. 7,5 g · 20 cm flexibel · 3 mm Kugeln + 8 mm Kugel mit runder Gravurplatte',
                'supplier_name' => 'Silberketten-Store.de (Einzelabruf ab 1 Stk.)',
                'product_supplier_id' => $supSilberketten?->id,
                'purchase_price_net_cents' => $b3PurchaseNet,
                'purchase_price_gross_cents' => $b3PurchaseGross,
                'packaging_cost_cents' => 106,
                'shipping_cost_cents' => 450,
                'target_price_gross_cents' => $b3TargetGross,
                'expected_profit_cents' => $b3ProfitNet,
                'components_links' => [
                    [
                        'title' => 'Kugel-Gravurarmband „Elastic Elements II“ – 925 Silber vergoldet (7,5 g)',
                        'sku' => 'Elastic-Elements-Armband-II-925-Silber-vergoldet',
                        'spec' => '925 Silber vergoldet · 7,5 g · 20 cm elastisch · runde Gravurplatte für Initialen/Symbol',
                        'price_gross_cents' => 4990,
                        'price_net_cents' => 4193,
                        'url' => 'https://www.silberketten-store.de/Elastic-Elements-Armband-II-925-Silber-vergoldet',
                    ],
                ],
                'notes' => "Modernes Damen-Kugelarmband aus vergoldetem 925er Echtsilber:\n"
                    . "• Kein Größenproblem beim Verschenken durch das flexible Schmuckband.\n"
                    . "• Runde Gravurplatte ideal für Monogramme, Initialen, Sternzeichen oder ein kleines Unendlichkeits-Symbol.",
            ]
        );

        // IDEE 5: Das Echtgold Tauf- & Geburts-Seelenband (333er / 585er Echtgold Panzer- & Figaro-Armband)
        $b4TargetGross = 29900; // 299,00 € Brutto-VK (333er Gold) bzw. 349,00 € (585er Gold)
        $b4PurchaseNet = 15042; // 150,42 € netto (179,00 € brutto für 333er Gold)
        $b4PurchaseGross = 17900;
        $b4ProfitNet = ProductIdea::computeExpectedProfitCents($b4TargetGross, $b4PurchaseNet, 106, 450);

        ProductIdea::where('title', 'Das Echtgold Tauf- & Geburts-Armband – Gravurarmband (333er & 585er Echtgold)')->delete();

        ProductIdea::updateOrCreate(
            ['title' => 'Das Echtgold Tauf- & Geburts-Seelenband – Gravurarmband (333er & 585er Echtgold)'],
            [
                'rank' => 5,
                'is_top_priority' => false,
                'category' => 'echtschmuck',
                'status' => 'planned',
                'material_summary' => 'Echtes 333er Gelbgold (Panzer 1,8 mm, Platte 25 × 5 mm, 12–16 cm verstellbar) oder 585er Gelbgold (Figaro 1,5 mm, Platte 20 × 5 mm)',
                'supplier_name' => 'Silberketten-Store.de (Einzelabruf ab 1 Stk.)',
                'product_supplier_id' => $supSilberketten?->id,
                'purchase_price_net_cents' => $b4PurchaseNet,
                'purchase_price_gross_cents' => $b4PurchaseGross,
                'packaging_cost_cents' => 106,
                'shipping_cost_cents' => 450,
                'target_price_gross_cents' => $b4TargetGross,
                'expected_profit_cents' => $b4ProfitNet,
                'components_links' => [
                    [
                        'title' => '333er Echtgold Gravurarmband Panzer 1,8 mm (Platte 25 × 5 mm · 12–16 cm)',
                        'sku' => 'Kinder-Gravurarmband-Panzer-1-8mm-333-Gold',
                        'spec' => '333 Gelbgold · ca. 1,30 g · Platte 25 × 5 mm (beidseitig) · variabel 12/14/16 cm',
                        'price_gross_cents' => 17900,
                        'price_net_cents' => 15042,
                        'url' => 'https://www.silberketten-store.de/SILBERSCHMUCK/Gravurartikel/Kindergravurarmbaender/Kinder-Gravurarmband-Panzer-1-8mm-333-Gold',
                    ],
                    [
                        'title' => 'Upgrade-Option: 585er Echtgold Gravurarmband Figaro 1,5 mm (Platte 20 × 5 mm)',
                        'sku' => 'Kinder-Gravurarmband-Figaro-1-5mm-585-Gold',
                        'spec' => '585 Gelbgold (14 Karat) · ca. 1,10 g · Platte 20 × 5 mm · 14 cm',
                        'price_gross_cents' => 21200,
                        'price_net_cents' => 17815,
                        'url' => 'https://www.silberketten-store.de/GOLDSCHMUCK/Gravurartikel/Kindergravurarmbaender/Kinder-Gravurarmband-Figaro-1-5mm-585-Gold',
                    ],
                ],
                'notes' => "Klassisches Echtgold-Geschenk zur Geburt, Taufe (Patenonkel/Patentante/Großeltern) mit Name auf der Vorderseite und Geburtsdatum/Uhrzeit auf der Rückseite.\n"
                    . "• Bis 16 cm Tragbar (auch für sehr schmale Handgelenke).\n"
                    . "• Über 95 € Netto-Reingewinn pro verkauftem Armband.",
            ]
        );

        // IDEE 6: Das 585er Echtgold Herz einzeln (ohne Kette – falls Kundin eigene Goldkette besitzt)
        $soloTargetGross = 24900; // 249,00 € Brutto-VK Solo-Anhänger
        $soloPurchaseNet = 11261; // 112,61 € netto (134,00 € brutto)
        $soloPurchaseGross = 13400;
        $soloProfitNet = ProductIdea::computeExpectedProfitCents($soloTargetGross, $soloPurchaseNet, 106, 450);

        ProductIdea::updateOrCreate(
            ['title' => 'Das Echtgold Seelen-Herz – 585er Gold-Herz (Einzel-Anhänger ohne Kette)'],
            [
                'rank' => 6,
                'is_top_priority' => false,
                'category' => 'echtschmuck',
                'status' => 'sample_needed',
                'material_summary' => 'Anhänger einzeln: 585er Echtgold / 14 Karat (13 × 14 mm, 0,80 g, gestempelt 585, inkl. schwarzer Westpack-Box)',
                'supplier_name' => 'Silberketten-Store.de (Einzelabruf ab 1 Stk.)',
                'product_supplier_id' => $supSilberketten?->id,
                'purchase_price_net_cents' => $soloPurchaseNet,
                'purchase_price_gross_cents' => $soloPurchaseGross,
                'packaging_cost_cents' => 106,
                'shipping_cost_cents' => 450,
                'target_price_gross_cents' => $soloTargetGross,
                'expected_profit_cents' => $soloProfitNet,
                'components_links' => [
                    [
                        'title' => 'Herz-Anhänger einzeln: 585er Echtgold (13 × 14 mm · 0,80 g · Art.-Nr. 235559.400)',
                        'sku' => '235559.400 / 30002023',
                        'spec' => '13 × 14 mm · 0,80 g · 585 Gelbgold (ungraviert bestellen)',
                        'price_gross_cents' => 13400,
                        'price_net_cents' => 11261,
                        'url' => 'https://www.silberketten-store.de/GOLDSCHMUCK/Kettenanhaenger/Gravuranhaenger-Herz-235559-400-585-Gold-10704',
                    ],
                ],
                'notes' => "Einstiegs-Option für Kundinnen, die bereits eine eigene Goldkette besitzen: 249,00 € brutto für den gravierten 585er Echtgold-Herzanhänger.",
            ]
        );

        // IDEE 7: Das Rhodiniertes Herren-Seelenband (925 Silber anlaufgeschützt – Alternative Händler: MATERIA Schmuck, FBM & Max Fröhlich B2B)
        $b5TargetGross = 18900; // 189,00 € Brutto-VK
        $b5PurchaseNet = 10248; // 102,48 € netto (121,95 € brutto bei MATERIA im Einzelabruf; via FBM/Max Fröhlich B2B noch günstiger)
        $b5PurchaseGross = 12195;
        $b5ProfitNet = ProductIdea::computeExpectedProfitCents($b5TargetGross, $b5PurchaseNet, 106, 450);

        ProductIdea::updateOrCreate(
            ['title' => 'Das Rhodinierte Herren-Seelenband – Panzer-Gravurarmband 925 Silber (MATERIA / FBM Made in Germany)'],
            [
                'rank' => 7,
                'is_top_priority' => false,
                'category' => 'echtschmuck',
                'status' => 'planned',
                'material_summary' => '925 Sterling Silber (hochwertig rhodiniert / anlaufgeschützt) · Verstellbar 19–23 cm · Massive Gravurplatte (Variante „Ohne Gravur“ als Blanko-Rohling)',
                'supplier_name' => 'MATERIA Schmuck / Friedrich Binder (FBM) / Max Fröhlich B2B',
                'product_supplier_id' => $supMateria?->id ?? $supFbm?->id,
                'purchase_price_net_cents' => $b5PurchaseNet,
                'purchase_price_gross_cents' => $b5PurchaseGross,
                'packaging_cost_cents' => 106,
                'shipping_cost_cents' => 450,
                'target_price_gross_cents' => $b5TargetGross,
                'expected_profit_cents' => $b5ProfitNet,
                'components_links' => [
                    [
                        'title' => 'MATERIA 925 Silber Herren-Panzer-Gravurarmband (rhodiniert, 19–23 cm, Variante „Ohne Gravur“)',
                        'sku' => 'MATERIA-A-10553',
                        'spec' => '925 Silber rhodiniert (anlaufgeschützt) · 19–23 cm · Sofort ab 1 Stk. ohne Gravur bestellbar',
                        'price_gross_cents' => 12195,
                        'price_net_cents' => 10248,
                        'url' => 'https://www.materia-schmuck.de/gravur-armband-herren-panzer-kette/a-10553/',
                    ],
                    [
                        'title' => 'MATERIA Ultra-Massiv 44g Herren-Panzer-Gravurarmband 925 Silber (Platte 40 × 9 × 2 mm, „Ohne Gravur“)',
                        'sku' => 'MATERIA-A-11492',
                        'spec' => '44g 925 Silber massiv · Made in Germany · Platte 40 × 9 × 2 mm · Variante „Ohne Gravur“',
                        'price_gross_cents' => 26995,
                        'price_net_cents' => 22685,
                        'url' => 'https://www.materia-schmuck.de/gravur-armband-maenner-silber-925/a-11492/',
                    ],
                    [
                        'title' => 'Friedrich Binder (FBM Pforzheim) – B2B-Hersteller für 333/585 Echtgold & 925 Silber ID-Armbänder ab Werk',
                        'sku' => 'FBM-ID-ARMBAENDER',
                        'spec' => 'Deutscher Traditionshersteller seit 1910 · 925 Silber, 333 Gold, 585 Gold & 750 Gold Blanko-ID-Armbänder',
                        'price_gross_cents' => 0,
                        'price_net_cents' => 0,
                        'url' => 'https://www.fbm-chains.com/schmuck/id-armbaender.php',
                    ],
                    [
                        'title' => 'Max Fröhlich GmbH – B2B-Großhandel für Gold- & Silber-Identitäts-Armbänder',
                        'sku' => 'MAXFROEHLICH-B2B-ID',
                        'spec' => 'Deutscher B2B-Großhandel (mit Gewerbenachweis) · Identitäts-Armbänder in Gold & Silber zum Großhandels-EK',
                        'price_gross_cents' => 0,
                        'price_net_cents' => 0,
                        'url' => 'https://b2b-shop.maxfroehlich.de/standard-schmuck/gold-silberketten-und-armbaender/identitaets-armbaender',
                    ],
                    [
                        'title' => 'Kleckow GmbH Pforzheim – B2B Echtschmuck-Großhandel (Same-Day-Versand)',
                        'sku' => 'KLECKOW-B2B',
                        'spec' => 'Pforzheimer B2B-Großhandel für 925 Silber & 333/585 Gold Armbänder ohne hohen Mindestbestellwert',
                        'price_gross_cents' => 0,
                        'price_net_cents' => 0,
                        'url' => 'https://kleckow.de/schmuck-b2b-shop/',
                    ],
                ],
                'notes' => "Alternative deutsche Händler & B2B-Hersteller neben Silberketten-Store.de:\n"
                    . "1. MATERIA Schmuck (Bad Köstritz): Sofort-Einzelabruf ab 1 Stk. ohne B2B-Hürde (im Dropdown einfach „Ohne Gravur“ wählen) – rhodiniertes, anlaufgeschütztes 925 Silber.\n"
                    . "2. Friedrich Binder FBM (Mönsheim bei Pforzheim): Der eigentliche deutsche Werkshersteller vieler Juwelier-ID-Armbänder in 925 Silber, 333 Gold und 585 Gold (eigener B2B-Shop mit Gewerbeschein).\n"
                    . "3. Max Fröhlich GmbH (Essen) & Kleckow GmbH (Pforzheim): Echte B2B-Großhändler für Gold- & Silber-Identitätsarmbänder zu Großhandelskonditionen.",
            ]
        );
    }
}
