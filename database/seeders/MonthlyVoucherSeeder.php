<?php

namespace Database\Seeders;

use App\Models\FunkiVoucher;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class MonthlyVoucherSeeder extends Seeder
{
    /**
     * Erstellt die monatlichen 5%-Aktionen für das aktuelle Jahr.
     * Fokus auf Wirtschaftlichkeit: 5% Rabatt bei 20 Nutzungen.
     */
    public function run(): void
    {
        $year = date('Y');

        $months = [
            1  => ['name' => 'Januar',    'code' => 'START'],
            2  => ['name' => 'Februar',   'code' => 'LOVE'],
            3  => ['name' => 'März',      'code' => 'FRUEHLING'],
            4  => ['name' => 'April',     'code' => 'OSTER'],
            5  => ['name' => 'Mai',       'code' => 'BLUME'],
            6  => ['name' => 'Juni',      'code' => 'SONNE'],
            7  => ['name' => 'Juli',      'code' => 'HITZE'],
            8  => ['name' => 'August',    'code' => 'URLAUB'],
            9  => ['name' => 'September', 'code' => 'HERBST'],
            10 => ['name' => 'Oktober',   'code' => 'GRUSEL'],
            11 => ['name' => 'November',  'code' => 'COZY'],
            12 => ['name' => 'Dezember',  'code' => 'XMAS'],
        ];

        foreach ($months as $num => $data) {
            $start = Carbon::create($year, $num, 1)->startOfMonth();
            $end = Carbon::create($year, $num, 1)->endOfMonth();

            FunkiVoucher::updateOrCreate(
                [
                    'code' => $data['code'] . '-' . $year,
                    'mode' => 'auto'
                ],
                [
                    'title'           => $data['name'] . ' ' . $year,
                    'type'            => 'percent',
                    'value'           => 5,        // Reduziert auf 5% für bessere Marge
                    'min_order_value' => 2000,     // Bleibt bei 20,00€ Mindestumsatz
                    'usage_limit'     => 20,       // Erhöht auf 20 Nutzungen
                    'is_active'       => true,
                    'valid_from'      => $start,
                    'valid_until'     => $end,
                    'days_offset'     => 0,
                ]
            );
        }

        $this->command->info("Wirtschaftlicher Check: 12x 5%-Voucher für $year erstellt (Limit: 20 Nutzungen/Monat).");
    }
}
