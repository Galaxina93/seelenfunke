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

        // TOP 1 PRODUKT-IDEE: Das Echtgold Seelen-Herz im Set (585er Gold-Herz + 333er Gold-Panzerkette)
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
                'status' => 'top_idea',
                'material_summary' => 'Anhänger: 585er Echtgold / 14 Karat (13 × 14 mm, 0,80 g, gestempelt 585) · Kette: 333er Echtgold Panzerkette (0,8 mm fein, gestempelt 333)',
                'supplier_name' => 'Silberketten-Store.de (Einzelabruf ab 1 Stk.)',
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
                        'spec' => '0,8 mm Stärke · 333 Gelbgold · Karabinerverschluss',
                        'price_gross_cents' => 6900,
                        'price_net_cents' => 5798,
                        'url' => 'https://www.silberketten-store.de/GOLDSCHMUCK/Ketten/Panzerketten/Panzerkette-0-8mm-333-Gold',
                    ],
                ],
                'notes' => "🏆 TOP 1 PRODUKT-FAVORIT (ECHTGOLD-ATELIER-SET):\n"
                    . "• Keine KI-Bilder: Vor Live-Schaltung (und für Influencer-Bewerbung) wird 1 echtes Muster-Set bestellt, unterm xTool F2 Ultra UV (5W UV-Laser) graviert und mit weißen Handschuhen in der schwarzen Westpack-Box echt fotografiert & gefilmt.\n"
                    . "• Bestell-Hinweis beim Händler: Auf der Produktseite das Dropdown „Gravur“ leer lassen („Bitte wählen Sie“), damit der blanke, gestempelte 585er Gold-Rohling ohne 5,00 € Gravur-Aufpreis geliefert wird.\n"
                    . "• Preis-Strategie: Komplett-Set (585er Gold-Herz + 333er Gold-Panzerkette + schwarze Westpack-Box + versicherter DHL-Versand) für 349,00 € brutto (293,28 € netto -> ~109,69 € Netto-Reingewinn pro Verkauf!).\n"
                    . "• Sicherheit unterm Laser: Erst Positionstest auf 0,50 € Messing-Plättchen in Schablone fahren, danach das echte 585er Gold-Herz einlegen.",
            ]
        );

        // OPTION 2: Das 585er Echtgold Herz einzeln (ohne Kette – falls Kundin eigene Goldkette besitzt)
        $soloTargetGross = 24900; // 249,00 € Brutto-VK Solo-Anhänger
        $soloPurchaseNet = 11261; // 112,61 € netto (134,00 € brutto)
        $soloPurchaseGross = 13400;
        $soloProfitNet = ProductIdea::computeExpectedProfitCents($soloTargetGross, $soloPurchaseNet, 106, 450);

        ProductIdea::updateOrCreate(
            ['title' => 'Das Echtgold Seelen-Herz – 585er Gold-Herz (Einzel-Anhänger ohne Kette)'],
            [
                'rank' => 2,
                'is_top_priority' => false,
                'category' => 'echtschmuck',
                'status' => 'sample_needed',
                'material_summary' => 'Anhänger einzeln: 585er Echtgold / 14 Karat (13 × 14 mm, 0,80 g, gestempelt 585, inkl. schwarzer Westpack-Box)',
                'supplier_name' => 'Silberketten-Store.de (Einzelabruf ab 1 Stk.)',
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
                'notes' => "Einstiegs-Option für Kundinnen, die bereits eine eigene Goldkette besitzen: 249,00 € brutto für den gravierten 585er Echtgold-Herzanhänger (+100,00 € Aufpreis für die passende 0,8 mm 333er Echtgold-Panzerkette im Set = 349,00 €).",
            ]
        );
    }
}
