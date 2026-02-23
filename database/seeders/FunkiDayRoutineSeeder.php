<?php

namespace Database\Seeders;

use App\Models\Funki\FunkiDayRoutine;
use App\Models\Funki\FunkiDayRoutineStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// WICHTIG: Diesen Import hinzufügen!

class FunkiDayRoutineSeeder extends Seeder
{
    public function run(): void
    {
        $routines = [
            [
                'time' => '09:00',
                'title' => 'Morgenroutine & Vorbereitung',
                'icon' => 'sparkles',
                'type' => 'hygiene',
                'duration' => 30,
                'message' => 'Ein erfolgreicher Tag beginnt mit einer starken Basis. Keine Kompromisse bei der eigenen Pflege. Richte dich her, nimm deine Medikamente und mach dich mental bereit für den Tag. Wer sich gut fühlt, arbeitet auch so!',
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
                'title' => 'Frühstück & Fokus',
                'icon' => 'cake',
                'type' => 'food',
                'duration' => 30,
                'message' => 'Treibstoff für deinen Körper und deinen Kopf. Nimm dir diese 30 Minuten, um in Ruhe anzukommen. Leg das Handy weg, atme tief durch und fokussiere dich auf dein wichtigstes Tagesziel.',
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
                'message' => 'Jetzt wird abgeliefert! Keine Ausreden, keine Ablenkungen, kein Social Media. Das ist der wichtigste Block des Tages. Erledige die härtesten und wichtigsten Aufgaben (Eat the Frog). In dieser Zeit wächst dein Unternehmen.',
                'steps' => [
                    ['title' => 'Fokus-Arbeit: Das Wichtigste zuerst', 'min' => 165],
                ]
            ],
            [
                'time' => '12:45',
                'title' => 'Frische Luft & Reset',
                'icon' => 'sun',
                'type' => 'break',
                'duration' => 15,
                'message' => 'Cut! Steh sofort auf, verlass den Schreibtisch und geh kurz an die frische Luft. Dein Gehirn braucht jetzt zwingend Sauerstoff, um am Nachmittag wieder auf 100% laufen zu können.',
                'steps' => [
                    ['title' => 'Rausgehen, Atmen, Bewegen', 'min' => 15],
                ]
            ],
            [
                'time' => '13:00',
                'title' => 'Mittagspause & Regeneration',
                'icon' => 'fire',
                'type' => 'food',
                'duration' => 60,
                'message' => 'Mahlzeit! Koche dir etwas Vernünftiges. Du bist eine Maschine, die gute Energie braucht. Und ganz wichtig: Nimm dir bewusst die Zeit, Gloria eine Runde Aufmerksamkeit zu schenken. Das erdet dich.',
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
                'title' => 'Work Session II (Operations)',
                'icon' => 'computer-desktop',
                'type' => 'work',
                'duration' => 300, // Bis 19:00
                'message' => 'Der zweite große Block gehört dem Tagesgeschäft. Abarbeiten von Bestellungen, E-Mails beantworten, Kundensupport und operative Projekte vorantreiben. Bleib diszipliniert – die saubere Arbeit von heute verhindert das Chaos von morgen.',
                'steps' => [
                    ['title' => 'Produktivität: Bestellungen, Mails & Laser-Arbeiten', 'min' => 300],
                ]
            ],
            [
                'time' => '19:00',
                'title' => 'Sport & Ausgleich',
                'icon' => 'trophy',
                'type' => 'sport',
                'duration' => 60,
                'message' => 'Dein Körper ist dein wertvollstes Kapital. Keine Diskussionen jetzt, zieh dein Workout durch! Der physische Stress baut den mentalen Stress ab. Schweiß ist Schwäche, die den Körper verlässt.',
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
                'title' => 'Abendessen & Feierabend',
                'icon' => 'moon',
                'type' => 'food',
                'duration' => 90, // Bis 21:30
                'message' => 'Der anstrengende Teil des Tages ist geschafft. Mach dir dein Essen, fahr das System in deinem Kopf langsam herunter und genieße deinen absolut wohlverdienten Feierabend. Arbeit ist jetzt tabu!',
                'steps' => [
                    ['title' => 'Brot mit Aufstrich zubereiten', 'min' => 15],
                    ['title' => 'Essen & Feierabend genießen', 'min' => 75],
                ]
            ],
            [
                'time' => '21:30',
                'title' => 'Abendroutine & Pflege',
                'icon' => 'star',
                'type' => 'hygiene',
                'duration' => 30,
                'message' => 'Der Tag neigt sich dem Ende zu. Wasch den Stress des Tages im Bad ab, pflege deine Haut und bereite dich mental und körperlich auf eine tiefe Ruhephase vor.',
                'steps' => [
                    ['title' => 'Duschen (waschen)', 'min' => 10],
                    ['title' => 'Zähne putzen', 'min' => 3],
                    ['title' => 'Hormone drauf machen', 'min' => 2],
                    ['title' => 'Schlafanzug anziehen', 'min' => 5],
                    ['title' => 'Abschminken & Pflege', 'min' => 10],
                ]
            ],
            [
                'time' => '22:00',
                'title' => 'Nachtruhe & Regeneration',
                'icon' => 'moon',
                'type' => 'sleep',
                'duration' => 660, // 11 Stunden (Bis 09:00 Uhr)
                'message' => 'Schlaf ist nicht verhandelbar! Er ist deine absolut wichtigste Regenerationsquelle. Leg das Handy sofort weit weg, mach die Augen zu. Nur wer tief schläft, kann morgen wieder auf Hochtouren performen. Gute Nacht!',
                'steps' => [
                    ['title' => 'Handy weg, Augen zu, Träumen', 'min' => 660],
                ]
            ],
        ];

        // --- HIER IST DER FIX ---
        // FK Checks aus, leeren, FK Checks wieder an
        Schema::disableForeignKeyConstraints();

        FunkiDayRoutineStep::truncate();
        FunkiDayRoutine::truncate();

        Schema::enableForeignKeyConstraints();
        // -------------------------

        foreach ($routines as $r) {
            $routine = FunkiDayRoutine::create([
                'id' => Str::uuid(),
                'start_time' => $r['time'],
                'title' => $r['title'],
                'message' => $r['message'],
                'icon' => $r['icon'],
                'type' => $r['type'],
                'duration_minutes' => $r['duration'],
                'is_active' => true
            ]);

            foreach ($r['steps'] as $index => $step) {
                FunkiDayRoutineStep::create([
                    'id' => Str::uuid(),
                    'funki_day_routine_id' => $routine->id,
                    'title' => $step['title'],
                    'position' => $index + 1,
                    'duration_minutes' => $step['min']
                ]);
            }
        }
    }
}
