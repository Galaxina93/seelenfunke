<?php

namespace Database\Seeders;

use App\Models\Management\ManagementTimelineEvent;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ManagementTimelineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ManagementTimelineEvent::truncate();

        ManagementTimelineEvent::create([
            'title' => 'Operation / Krankenhausaufenthalt',
            'description' => 'Geplante OP mit anschließender Regenerationsphase. Keine operativen Tasks während dieser Zeit übernehmen.',
            'start_date' => Carbon::parse('2026-06-23'),
            'end_date' => Carbon::parse('2026-07-23'),
            'type' => 'roadblock',
            'impact_level' => 'high'
        ]);

        ManagementTimelineEvent::create([
            'title' => 'Gewerbeanmeldung',
            'description' => 'Notwendige Papiere müssen eingereicht werden. Meilenstein für die formelle Gründung.',
            'start_date' => Carbon::parse('2026-08-01'),
            'end_date' => null,
            'type' => 'milestone',
            'impact_level' => 'high'
        ]);

        ManagementTimelineEvent::create([
            'title' => 'Shop Launch Party',
            'description' => 'Offizielles Event zum Release. Vorbereitung des Marketings notwendig.',
            'start_date' => Carbon::parse('2026-10-15'),
            'end_date' => Carbon::parse('2026-10-15'),
            'type' => 'event',
            'impact_level' => 'medium'
        ]);
        
        ManagementTimelineEvent::create([
            'title' => 'Winter-Saison Vorbereitung',
            'description' => 'Fokus auf Weihnachtsartikel und Produktion aufstocken.',
            'start_date' => Carbon::parse('2026-11-01'),
            'end_date' => Carbon::parse('2026-12-24'),
            'type' => 'phase',
            'impact_level' => 'medium'
        ]);
    }
}
