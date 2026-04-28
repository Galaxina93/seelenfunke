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
        ProductTemplate::truncate(); // Alte Vorlagen löschen (falls erwünscht beim Seed)

        $jsonPath = database_path('seeders/data/product_templates.json');

        if (\Illuminate\Support\Facades\File::exists($jsonPath)) {
            $this->seedFromJson($jsonPath);
            return;
        }

        // --- FALLBACK LOGIK (Falls es noch keinen Export gab) ---
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
        }
    }

    private function seedFromJson($jsonPath)
    {
        $json = \Illuminate\Support\Facades\File::get($jsonPath);
        $templates = json_decode($json, true);

        if (!$templates) return;

        foreach ($templates as $data) {
            $product = Product::where('slug', $data['product_slug'])->first();

            if ($product) {
                ProductTemplate::create([
                    'product_id' => $product->id,
                    'name' => $data['name'],
                    'configuration' => $data['configuration'],
                    'is_active' => $data['is_active'],
                    'holiday' => $data['holiday'] ?? null,
                    'preview_image' => $data['preview_image'] ?? null,
                ]);
            }
        }
    }

    private function createChristmasTemplate(Product $product)
    {
        $config = [
            'texts' => [
                [
                    'id' => Str::uuid()->toString(),
                    'text' => "Frohe Weihnachten!\n- Familie Mustermann -",
                    'font' => 'Dancing Script',
                    'align' => 'center',
                    'x' => 50,
                    'y' => 60,
                    'size' => 1.5,
                    'rotation' => 0
                ]
            ],
            'logos' => [],
            'texts_back' => [],
            'logos_back' => [],
            'notes' => '',
            'uploaded_files' => []
        ];

        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Frohe Weihnachten',
            'configuration' => $config,
            'is_active' => true,
            'holiday' => 'Weihnachten'
        ]);
    }

    private function createEasterTemplate(Product $product)
    {
        $config = [
            'texts' => [
                [
                    'id' => Str::uuid()->toString(),
                    'text' => 'Frohes Osterfest!',
                    'font' => 'Pacifico',
                    'align' => 'center',
                    'x' => 50,
                    'y' => 50,
                    'size' => 1.8,
                    'rotation' => 0
                ]
            ],
            'logos' => [],
            'texts_back' => [],
            'logos_back' => [],
            'notes' => '',
            'uploaded_files' => []
        ];

        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Frohes Osterfest',
            'configuration' => $config,
            'is_active' => true,
            'holiday' => 'Ostern'
        ]);
    }

    private function createWeddingTemplate(Product $product)
    {
        $config = [
            'texts' => [
                [
                    'id' => Str::uuid()->toString(),
                    'text' => "Lieblingsmensch\n24.08.2026",
                    'font' => 'Great Vibes',
                    'align' => 'center',
                    'x' => 50,
                    'y' => 50,
                    'size' => 1.6,
                    'rotation' => 0
                ]
            ],
            'logos' => [],
            'texts_back' => [],
            'logos_back' => [],
            'notes' => '',
            'uploaded_files' => []
        ];

        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Lieblingsmensch / Hochzeit',
            'configuration' => $config,
            'is_active' => true,
            'holiday' => 'Hochzeit'
        ]);
    }

    private function createBirthdayTemplate(Product $product)
    {
        $config = [
            'texts' => [
                [
                    'id' => Str::uuid()->toString(),
                    'text' => "Happy Birthday!",
                    'font' => 'Montserrat',
                    'align' => 'center',
                    'x' => 50,
                    'y' => 50,
                    'size' => 1.4,
                    'rotation' => 0
                ]
            ],
            'logos' => [],
            'texts_back' => [],
            'logos_back' => [],
            'notes' => '',
            'uploaded_files' => []
        ];

        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Alles Gute zum Geburtstag',
            'configuration' => $config,
            'is_active' => true,
            'holiday' => 'Kein Feiertag'
        ]);
    }
}
