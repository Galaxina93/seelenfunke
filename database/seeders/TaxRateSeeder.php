<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tax_rates')->truncate();

        $euMemberStates = [
            'BE' => ['name' => 'Belgien', 'rate' => 21.00],
            'BG' => ['name' => 'Bulgarien', 'rate' => 20.00],
            'CZ' => ['name' => 'Tschechien', 'rate' => 21.00],
            'DK' => ['name' => 'Dänemark', 'rate' => 25.00],
            'DE' => ['name' => 'Deutschland', 'rate' => 19.00],
            'EE' => ['name' => 'Estland', 'rate' => 22.00],
            'IE' => ['name' => 'Irland', 'rate' => 23.00],
            'GR' => ['name' => 'Griechenland', 'rate' => 24.00],
            'ES' => ['name' => 'Spanien', 'rate' => 21.00],
            'FR' => ['name' => 'Frankreich', 'rate' => 20.00],
            'HR' => ['name' => 'Kroatien', 'rate' => 25.00],
            'IT' => ['name' => 'Italien', 'rate' => 22.00],
            'CY' => ['name' => 'Zypern', 'rate' => 19.00],
            'LV' => ['name' => 'Lettland', 'rate' => 21.00],
            'LT' => ['name' => 'Litauen', 'rate' => 21.00],
            'LU' => ['name' => 'Luxemburg', 'rate' => 17.00],
            'HU' => ['name' => 'Ungarn', 'rate' => 27.00],
            'MT' => ['name' => 'Malta', 'rate' => 18.00],
            'NL' => ['name' => 'Niederlande', 'rate' => 21.00],
            'AT' => ['name' => 'Österreich', 'rate' => 20.00],
            'PL' => ['name' => 'Polen', 'rate' => 23.00],
            'PT' => ['name' => 'Portugal', 'rate' => 23.00],
            'RO' => ['name' => 'Rumänien', 'rate' => 19.00],
            'SI' => ['name' => 'Slowenien', 'rate' => 22.00],
            'SK' => ['name' => 'Slowakei', 'rate' => 20.00],
            'FI' => ['name' => 'Finnland', 'rate' => 24.00],
            'SE' => ['name' => 'Schweden', 'rate' => 25.00],
        ];

        foreach ($euMemberStates as $code => $data) {
            // Standard-Satz für jedes Land
            DB::table('tax_rates')->insert([
                'name'         => "Standard ({$data['name']})",
                'rate'         => $data['rate'],
                'country_code' => $code,
                'tax_class'    => 'standard',
                'is_default'   => ($code === 'DE'),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // Ermäßigter Satz für Deutschland (beispielhaft)
            // Falls du für andere Länder auch ermäßigte Sätze brauchst,
            // müssten diese hier analog zu DE ergänzt werden.
            if ($code === 'DE') {
                DB::table('tax_rates')->insert([
                    'name'         => 'Ermäßigt (DE)',
                    'rate'         => 7.00,
                    'country_code' => 'DE',
                    'tax_class'    => 'reduced',
                    'is_default'   => false,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }

        // Universal Steuerfrei Klasse
        DB::table('tax_rates')->insert([
            'name'         => 'Steuerfrei',
            'rate'         => 0.00,
            'country_code' => 'DE',
            'tax_class'    => 'zero',
            'is_default'   => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }
}
