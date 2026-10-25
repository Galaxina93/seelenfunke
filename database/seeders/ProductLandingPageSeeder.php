<?php

namespace Database\Seeders;

use App\Models\Marketing\MarketingLandingPage;
use App\Models\Product\Product;
use Illuminate\Database\Seeder;

class ProductLandingPageSeeder extends Seeder
{
    /**
     * Synchronisiert für alle Produkte im System automatisch die passende,
     * produktindividuelle Landing Page (nutzt das zentrale dynamische Master-Template).
     */
    public function run(): void
    {
        $this->command?->info('Synchronisiere dynamische Produkt-Landing-Pages...');

        $products = Product::all()->filter(fn(Product $p) => !str_starts_with((string) $p->slug, 'draft-'));

        if ($products->isEmpty()) {
            $this->command?->info('Keine aktiven Produkte gefunden.');
            return;
        }

        foreach ($products as $product) {
            try {
                MarketingLandingPage::syncForProduct($product, true);
            } catch (\Throwable $e) {
                $this->command?->error("Fehler bei Produkt {$product->name}: " . $e->getMessage());
            }
        }

        $this->command?->info('Landing-Pages erfolgreich synchronisiert (' . $products->count() . ' Produkte).');
    }
}
