<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product\Product;
use App\Models\Marketing\MarketingLandingPage;
use Illuminate\Support\Str;

class ProductLandingPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Starte lokalen Landing-Page Seeder...');

        $targetNames = [
            'Der Seelen Kristall', 
            'Der Seelenanhänger', 
            'Der Feierabend-Funke'
        ];

        // Finde Zielprodukte
        $products = Product::whereIn('name', $targetNames)->get();

        if ($products->isEmpty()) {
            $this->command->info('Keine der Zielprodukte gefunden.');
            return;
        }

        $this->command->info('Gefundene Zielprodukte: ' . $products->count());

        $bar = $this->command->getOutput()->createProgressBar($products->count());
        $bar->start();

        foreach ($products as $product) {
            try {
                // Generiere Datenbank Eintrag (aktualisiere falls vorhanden)
                $slug = $product->slug;
                
                // Ermittle dynamischen CTA Text
                $ctaText = 'Jetzt zum Produkt';
                if ($product->isPersonalizable()) {
                    $ctaText = 'Jetzt dein Unikat gestalten';
                } elseif ($product->type === 'digital') {
                    $ctaText = 'Jetzt digitalen Download ansehen';
                } elseif ($product->type === 'service') {
                    $ctaText = 'Jetzt Dienstleistung buchen';
                }

                $landingPage = MarketingLandingPage::updateOrCreate(
                    ['product_id' => $product->id],
                    [
                        'slug' => $slug,
                        'title' => $product->name,
                        'headline' => 'Entdecke die Magie von ' . $product->name,
                        'sales_copy' => $product->description,
                        'cta_text' => $ctaText,
                        'status' => 'active',
                    ]
                );

                // Generiere Local Blade File vom Template Stub
                $bladePath = resource_path('views/frontend/pages/landingpages/' . $slug . '.blade.php');
                $stubPath = resource_path('views/stubs/landing-page-template.stub');
                
                if (\Illuminate\Support\Facades\File::exists($stubPath)) {
                    $content = \Illuminate\Support\Facades\File::get($stubPath);
                    
                    if (!\Illuminate\Support\Facades\File::exists(dirname($bladePath))) {
                        \Illuminate\Support\Facades\File::makeDirectory(dirname($bladePath), 0755, true);
                    }
                    \Illuminate\Support\Facades\File::put($bladePath, $content);
                } else {
                    $this->command->error("\nStub nicht gefunden: " . $stubPath);
                }

                $bar->advance();

            } catch (\Exception $e) {
                $this->command->error("\nFehler bei Produkt {$product->name}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->command->info("\nSeeden erfolgreich abgeschlossen!");
    }
}
