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

    private function createChristmasTemplate(Product $product): void
    {
        $slug = $product->slug;
        $textY = $slug === 'seelen-kristall' ? 60 : 58;
        $textSize = $slug === 'seelen-anhaenger' ? 1.4 : 1.5;
        $logoY = $slug === 'seelen-anhaenger' ? 33 : 32;
        $logoSize = match ($slug) {
            'seelen-anhaenger' => 80,
            'weizenglas-personalisiert' => 95,
            default => 90,
        };

        $textContent = "Frohe Weihnachten!\n- Familie Mustermann -";

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
                    'x' => 50,
                    'y' => $textY,
                    'size' => $textSize,
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => 'vectors/Feiertage/weihnachtsbaum.svg',
                    'url' => '/storage/produkte/configurator/vectors/Feiertage/weihnachtsbaum.svg',
                    'x' => 50,
                    'y' => $logoY,
                    'size' => $logoSize,
                    'rotation' => 0,
                ],
            ],
            'logo_path' => 'vectors/Feiertage/weihnachtsbaum.svg',
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => [],
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
        $textSize = $slug === 'seelen-anhaenger' ? 1.6 : 1.7;
        $logoY = $slug === 'seelen-anhaenger' ? 33 : 32;
        $logoSize = match ($slug) {
            'seelen-anhaenger' => 80,
            'weizenglas-personalisiert' => 95,
            default => 90,
        };

        $textContent = 'Frohes Osterfest!';

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
                    'x' => 50,
                    'y' => 58,
                    'size' => $textSize,
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => 'vectors/Tiere/haeschen.svg',
                    'url' => '/storage/produkte/configurator/vectors/Tiere/haeschen.svg',
                    'x' => 50,
                    'y' => $logoY,
                    'size' => $logoSize,
                    'rotation' => 0,
                ],
            ],
            'logo_path' => 'vectors/Tiere/haeschen.svg',
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => [],
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
        $textSize = $slug === 'seelen-anhaenger' ? 1.5 : 1.6;
        $logoY = $slug === 'seelen-anhaenger' ? 33 : 32;
        $logoSize = match ($slug) {
            'seelen-anhaenger' => 80,
            'weizenglas-personalisiert' => 95,
            default => 90,
        };

        $textContent = "Lieblingsmensch\n24.08.2026";

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
                    'x' => 50,
                    'y' => 58,
                    'size' => $textSize,
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => 'vectors/Liebe/eheringe.svg',
                    'url' => '/storage/produkte/configurator/vectors/Liebe/eheringe.svg',
                    'x' => 50,
                    'y' => $logoY,
                    'size' => $logoSize,
                    'rotation' => 0,
                ],
            ],
            'logo_path' => 'vectors/Liebe/eheringe.svg',
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => [],
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
        $textSize = $slug === 'seelen-anhaenger' ? 1.3 : 1.4;
        $logoY = $slug === 'seelen-anhaenger' ? 33 : 32;
        $logoSize = match ($slug) {
            'seelen-anhaenger' => 78,
            'weizenglas-personalisiert' => 90,
            default => 85,
        };

        $textContent = 'Happy Birthday!';

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
                    'x' => 50,
                    'y' => 58,
                    'size' => $textSize,
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => 'vectors/Feiertage/geschenk.svg',
                    'url' => '/storage/produkte/configurator/vectors/Feiertage/geschenk.svg',
                    'x' => 50,
                    'y' => $logoY,
                    'size' => $logoSize,
                    'rotation' => 0,
                ],
            ],
            'logo_path' => 'vectors/Feiertage/geschenk.svg',
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => [],
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
        $textSize = $slug === 'seelen-anhaenger' ? 1.45 : 1.55;
        $logoY = $slug === 'seelen-anhaenger' ? 33 : 32;
        $logoSize = match ($slug) {
            'seelen-anhaenger' => 80,
            'weizenglas-personalisiert' => 92,
            default => 88,
        };

        $textContent = "Für immer Dein\n- 14. Februar -";

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
                    'x' => 50,
                    'y' => 58,
                    'size' => $textSize,
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => 'vectors/Liebe/doppelherzen.svg',
                    'url' => '/storage/produkte/configurator/vectors/Liebe/doppelherzen.svg',
                    'x' => 50,
                    'y' => $logoY,
                    'size' => $logoSize,
                    'rotation' => 0,
                ],
            ],
            'logo_path' => 'vectors/Liebe/doppelherzen.svg',
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => [],
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
        $textSize = $slug === 'seelen-anhaenger' ? 1.35 : 1.45;
        $logoY = $slug === 'seelen-anhaenger' ? 33 : 32;
        $logoSize = match ($slug) {
            'seelen-anhaenger' => 80,
            'weizenglas-personalisiert' => 92,
            default => 88,
        };

        $subline = $slug === 'weizenglas-personalisiert' ? '- Hexengebräu -' : '- Seelenzauber -';
        $textContent = "Magische Nacht\n{$subline}";

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
                    'x' => 50,
                    'y' => 58,
                    'size' => $textSize,
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => 'vectors/Symbole/mond_und_sterne.svg',
                    'url' => '/storage/produkte/configurator/vectors/Symbole/mond_und_sterne.svg',
                    'x' => 50,
                    'y' => $logoY,
                    'size' => $logoSize,
                    'rotation' => 0,
                ],
            ],
            'logo_path' => 'vectors/Symbole/mond_und_sterne.svg',
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => [],
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
        $textSize = $slug === 'seelen-anhaenger' ? 1.45 : 1.55;
        $logoY = $slug === 'seelen-anhaenger' ? 33 : 32;
        $logoSize = match ($slug) {
            'seelen-anhaenger' => 80,
            'weizenglas-personalisiert' => 92,
            default => 88,
        };

        $textContent = "Beste Mama\nder Welt";

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
                    'x' => 50,
                    'y' => 58,
                    'size' => $textSize,
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => 'vectors/Ornamente/herzkranz.svg',
                    'url' => '/storage/produkte/configurator/vectors/Ornamente/herzkranz.svg',
                    'x' => 50,
                    'y' => $logoY,
                    'size' => $logoSize,
                    'rotation' => 0,
                ],
            ],
            'logo_path' => 'vectors/Ornamente/herzkranz.svg',
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => [],
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
        $textSize = $slug === 'seelen-anhaenger' ? 1.35 : 1.45;
        $logoY = $slug === 'seelen-anhaenger' ? 33 : 32;
        $logoSize = match ($slug) {
            'seelen-anhaenger' => 78,
            'weizenglas-personalisiert' => 92,
            default => 86,
        };

        $vectorRel = $slug === 'weizenglas-personalisiert'
            ? 'vectors/Symbole/bierkrug.svg'
            : 'vectors/Symbole/siegerpokal.svg';

        $textContent = "Bester Papa\nder Welt!";

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
                    'x' => 50,
                    'y' => 58,
                    'size' => $textSize,
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => Str::uuid()->toString(),
                    'type' => 'vector',
                    'value' => $vectorRel,
                    'url' => '/storage/produkte/configurator/' . $vectorRel,
                    'x' => 50,
                    'y' => $logoY,
                    'size' => $logoSize,
                    'rotation' => 0,
                ],
            ],
            'logo_path' => $vectorRel,
            'is_digital' => false,
            'is_express' => false,
            'texts_back' => [],
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
