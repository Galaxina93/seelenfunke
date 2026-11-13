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
     * Berechnet optimale 2D-Koordinaten, Textgrößen & Umbrüche exakt abgestimmt auf die jeweilige
     * Produktgeometrie und Clipping-Kontur (Kristall, Alu-Herz, Weizenglas, Dog-Tag Talisman, Hundeknochen, 14k Gold-Herz).
     */
    private function getLayoutMetrics(string $slug, string $holiday = 'default'): array
    {
        return match ($slug) {
            'seelen-kristall' => [
                'logo_x' => 50, 'logo_y' => 31, 'logo_size' => 80,
                'text_x' => 50, 'text_y' => 55.5,
                'text_size' => match ($holiday) {
                    'weihnachten'  => 1.25,
                    'ostern'       => 1.30,
                    'hochzeit'     => 1.35,
                    'geburtstag'   => 1.20,
                    'valentinstag' => 1.35,
                    'halloween'    => 1.15,
                    'muttertag'    => 1.38,
                    'vatertag'     => 1.20,
                    default        => 1.25,
                },
                'back_texts' => [],
            ],
            'seelen-anhaenger' => [
                // Herz-Schlüsselanhänger (Herzfläche X: 28.14..71.86, Y: 54.19..94.52, Kerbe oben Y=59.45)
                'logo_x' => 50, 'logo_y' => 65, 'logo_size' => 36,
                'text_x' => 50, 'text_y' => 75.5,
                'text_size' => match ($holiday) {
                    'weihnachten'  => 0.65,
                    'ostern'       => 0.72,
                    'hochzeit'     => 0.74,
                    'geburtstag'   => 0.66,
                    'valentinstag' => 0.74,
                    'halloween'    => 0.60,
                    'muttertag'    => 0.76,
                    'vatertag'     => 0.66,
                    default        => 0.70,
                },
                'back_texts' => [],
            ],
            'weizenglas-personalisiert' => [
                'logo_x' => 50, 'logo_y' => 34, 'logo_size' => 74,
                'text_x' => 50, 'text_y' => 55,
                'text_size' => match ($holiday) {
                    'weihnachten'  => 1.05,
                    'ostern'       => 1.10,
                    'hochzeit'     => 1.15,
                    'geburtstag'   => 1.00,
                    'valentinstag' => 1.15,
                    'halloween'    => 0.96,
                    'muttertag'    => 1.18,
                    'vatertag'     => 1.00,
                    default        => 1.05,
                },
                'back_texts' => [],
            ],
            'seelen-talisman' => [
                // Schmale Hochformat-Plakette (X: 35.2..64.8 => 29.6% Gesamtbreite, Y: 46.8..93.8)
                'logo_x' => 50, 'logo_y' => 56, 'logo_size' => 46,
                'text_x' => 50, 'text_y' => 73,
                'text_size' => match ($holiday) {
                    'weihnachten'  => 0.62,
                    'ostern'       => 0.74,
                    'hochzeit'     => 0.74,
                    'geburtstag'   => 0.76,
                    'valentinstag' => 0.76,
                    'halloween'    => 0.58,
                    'muttertag'    => 0.82,
                    'vatertag'     => 0.68,
                    default        => 0.72,
                },
                'back_texts' => [],
            ],
            'seelen-pfote-hundemarke' => [
                // Knochenform mit Pfoten-Durchbruch rechts (nutzbare Breite links/mitte X: 18..60 => Zentrum X=41, Mittelsteg Y: 37.5..62.5)
                'logo_x' => 41, 'logo_y' => 43, 'logo_size' => 32,
                'text_x' => 41, 'text_y' => 53.5,
                'text_size' => match ($holiday) {
                    'weihnachten'  => 0.72,
                    'ostern'       => 0.65,
                    'hochzeit'     => 0.78,
                    'geburtstag'   => 0.64,
                    'valentinstag' => 0.72,
                    'halloween'    => 0.62,
                    'muttertag'    => 0.72,
                    'vatertag'     => 0.64,
                    default        => 0.68,
                },
                'back_texts' => [],
            ],
            'goldenes-seelen-herz',
            'echtgold-seelen-herz' => [
                // 14k / 585er Gold-Herz (Kerbe oben Y=42.2, Spitze unten Y=90.5, sichere Innenbreite bei Y=61.5 ca. 40%)
                'logo_x' => 50, 'logo_y' => 49, 'logo_size' => 42,
                'text_x' => 50, 'text_y' => 61.5,
                'text_size' => match ($holiday) {
                    'weihnachten'  => 0.80,
                    'ostern'       => 0.86,
                    'hochzeit'     => 0.88,
                    'geburtstag'   => 0.80,
                    'valentinstag' => 0.88,
                    'halloween'    => 0.72,
                    'muttertag'    => 0.92,
                    'vatertag'     => 0.78,
                    default        => 0.82,
                },
                'back_texts' => [],
            ],
            'id-armband-silber-herz',
            'id-armband-gold-herz' => [
                // FBM ID-Armband (Gravurplatte X=20..80, Y=42..58; Herz-Durchbruch links, sichere Gravurzone X=35.5..78.5, Y=44..56)
                'logo_x' => 41, 'logo_y' => 50, 'logo_size' => 24,
                'text_x' => 60, 'text_y' => 50,
                'text_size' => match ($holiday) {
                    'weihnachten'  => 0.54,
                    'ostern'       => 0.62,
                    'hochzeit'     => 0.60,
                    'geburtstag'   => 0.58,
                    'valentinstag' => 0.60,
                    'halloween'    => 0.52,
                    'muttertag'    => 0.62,
                    'vatertag'     => 0.56,
                    default        => 0.58,
                },
                'back_texts' => [],
            ],
            default => [
                'logo_x' => 50, 'logo_y' => 34, 'logo_size' => 76,
                'text_x' => 50, 'text_y' => 56, 'text_size' => 1.10,
                'back_texts' => [],
            ],
        };
    }

    private function createChristmasTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 'weihnachten');
        $textContent = match ($slug) {
            'seelen-pfote-hundemarke' => "BALU\nFrohe Weihnachten",
            'seelen-talisman'         => "Frohe\nWeihnachten!\nFamilie Mustermann",
            'id-armband-silber-herz',
            'id-armband-gold-herz'    => "Frohe Weihnachten\n24.12.2026",
            'goldenes-seelen-herz',
            'echtgold-seelen-herz',
            'seelen-anhaenger',
            'weizenglas-personalisiert' => "Frohe Weihnachten!\nFamilie Mustermann",
            default                   => "Frohe Weihnachten!\n- Familie Mustermann -",
        };

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
            'preview_image' => null,
        ]);
    }

    private function createEasterTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 'ostern');
        $textContent = match ($slug) {
            'seelen-pfote-hundemarke' => "BALU\nOster-Spürhund",
            default                   => "Frohes\nOsterfest!",
        };

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
            'preview_image' => null,
        ]);
    }

    private function createWeddingTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 'hochzeit');
        $textContent = match ($slug) {
            'seelen-pfote-hundemarke' => "BALU\nSeelenhund",
            default                   => "Lieblingsmensch\n24.08.2026",
        };

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
            'preview_image' => null,
        ]);
    }

    private function createBirthdayTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 'geburtstag');
        $textContent = match ($slug) {
            'seelen-pfote-hundemarke' => "BALU\nIch bin gechipt!",
            default                   => "Happy\nBirthday!",
        };

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
            'preview_image' => null,
        ]);
    }

    private function createValentinesTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 'valentinstag');
        $textContent = match ($slug) {
            'seelen-pfote-hundemarke' => "BALU\nLiebling auf 4 Pfoten",
            'id-armband-silber-herz',
            'id-armband-gold-herz'    => "Für immer Dein\n14. Februar",
            default                   => "Für immer Dein\n- 14. Februar -",
        };

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
            'preview_image' => null,
        ]);
    }

    private function createHalloweenTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 'halloween');
        $textContent = match ($slug) {
            'seelen-pfote-hundemarke'   => "BALU\nNachtwächter",
            'seelen-talisman'           => "Magische\nNacht\nSeelenzauber",
            'id-armband-silber-herz',
            'id-armband-gold-herz',
            'goldenes-seelen-herz',
            'echtgold-seelen-herz',
            'seelen-anhaenger'          => "Magische Nacht\nSeelenzauber",
            'weizenglas-personalisiert' => "Magische Nacht\n- Hexengebräu -",
            default                     => "Magische Nacht\n- Seelenzauber -",
        };

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
            'preview_image' => null,
        ]);
    }

    private function createMothersDayTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 'muttertag');
        $textContent = match ($slug) {
            'seelen-pfote-hundemarke' => "BALU\nFrauchens Liebling",
            default                   => "Beste Mama\nder Welt",
        };

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
            'preview_image' => null,
        ]);
    }

    private function createFathersDayTemplate(Product $product): void
    {
        $slug = $product->slug;
        $m = $this->getLayoutMetrics($slug, 'vatertag');
        $vectorRel = $slug === 'weizenglas-personalisiert'
            ? 'vectors/Symbole/bierkrug.svg'
            : 'vectors/Symbole/siegerpokal.svg';

        $textContent = match ($slug) {
            'seelen-pfote-hundemarke' => "BALU\nBester Kumpel",
            default                   => "Bester Papa\nder Welt!",
        };

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
            'preview_image' => null,
        ]);
    }
}
