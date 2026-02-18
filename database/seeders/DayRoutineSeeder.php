<?php

namespace Database\Seeders;

use App\Models\DayRoutine;
use App\Models\DayRoutineStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DayRoutineSeeder extends Seeder
{
    public function run(): void
    {
        // Altes löschen um Duplikate zu vermeiden
        DayRoutine::truncate();
        DayRoutineStep::truncate();

        $routines = [
            [
                'time' => '09:00',
                'title' => 'Morgenroutine',
                'icon' => 'sparkles',
                'type' => 'hygiene',
                'duration' => 30,
                'steps' => [
                    ['title' => 'Haare hoch machen', 'min' => 1],
                    ['title' => 'Aufs Klo gehen', 'min' => 4],
                    ['title' => 'Intim waschen', 'min' => 2],
                    ['title' => 'Hormone drauf machen', 'min' => 2],
                    ['title' => 'Gesicht waschen', 'min' => 2],
                    ['title' => 'Tabletten nehmen', 'min' => 1],
                    ['title' => 'Rasieren', 'min' => 5],
                    ['title' => 'Eincremen', 'min' => 3],
                    ['title' => 'Schminken', 'min' => 5],
                    ['title' => 'Haare waschen / richten', 'min' => 3],
                    ['title' => 'Zähne putzen', 'min' => 2],
                ]
            ],
            [
                'time' => '09:30',
                'title' => 'Frühstück',
                'icon' => 'cake',
                'type' => 'food',
                'duration' => 30,
                'steps' => [
                    ['title' => 'In die Küche gehen / Hinsetzen', 'min' => 5],
                    ['title' => 'Shake trinken', 'min' => 10],
                    ['title' => 'Kleine Pause & Durchatmen', 'min' => 15],
                ]
            ],
            [
                'time' => '10:00',
                'title' => 'Deep Work Phase',
                'icon' => 'briefcase',
                'type' => 'work',
                'duration' => 165, // Bis 12:45
                'steps' => [
                    ['title' => 'Fokus-Arbeit: Das Wichtigste zuerst', 'min' => 165],
                ]
            ],
            [
                'time' => '12:45',
                'title' => 'Frische Luft Pause',
                'icon' => 'sun',
                'type' => 'break',
                'duration' => 15,
                'steps' => [
                    ['title' => 'Rausgehen, Atmen, Bewegen', 'min' => 15],
                ]
            ],
            [
                'time' => '13:00',
                'title' => 'Mittagessen',
                'icon' => 'fire',
                'type' => 'food',
                'duration' => 60,
                'steps' => [
                    ['title' => 'Essen kochen', 'min' => 20],
                    ['title' => 'Tisch decken', 'min' => 5],
                    ['title' => 'Gloria hallo sagen', 'min' => 5],
                    ['title' => 'In Ruhe essen', 'min' => 20],
                    ['title' => 'Durchatmen', 'min' => 5],
                    ['title' => 'Zähne putzen', 'min' => 5],
                ]
            ],
            [
                'time' => '14:00',
                'title' => 'Work Session II',
                'icon' => 'computer-desktop',
                'type' => 'work',
                'duration' => 300, // Bis 19:00
                'steps' => [
                    ['title' => 'Produktivität: Bestellungen & Mails', 'min' => 300],
                ]
            ],
            [
                'time' => '19:00',
                'title' => 'Sport',
                'icon' => 'trophy',
                'type' => 'sport',
                'duration' => 60,
                'steps' => [
                    ['title' => 'Dehnübungen (Warmup)', 'min' => 10],
                    ['title' => 'Untere Rückenübungen', 'min' => 10],
                    ['title' => 'Bauchübungen', 'min' => 10],
                    ['title' => 'Beinübungen', 'min' => 15],
                    ['title' => 'Poübungen', 'min' => 15],
                ]
            ],
            [
                'time' => '20:00',
                'title' => 'Abendbrot',
                'icon' => 'moon',
                'type' => 'food',
                'duration' => 90, // Bis 21:30
                'steps' => [
                    ['title' => 'Brot mit Aufstrich zubereiten', 'min' => 15],
                    ['title' => 'Essen & Feierabend genießen', 'min' => 75],
                ]
            ],
            [
                'time' => '21:30',
                'title' => 'Abendroutine',
                'icon' => 'star',
                'type' => 'hygiene',
                'duration' => 30,
                'steps' => [
                    ['title' => 'Duschen (waschen)', 'min' => 10],
                    ['title' => 'Zähne putzen', 'min' => 3],
                    ['title' => 'Hormone drauf machen', 'min' => 2],
                    ['title' => 'Schlafanzug anziehen', 'min' => 5],
                    ['title' => 'Abschminken', 'min' => 10],
                ]
            ],
            [
                'time' => '22:00',
                'title' => 'Nachtruhe',
                'icon' => 'moon',
                'type' => 'sleep',
                'duration' => 0, // Open End
                'steps' => [
                    ['title' => 'Handy weg, Augen zu, Träumen', 'min' => 0],
                ]
            ],
        ];

        foreach ($routines as $r) {
            $routine = DayRoutine::create([
                'id' => Str::uuid(),
                'start_time' => $r['time'],
                'title' => $r['title'],
                'message' => 'Folge dem Plan.',
                'icon' => $r['icon'],
                'type' => $r['type'],
                'duration_minutes' => $r['duration'],
                'is_active' => true
            ]);

            foreach ($r['steps'] as $index => $step) {
                DayRoutineStep::create([
                    'id' => Str::uuid(),
                    'day_routine_id' => $routine->id,
                    'title' => $step['title'],
                    'position' => $index + 1,
                    'duration_minutes' => $step['min']
                ]);
            }
        }
    }
}
