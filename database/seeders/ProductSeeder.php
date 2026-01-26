<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Standard-Produkt (Sofort lieferbar)
        Product::create([
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall',
            'status' => 'active',
            'price' => 3990, // 39,90 €
            'tax_class' => 'standard',
            'tax_included' => true,
            'sku' => 'CRYSTAL-001',
            'track_quantity' => true,
            'quantity' => 50,
            'weight' => 200, // 200g
            'description' => 'Ein handgeschliffener Kristall für besondere Momente.',
            'attributes' => [
                'Material' => 'Hochwertiges Glas',
                'Größe' => '10x10 cm',
                'Farbe' => 'Transparent',
                'Verpackung' => 'Geschenkbox'
            ],
            // Standard Konfigurator (Text zentriert)
            'configurator_settings' => [
                'allow_text_pos' => true,
                'allow_logo' => true,
                'area_width' => 80,
                'area_height' => 80,
                'area_top' => 10,
                'area_left' => 10,
            ]
        ]);

        // 2. Personalisiertes Holzherz (Konfigurierbar)
        Product::create([
            'name' => 'Herz aus Eiche',
            'slug' => 'herz-eiche',
            'status' => 'active',
            'price' => 2490, // 24,90 €
            'tax_class' => 'standard',
            'tax_included' => true,
            'sku' => 'WOOD-HEART-05',
            'track_quantity' => true,
            'quantity' => 100,
            'weight' => 150,
            'description' => 'Natürliches Eichenholz, perfekt für Lasergravuren.',
            'attributes' => [
                'Material' => 'Eiche massiv',
                'Größe' => '15x15 cm',
                'Technik' => 'Lasergravur'
            ],
            'configurator_settings' => [
                'allow_text_pos' => true,
                'allow_logo' => false, // Kein Logo bei Holzstruktur empfohlen
                'area_width' => 60,
                'area_height' => 40,
                'area_top' => 30,
                'area_left' => 20,
            ]
        ]);

        // 3. Limitierte Edition (Fast ausverkauft)
        Product::create([
            'name' => 'Goldene Erinnerung',
            'slug' => 'goldene-erinnerung',
            'status' => 'active',
            'price' => 8900, // 89,00 €
            'tax_class' => 'standard',
            'tax_included' => true,
            'sku' => 'GOLD-LTD-99',
            'track_quantity' => true,
            'quantity' => 3, // Knappheitssignal im Shop
            'weight' => 500,
            'description' => 'Vergoldeter Rahmen mit personalisierbarem Einleger. Nur solange der Vorrat reicht.',
            'attributes' => [
                'Material' => 'Messing vergoldet',
                'Limitierung' => 'Ja',
            ],
            'configurator_settings' => [
                'allow_text_pos' => true,
                'allow_logo' => true,
                'area_width' => 50,
                'area_height' => 50,
                'area_top' => 25,
                'area_left' => 25,
            ]
        ]);
    }
}
