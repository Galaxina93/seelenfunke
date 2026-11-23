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
     * Berechnet maßstabsgerechte 2D-Koordinaten, Textgrößen und Symbolgrößen anhand der realen
     * physischen Rohlingsmaße (fontSizeMm = text_size * (4 * blankWidthMm / plateBoxWidthPct),
     * logoWidthMm = logo_size * (blankWidthMm / (5 * plateBoxWidthPct))).
     */
    private function getLayoutMetrics(string $slug, string $holiday = 'default'): array
    {
        return match ($slug) {
            'seelen-kristall' => [
                // K9 Kristall Ø 161,5 mm (plateW = 82 %, Kreis X=16..84, Y=11.6..79.6)
                'logo_x' => 50.0,
                'logo_y' => 30.5,
                'logo_size' => match ($holiday) {
                    'hochzeit', 'valentinstag', 'muttertag' => 98,
                    'weihnachten', 'geburtstag'             => 94,
                    default                                 => 90,
                },
                'text_x' => 50.0,
                'text_y' => 56.5,
                'text_size' => match ($holiday) {
                    'weihnachten'  => 1.32,
                    'ostern'       => 1.38,
                    'hochzeit'     => 1.44,
                    'geburtstag'   => 1.30,
                    'valentinstag' => 1.46,
                    'halloween'    => 1.28,
                    'muttertag'    => 1.48,
                    'vatertag'     => 1.30,
                    default        => 1.34,
                },
            ],
            'seelen-anhaenger' => [
                // Alu-Herz 45 x 40 mm (plateW = 48.25 %, 1.0 = 3,73 mm; Bezier-Herz X=28.07..71.93, Y=54.05..94.43, Kerbe Y=59.47)
                'logo_x' => 50.0,
                'logo_y' => 66.0,
                'logo_size' => match ($holiday) {
                    'hochzeit', 'valentinstag', 'muttertag' => 62,
                    'weihnachten', 'ostern'                 => 58,
                    default                                 => 56,
                },
                'text_x' => 50.0,
                'text_y' => 77.5,
                'text_size' => match ($holiday) {
                    'weihnachten'  => 1.10,
                    'ostern'       => 1.20,
                    'hochzeit'     => 1.24,
                    'geburtstag'   => 1.12,
                    'valentinstag' => 1.26,
                    'halloween'    => 1.08,
                    'muttertag'    => 1.28,
                    'vatertag'     => 1.12,
                    default        => 1.16,
                },
            ],
            'weizenglas-personalisiert' => [
                'logo_x' => 50.0,
                'logo_y' => 34.0,
                'logo_size' => 76,
                'text_x' => 50.0,
                'text_y' => 55.0,
                'text_size' => match ($holiday) {
                    'weihnachten'  => 1.10,
                    'ostern'       => 1.15,
                    'hochzeit'     => 1.20,
                    'geburtstag'   => 1.08,
                    'valentinstag' => 1.20,
                    'halloween'    => 1.05,
                    'muttertag'    => 1.22,
                    'vatertag'     => 1.08,
                    default        => 1.12,
                },
            ],
            'seelen-talisman' => [
                // Dog-Tag Plakette 28 x 50 mm (plateW = 32.25 %, 1.0 = 3,47 mm; X=34.25..65.25, Y=46.84..94.24)
                'logo_x' => 50.0,
                'logo_y' => 56.5,
                'logo_size' => match ($holiday) {
                    'hochzeit', 'valentinstag', 'muttertag' => 62,
                    'weihnachten', 'ostern'                 => 58,
                    default                                 => 56,
                },
                'text_x' => 50.0,
                'text_y' => 74.5,
                'text_size' => match ($holiday) {
                    'weihnachten'  => 1.10,
                    'ostern'       => 1.22,
                    'hochzeit'     => 1.25,
                    'geburtstag'   => 1.14,
                    'valentinstag' => 1.26,
                    'halloween'    => 0.96,
                    'muttertag'    => 1.30,
                    'vatertag'     => 1.14,
                    default        => 1.18,
                },
            ],
            'seelen-pfote-hundemarke' => [
                // Knochen 40 x 22 mm (plateW = 80 %, 1.0 = 2,00 mm; Symbol links im Knochenkopf X=23.5, Text im Steg X=48.5, Y=39.2..63.3)
                'logo_x' => 23.5,
                'logo_y' => 50.0,
                'logo_size' => match ($holiday) {
                    'hochzeit', 'valentinstag', 'muttertag' => 64,
                    'weihnachten', 'ostern'                 => 60,
                    default                                 => 58,
                },
                'text_x' => 48.5,
                'text_y' => 51.0,
                'text_size' => match ($holiday) {
                    'weihnachten'  => 1.90,
                    'ostern'       => 1.85,
                    'hochzeit'     => 2.05,
                    'geburtstag'   => 2.15,
                    'valentinstag' => 2.00,
                    'halloween'    => 1.80,
                    'muttertag'    => 1.95,
                    'vatertag'     => 1.85,
                    default        => 1.95,
                },
            ],
            'goldenes-seelen-herz',
            'echtgold-seelen-herz' => [
                // Gold-Herz 25 x 22 mm (plateW = 65 %, 1.0 = 1,54 mm; Kerbe Y=40.63, Spitze Y=91.97)
                'logo_x' => 50.0,
                'logo_y' => 50.5,
                'logo_size' => match ($holiday) {
                    'hochzeit', 'valentinstag', 'muttertag' => 84,
                    'weihnachten', 'ostern'                 => 78,
                    default                                 => 76,
                },
                'text_x' => 50.0,
                'text_y' => 67.0,
                'text_size' => match ($holiday) {
                    'weihnachten'  => 2.25,
                    'ostern'       => 2.25,
                    'hochzeit'     => 2.50,
                    'geburtstag'   => 2.18,
                    'valentinstag' => 2.45,
                    'halloween'    => 2.15,
                    'muttertag'    => 2.35,
                    'vatertag'     => 2.20,
                    default        => 2.25,
                },
            ],
            'id-armband-silber-herz',
            'id-armband-gold-herz' => [
                // FBM ID-Armband 24 x 5,5 mm (4,5 mm Plattenhöhe, plateW = 66 %, 1.0 = 1,45 mm; Gravurzone X=35.1..78.6, Y=43.85..56.15)
                'logo_x' => 41.5,
                'logo_y' => 50.0,
                'logo_size' => match ($holiday) {
                    'hochzeit', 'valentinstag', 'muttertag' => 48,
                    default                                 => 46,
                },
                'text_x' => 62.5,
                'text_y' => 50.0,
                'text_size' => match ($holiday) {
                    'weihnachten'  => 2.25,
                    'ostern'       => 2.22,
                    'hochzeit'     => 2.20,
                    'geburtstag'   => 2.22,
                    'valentinstag' => 2.25,
                    'halloween'    => 2.18,
                    'muttertag'    => 2.30,
                    'vatertag'     => 2.25,
                    default        => 2.22,
                },
            ],
            default => [
                'logo_x' => 50.0,
                'logo_y' => 34.0,
                'logo_size' => 84,
                'text_x' => 50.0,
                'text_y' => 56.0,
                'text_size' => 1.20,
            ],
        };
    }

    /**
     * Erzeugt optionale, maßstabsgerechte Rückseiten-Texte für beidseitig gravierbare Schmuckstücke.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildBackTexts(string $slug, string $holiday, string $font): array
    {
        return match ($slug) {
            'id-armband-silber-herz',
            'id-armband-gold-herz' => [[
                'id' => Str::uuid()->toString(),
                'text' => match ($holiday) {
                    'weihnachten'  => '24.12.2026',
                    'ostern'       => 'Frohe Ostern',
                    'hochzeit'     => '24.08.2026',
                    'geburtstag'   => '18.11.2026',
                    'valentinstag' => '14.02.2027',
                    'halloween'    => 'Seelenzauber',
                    'muttertag'    => 'Danke Mama',
                    'vatertag'     => 'Danke Papa',
                    default        => 'Für immer',
                },
                'font' => $font,
                'align' => 'center',
                'x' => 42.5,
                'y' => 50.0,
                'size' => 2.18,
                'rotation' => 0,
            ]],
            'seelen-pfote-hundemarke' => [[
                'id' => Str::uuid()->toString(),
                'text' => match ($holiday) {
                    'geburtstag' => "0151 / 12345678\nIch bin gechipt",
                    default      => "0151 / 12345678\nRuf mich an",
                },
                'font' => 'Montserrat',
                'align' => 'center',
                'x' => 58.0,
                'y' => 51.0,
                'size' => 1.65,
                'rotation' => 0,
            ]],
            'goldenes-seelen-herz',
            'echtgold-seelen-herz' => [[
                'id' => Str::uuid()->toString(),
                'text' => match ($holiday) {
                    'weihnachten'  => '24.12.2026',
                    'hochzeit'     => '24.08.2026',
                    'valentinstag' => '14. Februar',
                    'geburtstag'   => 'Alles Liebe',
                    'muttertag'    => 'Von Herzen',
                    'vatertag'     => 'Für immer',
                    default        => 'Dein Unikat',
                },
                'font' => $font,
                'align' => 'center',
                'x' => 50.0,
                'y' => 59.5,
                'size' => 2.18,
                'rotation' => 0,
            ]],
            default => [],
        };
    }

    private function createTemplateRecord(
        Product $product,
        string $name,
        string $holidayLabel,
        string $holidayKey,
        string $theme,
        string $font,
        string $vectorRel,
        string $textContent
    ): void {
        $slug = (string) $product->slug;
        $m = $this->getLayoutMetrics($slug, $holidayKey);
        $backTexts = $this->buildBackTexts($slug, $holidayKey, $font);

        $config = [
            'text' => $textContent,
            'type' => 'physical',
            'background_theme' => $theme,
            'files' => [],
            'texts' => [
                [
                    'id' => Str::uuid()->toString(),
                    'text' => $textContent,
                    'font' => $font,
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
            'texts_back' => $backTexts,
            'logos_back' => [],
            'notes' => '',
            'uploaded_files' => [],
        ];

        $templateManagedSlugs = [
            'seelen-kristall',
            'goldenes-seelen-herz',
            'seelen-anhaenger',
            'seelen-pfote-hundemarke',
            'seelen-talisman',
        ];
        $candidatePreview = "produkte/product-templates/{$slug}-{$holidayKey}.jpg";
        $previewImage = in_array($slug, $templateManagedSlugs, true)
            && \Illuminate\Support\Facades\Storage::disk('public')->exists($candidatePreview)
            ? $candidatePreview
            : null;

        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => $name,
            'configuration' => $config,
            'is_active' => true,
            'is_ai_generated' => false,
            'holiday' => $holidayLabel,
            'preview_image' => $previewImage,
        ]);
    }

    private function createChristmasTemplate(Product $product): void
    {
        $slug = (string) $product->slug;
        $textContent = match ($slug) {
            'seelen-pfote-hundemarke' => "BALU\nLiebling",
            'id-armband-silber-herz',
            'id-armband-gold-herz'    => 'LENA',
            'goldenes-seelen-herz',
            'echtgold-seelen-herz'    => "Frohes\nFest",
            'seelen-talisman'         => "Frohes\nFest!",
            'seelen-anhaenger'        => "Frohes Fest\nIn Liebe",
            default                   => "Frohe Weihnachten!\nFamilie Mustermann",
        };

        $this->createTemplateRecord(
            $product,
            'Frohe Weihnachten',
            'Weihnachten',
            'weihnachten',
            'winter',
            'Dancing Script',
            'vectors/Feiertage/weihnachtsbaum.svg',
            $textContent
        );
    }

    private function createEasterTemplate(Product $product): void
    {
        $slug = (string) $product->slug;
        $textContent = match ($slug) {
            'seelen-pfote-hundemarke' => "MILO\nSpürhund",
            'id-armband-silber-herz',
            'id-armband-gold-herz'    => 'CLARA',
            'goldenes-seelen-herz',
            'echtgold-seelen-herz'    => "Frohe\nOstern",
            default                   => "Frohes\nOsterfest!",
        };

        $this->createTemplateRecord(
            $product,
            'Frohes Osterfest',
            'Ostern',
            'ostern',
            'ostern',
            'Pacifico',
            'vectors/Tiere/haeschen.svg',
            $textContent
        );
    }

    private function createWeddingTemplate(Product $product): void
    {
        $slug = (string) $product->slug;
        $textContent = match ($slug) {
            'seelen-pfote-hundemarke' => "BELLA\nSeelenhund",
            'id-armband-silber-herz',
            'id-armband-gold-herz'    => 'EWIG DEIN',
            'goldenes-seelen-herz',
            'echtgold-seelen-herz'    => 'L & M',
            'seelen-talisman'         => "Für immer\n24.08.2026",
            default                   => "Lieblingsmensch\n24.08.2026",
        };

        $this->createTemplateRecord(
            $product,
            'Lieblingsmensch / Hochzeit',
            'Hochzeit',
            'hochzeit',
            'hochzeit',
            'Great Vibes',
            'vectors/Liebe/eheringe.svg',
            $textContent
        );
    }

    private function createBirthdayTemplate(Product $product): void
    {
        $slug = (string) $product->slug;
        $textContent = match ($slug) {
            'seelen-pfote-hundemarke' => 'BALU',
            'id-armband-silber-herz',
            'id-armband-gold-herz'    => 'SOFIA',
            'goldenes-seelen-herz',
            'echtgold-seelen-herz'    => "Happy\nBirthday",
            default                   => "Happy\nBirthday!",
        };

        $this->createTemplateRecord(
            $product,
            'Alles Gute zum Geburtstag',
            'Geburtstag',
            'geburtstag',
            'geburtstag',
            'Montserrat',
            'vectors/Feiertage/geschenk.svg',
            $textContent
        );
    }

    private function createValentinesTemplate(Product $product): void
    {
        $slug = (string) $product->slug;
        $textContent = match ($slug) {
            'seelen-pfote-hundemarke' => "MILO\nLiebling",
            'id-armband-silber-herz',
            'id-armband-gold-herz'    => 'LIEBE',
            'goldenes-seelen-herz',
            'echtgold-seelen-herz'    => "Für\nDich",
            'seelen-talisman'         => "Für immer\nDein",
            default                   => "Für immer Dein\n14. Februar",
        };

        $this->createTemplateRecord(
            $product,
            'Für immer Dein / Valentinstag',
            'Valentinstag',
            'valentinstag',
            'hochzeit',
            'Great Vibes',
            'vectors/Liebe/doppelherzen.svg',
            $textContent
        );
    }

    private function createHalloweenTemplate(Product $product): void
    {
        $slug = (string) $product->slug;
        $textContent = match ($slug) {
            'seelen-pfote-hundemarke'   => "LUNA\nWächter",
            'id-armband-silber-herz',
            'id-armband-gold-herz'      => 'LUNA',
            'goldenes-seelen-herz',
            'echtgold-seelen-herz'      => "Magie\nNacht",
            'seelen-talisman'           => "Happy\nHalloween!",
            'weizenglas-personalisiert' => "Magische Nacht\nHexengebräu",
            default                     => "Magische Nacht\nSeelenzauber",
        };

        $this->createTemplateRecord(
            $product,
            $slug === 'seelen-talisman' ? 'Happy Halloween' : 'Magische Nacht / Halloween',
            'Halloween',
            'halloween',
            'herbst',
            'Cinzel',
            'vectors/Symbole/mond_und_sterne.svg',
            $textContent
        );
    }

    private function createMothersDayTemplate(Product $product): void
    {
        $slug = (string) $product->slug;
        $textContent = match ($slug) {
            'seelen-pfote-hundemarke' => "BELLA\nLiebling",
            'id-armband-silber-herz',
            'id-armband-gold-herz'    => 'MAMA',
            'goldenes-seelen-herz',
            'echtgold-seelen-herz'    => "Beste\nMama",
            default                   => "Beste Mama\nder Welt",
        };

        $this->createTemplateRecord(
            $product,
            'Beste Mama der Welt / Muttertag',
            'Muttertag',
            'muttertag',
            'hochzeit',
            'Dancing Script',
            'vectors/Ornamente/herzkranz.svg',
            $textContent
        );
    }

    private function createFathersDayTemplate(Product $product): void
    {
        $slug = (string) $product->slug;
        $vectorRel = $slug === 'weizenglas-personalisiert'
            ? 'vectors/Symbole/bierkrug.svg'
            : 'vectors/Symbole/siegerpokal.svg';

        $textContent = match ($slug) {
            'seelen-pfote-hundemarke' => "BALU\nKumpel",
            'id-armband-silber-herz',
            'id-armband-gold-herz'    => 'PAPA',
            'goldenes-seelen-herz',
            'echtgold-seelen-herz'    => "Bester\nPapa",
            default                   => "Bester Papa\nder Welt!",
        };

        $this->createTemplateRecord(
            $product,
            'Bester Papa / Vatertag',
            'Vatertag',
            'vatertag',
            'geburtstag',
            'Montserrat',
            $vectorRel,
            $textContent
        );
    }
}
