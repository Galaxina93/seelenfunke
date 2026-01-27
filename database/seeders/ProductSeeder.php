<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // ---------------------------------------------------------
        // 1. Standard-Produkt (Der Bestseller)
        // ---------------------------------------------------------
        Product::create([
            // Basisdaten
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall',
            'description' => 'Ein handgeschliffener Kristall für besondere Momente. Durch die hochwertige 3D-Innengravur schwebt Ihr Wunschmotiv förmlich im Glas. Ein unvergessliches Geschenk für Hochzeiten, Jahrestage oder als Erinnerungsstück. Lieferumfang: Kristallglas, Geschenkbox, Pflegehinweise.',
            'short_description' => 'Personalisiertes 3D-Glasgeschenk inkl. Geschenkbox.',
            'status' => 'active',

            // Preis & Steuer
            'price' => 3990, // 39,90 €
            'compare_at_price' => 4990, // UVP 49,90 € (Zeigt "Angebot" an)
            'cost_per_item' => 1250, // Einkauf: 12,50 € (für Margenberechnung)
            'tax_class' => 'standard', // 19%
            'tax_included' => true,

            // Lager & Identifikation
            'sku' => 'CRYSTAL-001-CLR',
            'barcode' => '426000000001',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 150, // Guter Lagerbestand
            'continue_selling_when_out_of_stock' => true, // Nachbestellung möglich

            // Versand (Kompaktes Paket)
            'is_physical_product' => true,
            'weight' => 250, // Gramm (inkl. Verpackung)
            'height' => 100, // mm
            'width' => 100,  // mm
            'length' => 100, // mm
            'shipping_class' => 'paket_s',

            // Medien & Konfigurator
            'preview_image_path' => 'Testdata/overlay.png', // Relativ zu storage/app/public/
            'media_gallery' => [
                [
                    'type' => 'image',
                    'path' => 'Testdata/seelen-kristall_b.jpg',
                    'is_main' => true,
                    'alt' => 'Seelen Kristall Frontansicht'
                ],
                [
                    'type' => 'video',
                    'path' => 'Testdata/video.mp4',
                    'is_main' => false
                ]
            ],
            'configurator_settings' => [
                'allow_text_pos' => true,
                'allow_logo' => true,
                'area_width' => 80,
                'area_height' => 80,
                'area_top' => 4,
                'area_left' => 10,
            ],

            // JSON Daten
            'attributes' => [
                'Material' => 'Hochwertiges K9 Kristallglas',
                'Größe' => '10x10x10 cm',
                'Farbe' => 'Transparent',
                'Verpackung' => 'Schwarze Geschenkbox mit Seidenfutter',
                'Druck' => 'UV-Direktdruck (optional)',
                'Technik' => '3D-Laser-Innengravur',
                'Gewicht' => '200 g (Netto)'
            ],
            'tier_pricing' => [
                ['qty' => 5, 'percent' => 5],   // 5% Rabatt ab 5 Stk.
                ['qty' => 10, 'percent' => 10], // 10% Rabatt ab 10 Stk.
                ['qty' => 25, 'percent' => 15]  // 15% Rabatt ab 25 Stk.
            ],

            // SEO
            'seo_title' => 'Der Seelen Kristall | Personalisierbares Glasgeschenk | Mein-Seelenfunke',
            'seo_description' => 'Verschenken Sie Ewigkeit: Unser Seelen Kristall aus hochwertigem Glas mit individueller 3D-Gravur. Perfekt für Hochzeiten und Jubiläen. Jetzt gestalten!',
            'completion_step' => 5 // Vollständig ausgefüllt
        ]);

        // ---------------------------------------------------------
        // 2. Personalisiertes Holzherz (Naturprodukt)
        // ---------------------------------------------------------
        Product::create([
            'name' => 'Herz aus Eiche',
            'slug' => 'herz-eiche',
            'description' => 'Natürliches, massives Eichenholz, liebevoll in Herzform geschliffen. Jedes Stück ist durch die individuelle Maserung ein Unikat. Die dunkle Lasergravur bildet einen wunderschönen Kontrast zum hellen Holz.',
            'short_description' => 'Massives Eichenholz-Herz mit Wunschgravur.',
            'status' => 'active',

            'price' => 2490,
            'compare_at_price' => null, // Kein Angebot
            'cost_per_item' => 800,
            'tax_class' => 'standard',
            'tax_included' => true,

            'sku' => 'WOOD-HEART-05',
            'barcode' => '426000000002',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 45,
            'continue_selling_when_out_of_stock' => false, // Wenn weg, dann weg (bis Nachproduktion)

            // Versand (Briefversand möglich da flach)
            'is_physical_product' => true,
            'weight' => 180,
            'height' => 20,
            'width' => 150,
            'length' => 150,
            'shipping_class' => 'grossbrief',

            'preview_image_path' => null,
            'media_gallery' => [],
            'configurator_settings' => [
                'allow_text_pos' => true,
                'allow_logo' => false,
                'area_width' => 60,
                'area_height' => 40,
                'area_top' => 30,
                'area_left' => 20,
            ],

            'attributes' => [
                'Material' => 'Eiche massiv (FSC-zertifiziert)',
                'Größe' => '15x15x2 cm',
                'Technik' => 'Präzisions-Lasergravur',
                'Farbe' => 'Natur geölt',
                'Druck' => '-',
                'Gewicht' => '150 g'
            ],
            'tier_pricing' => [
                ['qty' => 10, 'percent' => 8],
                ['qty' => 50, 'percent' => 15] // Für Gastgeschenke Hochzeit
            ],

            'seo_title' => 'Personalisiertes Holzherz aus Eiche | Gravur Geschenk',
            'seo_description' => 'Massives Eichenholz-Herz mit individueller Lasergravur. Handgefertigt und natürlich geölt. Ein Unikat für die Ewigkeit von Mein-Seelenfunke.',
            'completion_step' => 4 // Bilder fehlen noch
        ]);

        // ---------------------------------------------------------
        // 3. Limitierte Edition (High Ticket)
        // ---------------------------------------------------------
        Product::create([
            'name' => 'Goldene Erinnerung',
            'slug' => 'goldene-erinnerung',
            'description' => 'Limitierte Edition: Dieser exklusive Rahmen ist mit 24 Karat Blattgold veredelt. Der Einleger wird in einem speziellen Druckverfahren hergestellt, das Farben besonders brillant leuchten lässt.',
            'short_description' => 'Vergoldeter Rahmen, limitiert auf 100 Stück.',
            'status' => 'active',

            'price' => 8900,
            'compare_at_price' => 12900, // Starker Preisanker
            'cost_per_item' => 4500,
            'tax_class' => 'standard',
            'tax_included' => true,

            'sku' => 'GOLD-LTD-99',
            'barcode' => '426000000099',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 3, // Verknappung!
            'continue_selling_when_out_of_stock' => false,

            // Versand (Versichertes Paket)
            'is_physical_product' => true,
            'weight' => 800,
            'height' => 50,
            'width' => 250,
            'length' => 250,
            'shipping_class' => 'paket_m',

            'preview_image_path' => null,
            'media_gallery' => [],
            'configurator_settings' => [
                'allow_text_pos' => true,
                'allow_logo' => true,
                'area_width' => 50,
                'area_height' => 50,
                'area_top' => 25,
                'area_left' => 25,
            ],

            'attributes' => [
                'Material' => 'Messing, 24K vergoldet',
                'Limitierung' => 'Ja, Nr. zertifiziert',
                'Technik' => 'Handpoliert',
                'Druck' => 'Premium Golddruck',
                'Gewicht' => '500 g',
                'Farbe' => 'Gold / Weiß',
                'Verpackung' => 'Samt-Etui mit Echtheitszertifikat'
            ],
            'tier_pricing' => null, // Keine Mengenrabatte bei Limitierung

            'seo_title' => 'Limitierte Edition: Goldene Erinnerung | 24K Vergoldet',
            'seo_description' => 'Exklusiver, vergoldeter Rahmen für Ihre wertvollsten Erinnerungen. Streng limitierte Auflage. Sichern Sie sich Ihr Exemplar bei Mein-Seelenfunke.',
            'completion_step' => 5
        ]);
    }
}
