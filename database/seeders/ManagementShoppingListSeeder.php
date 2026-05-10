<?php

namespace Database\Seeders;

use App\Models\Management\ManagementShoppingCategory;
use App\Models\Management\ManagementShoppingItem;
use Illuminate\Database\Seeder;

class ManagementShoppingListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Kategorien erstellen
        $haushalt = ManagementShoppingCategory::firstOrCreate(
            ['name' => 'Haushalt'],
            ['icon' => 'home', 'sort_order' => 1]
        );

        $lebensmittel = ManagementShoppingCategory::firstOrCreate(
            ['name' => 'Lebensmittel'],
            ['icon' => 'shopping-bag', 'sort_order' => 2]
        );

        // Haushalts-Produkte
        $haushaltItems = [
            'Deoroller',
            'Klopapier',
            'Desinfektionsmittel fürs Ohr',
            'Reinigungstücher Desinfektion',
            'Geschirr Bürsten',
            'Putzlappen',
            'Labellos',
            'Küchentücher',
            'Gesichtscreme',
            'Zahnpasta',
            'Ohrstäbchen',
            'Vitamin D',
            'Zahnseide',
            'Putztücher von Sagrotan',
            'Rasierschaum',
            'Seife',
            'Baby Feuchttücher ohne extra Stoffe' // Aus dem Screenshot "Einkaufsliste" übernommen, passt hier besser
        ];

        foreach ($haushaltItems as $item) {
            ManagementShoppingItem::firstOrCreate([
                'name' => $item,
                'category_id' => $haushalt->id
            ], [
                'status' => 'stocked'
            ]);
        }

        // Lebensmittel-Produkte (Einkaufsliste Screenshot)
        $lebensmittelItems = [
            'Heringsfilet',
            'Salami',
            'Bananen',
            'Milch',
            'Gefrorene Erdbeeren',
            'Brotaufstrich',
            'Nutella',
            'Wurst',
            'Erdnussbutter',
            'Toastscheiben'
        ];

        foreach ($lebensmittelItems as $item) {
            ManagementShoppingItem::firstOrCreate([
                'name' => $item,
                'category_id' => $lebensmittel->id
            ], [
                'status' => 'stocked'
            ]);
        }
    }
}
