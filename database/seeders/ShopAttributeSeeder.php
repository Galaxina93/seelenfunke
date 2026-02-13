<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShopAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = [
            // Physical
            ['name' => 'Material', 'type' => 'physical'],
            ['name' => 'Größe', 'type' => 'physical'],
            ['name' => 'Form', 'type' => 'physical'],
            ['name' => 'Gewicht', 'type' => 'physical'],
            ['name' => 'Farbe', 'type' => 'physical'],
            ['name' => 'Verpackung', 'type' => 'physical'],
            ['name' => 'Druck', 'type' => 'physical'],
            ['name' => 'Technik', 'type' => 'physical'],
            ['name' => 'Lieferzeit', 'type' => 'physical'],
            ['name' => 'Oberfläche', 'type' => 'physical'],
            ['name' => 'Besonderheit', 'type' => 'physical'],

            // Digital
            ['name' => 'Format', 'type' => 'digital'],
            ['name' => 'Seiten', 'type' => 'digital'],
            ['name' => 'Sprache', 'type' => 'digital'], // Doppelt, wird durch firstOrCreate handled oder ignoriert
            ['name' => 'Auslieferung', 'type' => 'digital'],

            // Service
            ['name' => 'Dauer', 'type' => 'service'],
            ['name' => 'Ort', 'type' => 'service'],
            ['name' => 'Experte', 'type' => 'service'],
        ];

        foreach ($attributes as $attr) {
            // Wir nutzen insertOrIgnore oder firstOrCreate, um Sprache doppelt zu vermeiden
            if (!DB::table('shop_attributes')->where('name', $attr['name'])->exists()) {
                DB::table('shop_attributes')->insert([
                    'name' => $attr['name'],
                    'slug' => Str::slug($attr['name']),
                    'type' => $attr['type'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
