<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Blog\BlogCategory;
use Illuminate\Support\Str;

class BlogCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // Emotionale Kern-Themen
            'Inspiration & Achtsamkeit',

            // Vertrauensaufbau & Marke
            'Hinter den Kulissen',

            // Verkaufsfördernde Anlässe (Top für SEO)
            'Geschenkideen & Anlässe',
            'Hochzeit & Liebe',
            'Geburt & Taufe',

            // Community & Mehrwert
            'DIY & Kreatives',
            'Kunden-Geschichten',

            // Produktbezogen
            'Neuheiten & Kollektionen',
            'Wohnen & Dekorieren',
            'Wissen & Materialkunde'
        ];

        foreach ($categories as $categoryName) {
            // Wir nutzen firstOrCreate, um Duplikate zu vermeiden,
            // falls der Seeder mehrmals läuft.
            BlogCategory::firstOrCreate(
                ['slug' => Str::slug($categoryName)], // Suchkriterium
                ['name' => $categoryName]             // Werte zum Setzen falls neu
            );
        }
    }
}
