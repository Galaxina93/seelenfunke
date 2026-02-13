<?php

namespace Database\Seeders;

use App\Models\Category; // We'll create this model next
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Physical
            ['name' => 'Glas & Kristall', 'type' => 'physical', 'color' => 'bg-blue-50 text-blue-700'],
            ['name' => 'Holz & Natur', 'type' => 'physical', 'color' => 'bg-amber-50 text-amber-700'],
            ['name' => 'Metall & Alu', 'type' => 'physical', 'color' => 'bg-gray-100 text-gray-700'],
            ['name' => 'Schmuck & Anhänger', 'type' => 'physical', 'color' => 'bg-pink-50 text-pink-700'],
            ['name' => 'Geschenksets', 'type' => 'physical', 'color' => 'bg-purple-50 text-purple-700'],
            ['name' => 'Bestseller', 'type' => 'physical', 'color' => 'bg-yellow-100 text-yellow-800'],

            // Digital
            ['name' => 'E-Books & Guides', 'type' => 'digital', 'color' => 'bg-indigo-50 text-indigo-700'],
            ['name' => 'Design-Vorlagen', 'type' => 'digital', 'color' => 'bg-cyan-50 text-cyan-700'],
            ['name' => 'Printables', 'type' => 'digital', 'color' => 'bg-teal-50 text-teal-700'],
            ['name' => 'Audio & Meditation', 'type' => 'digital', 'color' => 'bg-emerald-50 text-emerald-700'],

            // Service
            ['name' => 'Beratung', 'type' => 'service', 'color' => 'bg-orange-50 text-orange-700'],
            ['name' => 'Workshops', 'type' => 'service', 'color' => 'bg-rose-50 text-rose-700'],
            ['name' => 'Express-Service', 'type' => 'service', 'color' => 'bg-red-50 text-red-700'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => Str::slug($cat['name'])],
                $cat
            );
        }
    }
}
