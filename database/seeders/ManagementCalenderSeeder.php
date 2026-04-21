<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Management\ManagementCalendarEvent;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ManagementCalenderSeeder extends Seeder
{
    public function run(): void
    {
        // Delete old explicit events if needed, but here we just create them
        ManagementCalendarEvent::create([
            'id' => Str::uuid(),
            'title' => 'Operation / Krankenhausaufenthalt',
            'start_date' => Carbon::create(2026, 6, 23, 0, 0, 0),
            'end_date' => Carbon::create(2026, 7, 23, 23, 59, 59),
            'is_all_day' => true,
            'category' => 'general',
            'description' => 'Geplante OP mit anschließender Regenerationsphase. Keine operativen Tasks während dieser Zeit übernehmen.',
            'priority' => 'high'
        ]);

        ManagementCalendarEvent::create([
            'id' => Str::uuid(),
            'title' => 'Gewerbeanmeldung',
            'start_date' => Carbon::create(2026, 8, 1, 0, 0, 0),
            'end_date' => Carbon::create(2026, 8, 1, 23, 59, 59),
            'is_all_day' => true,
            'category' => 'general',
            'description' => 'Notwendige Papiere müssen eingereicht werden. Meilenstein für die formelle Gründung.',
            'priority' => 'high'
        ]);
    }
}
