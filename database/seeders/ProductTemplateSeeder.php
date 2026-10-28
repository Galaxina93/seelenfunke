<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;

class ProductTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProductTemplate::truncate();

        $products = Product::where('status', 'active')
            ->where('type', 'physical')
            ->where('is_personalizable', true)
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        foreach ($products as $product) {
            $this->createChristmasTemplate($product);
            $this->createEasterTemplate($product);
            $this->createWeddingTemplate($product);
            $this->createBirthdayTemplate($product);
            $this->createValentinesTemplate($product);
            $this->createHalloweenTemplate($product);
            $this->createMothersDayTemplate($product);
            $this->createFathersDayTemplate($product);
        }
    }

    /**
     * Berechnet optimale 2D-Koordinaten & Größen je nach Produktgeometrie (Kristall, Herz, Glas, Barren, Hundeknochen).
     */
    private function getLayoutMetrics(string $slug, float $defaultTextSize = 1.5, int $defaultLogoSize = 90): array
    {
        return match ($slug) {
            'seelen-kristall' => [
                'logo_x' => 50, 'logo_y' => 32, 'logo_size' => $defaultLogoSize,
                'text_x' => 50, 'text_y' => 60, 'text_size' => $defaultTextSize,
                'back_texts' => [],
            ],
            'seelen-anhaenger' => [
                'logo_x' => 50, 'logo_y' => 33, 'logo_size' => min(80, $defaultLogoSize),
                'text_x' => 50, 'text_y' => 58, 'text_size' => round($defaultTextSize - 0.1, 2),
                'back_texts' => [
                    [
                        'id' => Str::uuid()->toString(),
                        'text' => "Für immer verbunden\n♥",
                        'font' => 'Montserrat',
                        'align' => 'center',
                        'x' => 50,
                        'y' => 50,
                        'size' => 1.15,
                        'rotation' => 0,
                    ],
                ],
            ],
            'seelen-talisman' => [
                'logo_x' => 50, 'logo_y' => 57, 'logo_size' => 54,
                'text_x' => 50, 'text_y' => 74, 'text_size' => 1.05,
                'back_texts' => [
                    [
                        'id' => Str::uuid()->toString(),
                        'text' => "52°31'12\"N\n13°24'18\"E",
                        'font' => 'Montserrat',
                        'align' => 'center',
                        'x' => 50,
                        'y' => 69,
                        'size' => 1.0,
                        'rotation' => 0,
                    ],
                ],
            ],
            'seelen-pfote-hundemarke' => [
                'logo_x' => 46, 'logo_y' => 44, 'logo_size' => 44,
                'text_x' => 46, 'text_y' => 56, 'text_size' => 1.15,
                'back_texts' => [
                    [
                        'id' => Str::uuid()->toString(),
                        'text' => "Tel: 0176 / 123 456 78\nBitte ruf Frauchen an!",
                        'font' => 'Montserrat',
                        'align' => 'center',
                        'x' => 46,
                        'y' => 50,
                        'size' => 1.0,
                        'rotation' => 0,
                    ],
                ],
            ],
            'goldenes-seelen-herz' => [
                'logo_x' => 50, 'logo_y' => 47, 'logo_size' => 52,
                'text_x' => 50, 'text_y' => 64, 'text_size' => 1.15,
                'back_texts' => [
                    [
                        'id' => Str::uuid()->toString(),
                        'text' => "Für immer im Herzen\n♥",
                        'font' => 'Montserrat',
                        'align' => 'center',
                        'x' => 50,
                        'y' => 57,
                        'size' => 1.05,
                        'rotation' => 0,
                    ],
                ],
            ],
            default => [
                'logo_x' => 50, 'logo_y' => 32, 'logo_size' => 92,
                'text_x' => 50, 'text_y' => 58, 'text_size' => $defaultTextSize,
                'back_texts' => [],
            ],
        };
    }

    private function createChristmasTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 1.5, 90);
        $textContent = $slug === 'seelen-pfote-hundemarke'
            ? "BALU\nFrohe Weihnachten!"
            : "Frohe Weihnachten!\n- Familie Mustermann -";

        $config = [
            'text' => $textContent,
            'type' => 'physical',
            'background_theme' => 'winter',
            'files' => [],
            'texts' => [
                [
                    'id' => Str::uuid()->toString(),
                    'text' => $textContent,
                    'font' => 'Dancing Script',
                    'align' => 'center',
                    'x' => $m['text_x'],
                    'y' => $m['text_y'],
                    'size' => $m['text_size'],
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => 'vectors/Feiertage/weihnachtsbaum.svg',
                    'url' => '/storage/produkte/configurator/vectors/Feiertage/weihnachtsbaum.svg',
                    'x' => $m['logo_x'],
                    'y' => $m['logo_y'],
                    'size' => $m['logo_size'],
                    'rotation' => 0,
                ],
            ],
            'logo_path' => 'vectors/Feiertage/weihnachtsbaum.svg',
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => $m['back_texts'],
            'logos_back' => [],
            'notes' => '',
            'uploaded_files' => [],
        ];

        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Frohe Weihnachten',
            'configuration' => $config,
            'is_active' => true,
            'is_ai_generated' => true,
            'holiday' => 'Weihnachten',
            'preview_image' => "produkte/product-templates/{$product->slug}-weihnachten.jpg",
        ]);
    }

    private function createEasterTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 1.65, 90);
        $textContent = $slug === 'seelen-pfote-hundemarke' ? "BALU\nSpürhund im Dienst" : 'Frohes Osterfest!';

        $config = [
            'text' => $textContent,
            'type' => 'physical',
            'background_theme' => 'ostern',
            'files' => [],
            'texts' => [
                [
                    'id' => Str::uuid()->toString(),
                    'text' => $textContent,
                    'font' => 'Pacifico',
                    'align' => 'center',
                    'x' => $m['text_x'],
                    'y' => $m['text_y'],
                    'size' => $m['text_size'],
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => 'vectors/Tiere/haeschen.svg',
                    'url' => '/storage/produkte/configurator/vectors/Tiere/haeschen.svg',
                    'x' => $m['logo_x'],
                    'y' => $m['logo_y'],
                    'size' => $m['logo_size'],
                    'rotation' => 0,
                ],
            ],
            'logo_path' => 'vectors/Tiere/haeschen.svg',
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => $m['back_texts'],
            'logos_back' => [],
            'notes' => '',
            'uploaded_files' => [],
        ];

        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Frohes Osterfest',
            'configuration' => $config,
            'is_active' => true,
            'is_ai_generated' => true,
            'holiday' => 'Ostern',
            'preview_image' => "produkte/product-templates/{$product->slug}-ostern.jpg",
        ]);
    }

    private function createWeddingTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 1.55, 90);
        $textContent = $slug === 'seelen-pfote-hundemarke'
            ? "BALU\nSeelenhund auf Lebenszeit"
            : "Lieblingsmensch\n24.08.2026";

        $config = [
            'text' => $textContent,
            'type' => 'physical',
            'background_theme' => 'hochzeit',
            'files' => [],
            'texts' => [
                [
                    'id' => Str::uuid()->toString(),
                    'text' => $textContent,
                    'font' => 'Great Vibes',
                    'align' => 'center',
                    'x' => $m['text_x'],
                    'y' => $m['text_y'],
                    'size' => $m['text_size'],
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => 'vectors/Liebe/eheringe.svg',
                    'url' => '/storage/produkte/configurator/vectors/Liebe/eheringe.svg',
                    'x' => $m['logo_x'],
                    'y' => $m['logo_y'],
                    'size' => $m['logo_size'],
                    'rotation' => 0,
                ],
            ],
            'logo_path' => 'vectors/Liebe/eheringe.svg',
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => $m['back_texts'],
            'logos_back' => [],
            'notes' => '',
            'uploaded_files' => [],
        ];

        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Lieblingsmensch / Hochzeit',
            'configuration' => $config,
            'is_active' => true,
            'is_ai_generated' => true,
            'holiday' => 'Hochzeit',
            'preview_image' => "produkte/product-templates/{$product->slug}-hochzeit.jpg",
        ]);
    }

    private function createBirthdayTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 1.38, 85);
        $textContent = $slug === 'seelen-pfote-hundemarke' ? "BALU\nIch bin gechipt!" : 'Happy Birthday!';

        $config = [
            'text' => $textContent,
            'type' => 'physical',
            'background_theme' => 'geburtstag',
            'files' => [],
            'texts' => [
                [
                    'id' => Str::uuid()->toString(),
                    'text' => $textContent,
                    'font' => 'Montserrat',
                    'align' => 'center',
                    'x' => $m['text_x'],
                    'y' => $m['text_y'],
                    'size' => $m['text_size'],
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => 'vectors/Feiertage/geschenk.svg',
                    'url' => '/storage/produkte/configurator/vectors/Feiertage/geschenk.svg',
                    'x' => $m['logo_x'],
                    'y' => $m['logo_y'],
                    'size' => $m['logo_size'],
                    'rotation' => 0,
                ],
            ],
            'logo_path' => 'vectors/Feiertage/geschenk.svg',
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => $m['back_texts'],
            'logos_back' => [],
            'notes' => '',
            'uploaded_files' => [],
        ];

        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Alles Gute zum Geburtstag',
            'configuration' => $config,
            'is_active' => true,
            'is_ai_generated' => true,
            'holiday' => 'Geburtstag',
            'preview_image' => "produkte/product-templates/{$product->slug}-geburtstag.jpg",
        ]);
    }

    private function createValentinesTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 1.5, 88);
        $textContent = $slug === 'seelen-pfote-hundemarke'
            ? "BALU\nLiebling auf 4 Pfoten"
            : "Für immer Dein\n- 14. Februar -";

        $config = [
            'text' => $textContent,
            'type' => 'physical',
            'background_theme' => 'hochzeit',
            'files' => [],
            'texts' => [
                [
                    'id' => Str::uuid()->toString(),
                    'text' => $textContent,
                    'font' => 'Great Vibes',
                    'align' => 'center',
                    'x' => $m['text_x'],
                    'y' => $m['text_y'],
                    'size' => $m['text_size'],
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => 'vectors/Liebe/doppelherzen.svg',
                    'url' => '/storage/produkte/configurator/vectors/Liebe/doppelherzen.svg',
                    'x' => $m['logo_x'],
                    'y' => $m['logo_y'],
                    'size' => $m['logo_size'],
                    'rotation' => 0,
                ],
            ],
            'logo_path' => 'vectors/Liebe/doppelherzen.svg',
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => $m['back_texts'],
            'logos_back' => [],
            'notes' => '',
            'uploaded_files' => [],
        ];

        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Für immer Dein / Valentinstag',
            'configuration' => $config,
            'is_active' => true,
            'is_ai_generated' => true,
            'holiday' => 'Valentinstag',
            'preview_image' => "produkte/product-templates/{$product->slug}-valentinstag.jpg",
        ]);
    }

    private function createHalloweenTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 1.4, 88);
        $subline = $slug === 'weizenglas-personalisiert' ? '- Hexengebräu -' : '- Seelenzauber -';
        $textContent = $slug === 'seelen-pfote-hundemarke'
            ? "BALU\nKleiner Nachtwächter"
            : "Magische Nacht\n{$subline}";

        $config = [
            'text' => $textContent,
            'type' => 'physical',
            'background_theme' => 'herbst',
            'files' => [],
            'texts' => [
                [
                    'id' => Str::uuid()->toString(),
                    'text' => $textContent,
                    'font' => 'Cinzel',
                    'align' => 'center',
                    'x' => $m['text_x'],
                    'y' => $m['text_y'],
                    'size' => $m['text_size'],
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => 'vectors/Symbole/mond_und_sterne.svg',
                    'url' => '/storage/produkte/configurator/vectors/Symbole/mond_und_sterne.svg',
                    'x' => $m['logo_x'],
                    'y' => $m['logo_y'],
                    'size' => $m['logo_size'],
                    'rotation' => 0,
                ],
            ],
            'logo_path' => 'vectors/Symbole/mond_und_sterne.svg',
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => $m['back_texts'],
            'logos_back' => [],
            'notes' => '',
            'uploaded_files' => [],
        ];

        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Magische Nacht / Halloween',
            'configuration' => $config,
            'is_active' => true,
            'is_ai_generated' => true,
            'holiday' => 'Halloween',
            'preview_image' => "produkte/product-templates/{$product->slug}-halloween.jpg",
        ]);
    }

    private function createMothersDayTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 1.5, 88);
        $textContent = $slug === 'seelen-pfote-hundemarke'
            ? "BALU\nFrauchens Liebling"
            : "Beste Mama\nder Welt";

        $config = [
            'text' => $textContent,
            'type' => 'physical',
            'background_theme' => 'hochzeit',
            'files' => [],
            'texts' => [
                [
                    'id' => Str::uuid()->toString(),
                    'text' => $textContent,
                    'font' => 'Dancing Script',
                    'align' => 'center',
                    'x' => $m['text_x'],
                    'y' => $m['text_y'],
                    'size' => $m['text_size'],
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => 'vectors/Ornamente/herzkranz.svg',
                    'url' => '/storage/produkte/configurator/vectors/Ornamente/herzkranz.svg',
                    'x' => $m['logo_x'],
                    'y' => $m['logo_y'],
                    'size' => $m['logo_size'],
                    'rotation' => 0,
                ],
            ],
            'logo_path' => 'vectors/Ornamente/herzkranz.svg',
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => $m['back_texts'],
            'logos_back' => [],
            'notes' => '',
            'uploaded_files' => [],
        ];

        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Beste Mama der Welt / Muttertag',
            'configuration' => $config,
            'is_active' => true,
            'is_ai_generated' => true,
            'holiday' => 'Muttertag',
            'preview_image' => "produkte/product-templates/{$product->slug}-muttertag.jpg",
        ]);
    }

    private function createFathersDayTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 1.4, 86);
        $vectorRel = $slug === 'weizenglas-personalisiert'
            ? 'vectors/Symbole/bierkrug.svg'
            : 'vectors/Symbole/siegerpokal.svg';

        $textContent = $slug === 'seelen-pfote-hundemarke'
            ? "BALU\nHerrchens bester Kumpel"
            : "Bester Papa\nder Welt!";

        $config = [
            'text' => $textContent,
            'type' => 'physical',
            'background_theme' => 'geburtstag',
            'files' => [],
            'texts' => [
                [
                    'id' => Str::uuid()->toString(),
                    'text' => $textContent,
                    'font' => 'Montserrat',
                    'align' => 'center',
                    'x' => $m['text_x'],
                    'y' => $m['text_y'],
                    'size' => $m['text_size'],
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => $vectorRel,
                    'url' => '/storage/produkte/configurator/' . $vectorRel,
                    'x' => $m['logo_x'],
                    'y' => $m['logo_y'],
                    'size' => $m['logo_size'],
                    'rotation' => 0,
                ],
            ],
            'logo_path' => $vectorRel,
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => $m['back_texts'],
            'logos_back' => [],
            'notes' => '',
            'uploaded_files' => [],
        ];

        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Bester Papa / Vatertag',
            'configuration' => $config,
            'is_active' => true,
            'is_ai_generated' => true,
            'holiday' => 'Vatertag',
            'preview_image' => "produkte/product-templates/{$product->slug}-vatertag.jpg",
        ]);
    }
}
