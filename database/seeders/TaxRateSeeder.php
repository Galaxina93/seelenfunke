<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        // Tabelle leeren, um Duplikate zu vermeiden
        DB::table('tax_rates')->truncate();

        DB::table('tax_rates')->insert([
            [
                'name' => 'Standard (DE)',
                'rate' => 19.00,
                'country_code' => 'DE',
                'tax_class' => 'standard',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Ermäßigt (DE)',
                'rate' => 7.00,
                'country_code' => 'DE',
                'tax_class' => 'reduced',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Steuerfrei',
                'rate' => 0.00,
                'country_code' => 'DE',
                'tax_class' => 'zero',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
