<?php

namespace Database\Seeders;

use App\Models\Management\ManagementDayRoutine;
use App\Models\Management\ManagementDayRoutineStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// WICHTIG: Diesen Import hinzufügen!

class ManagementDayRoutineSeeder extends Seeder
{
    public function run(): void
    {
        $routines = [
            [
                'time' => '07:00',
                'title' => 'Aufstehen & Frischestart',
                'icon' => 'sparkles',
                'type' => 'hygiene',
                'duration' => 30,
                'message' => 'Guten Morgen! Nicht lange überlegen: Raus aus den Federn, Gesicht mit frischem Wasser wecken, Kälteschutz drauf und rein in die Sportsachen.',
                'steps' => [
                    ['title' => 'Aufstehen & Wasser ins Gesicht', 'min' => 5],
                    ['title' => 'Aufs Klo gehen', 'min' => 5],
                    ['title' => 'Kälteschutz / Gesichtscreme auftragen', 'min' => 5],
                    ['title' => 'In Sportkleidung werfen & Schuhe anziehen', 'min' => 10],
                    ['title' => 'Großes Glas Wasser trinken', 'min' => 5],
                ]
            ],
            [
                'time' => '07:30',
                'title' => 'Morgensport: Laufen & Ganzkörper-Workout',
                'icon' => 'trophy',
                'type' => 'sport',
                'duration' => 75,
                'message' => 'Der einzige Sportblock des Tages! Zieh es jetzt durch und du hast für den restlichen Tag frei von Sport. Erst frische Luft und Schritte sammeln, danach gezielte Übungen für Rücken und Körper.',
                'steps' => [
                    ['title' => 'Morgenrunde laufen / walken (anfangs kürzere Wege testen)', 'min' => 45],
                    ['title' => 'Dehnübungen & Mobilität (Warmdown Laufen / Warmup Workout)', 'min' => 10],
                    ['title' => 'Rücken- & Rumpfübungen (Schutz vor Schreibtisch-Schmerzen)', 'min' => 10],
                    ['title' => 'Bauch-, Bein- & Po-Übungen', 'min' => 10],
                ]
            ],
            [
                'time' => '08:45',
                'title' => 'Bad, Styling & Hormone',
                'icon' => 'sparkles',
                'type' => 'hygiene',
                'duration' => 75,
                'message' => 'Jetzt ohne Zeitdruck frisch machen. Warm duschen, Hormone auftragen, stylen und zurechtmachen. Nimm dir die Zeit, dich wohl in deiner Haut zu fühlen.',
                'steps' => [
                    ['title' => 'Ausgiebig duschen & abtrocknen', 'min' => 15],
                    ['title' => 'Hormone auftragen & einziehen lassen', 'min' => 5],
                    ['title' => 'Hautpflege & Eincremen', 'min' => 10],
                    ['title' => 'Haare machen & stylen', 'min' => 15],
                    ['title' => 'Schminken & Make-up', 'min' => 15],
                    ['title' => 'Zähne putzen', 'min' => 5],
                    ['title' => 'Tagesoutfit anziehen', 'min' => 10],
                ]
            ],
            [
                'time' => '10:00',
                'title' => 'Frühstück & Mentaler Fokus',
                'icon' => 'cake',
                'type' => 'food',
                'duration' => 30,
                'message' => 'Treibstoff für Kopf und Körper. Nimm dir diese Zeit, um in Ruhe anzukommen, zu frühstücken und das wichtigste Tagesziel für den ersten Fokusblock festzulegen.',
                'steps' => [
                    ['title' => 'Frühstück / Shake zubereiten', 'min' => 10],
                    ['title' => 'In Ruhe frühstücken & Kaffee/Tee trinken', 'min' => 15],
                    ['title' => 'Tagesziel & Kernaufgabe visualisieren', 'min' => 5],
                ]
            ],
            [
                'time' => '10:30',
                'title' => 'Deep Work Block I: Fokusprojekt',
                'icon' => 'briefcase',
                'type' => 'work',
                'duration' => 120, // 2,0 Stunden (bis 12:30)
                'message' => 'Eat the Frog! Jetzt wird abgeliefert: Keine Ablenkungen, kein Social Media, voller Fokus auf das wichtigste Projekt (z.B. Coden oder Content produzieren).',
                'steps' => [
                    ['title' => 'Fokus-Arbeit: Kernprojekt & Deep Work', 'min' => 120],
                ]
            ],
            [
                'time' => '12:30',
                'title' => 'Mittagspause & Regeneration',
                'icon' => 'fire',
                'type' => 'food',
                'duration' => 75, // Bis 13:45
                'message' => 'Cut! Verlass den Schreibtisch. Koche dir etwas Leckeres, schenke Gloria Aufmerksamkeit und schalte komplett vom Monitor ab.',
                'steps' => [
                    ['title' => 'Mittagessen kochen / zubereiten', 'min' => 25],
                    ['title' => 'In Ruhe essen', 'min' => 20],
                    ['title' => 'Gloria hallo sagen & Zeit schenken', 'min' => 10],
                    ['title' => 'Verdauungspause, Durchatmen & Zähne putzen', 'min' => 20],
                ]
            ],
            [
                'time' => '13:45',
                'title' => 'Work Block II: Operations & Business',
                'icon' => 'computer-desktop',
                'type' => 'work',
                'duration' => 150, // 2,5 Stunden (bis 16:15)
                'message' => 'Zweiter und letzter Arbeitsblock des Tages! Abarbeiten von E-Mails, Marketing-Videos schneiden/planen, Bestellungen und Support. Bleib dran – um 16:15 Uhr ist Feierabend!',
                'steps' => [
                    ['title' => 'Tagesgeschäft: Videos, Mails & operative Aufgaben', 'min' => 150],
                ]
            ],
            [
                'time' => '16:15',
                'title' => 'Feierabend-Reset & Übergang',
                'icon' => 'sun',
                'type' => 'break',
                'duration' => 45, // Bis 17:00
                'message' => 'Feierabend! Schließe alle Tabs, räume den Schreibtisch auf und ziehe einen klaren mentalen Schlussstrich unter den Arbeitstag.',
                'steps' => [
                    ['title' => 'Schreibtisch aufräumen & Rechner herunterfahren', 'min' => 10],
                    ['title' => 'Beine hochlegen, durchatmen & entspannen', 'min' => 20],
                    ['title' => 'Musik hören / Gedanken sortieren', 'min' => 15],
                ]
            ],
            [
                'time' => '17:00',
                'title' => 'Abendbrot & Abendpflege',
                'icon' => 'moon',
                'type' => 'hygiene',
                'duration' => 90, // Bis 18:30
                'message' => 'Gemütlich Abendbrot essen und danach ab ins Bad: kurz abduschen, rasieren, eincremen und in bequeme Kleidung schlüpfen – bereit für einen entspannten Abend.',
                'steps' => [
                    ['title' => 'Abendbrot zubereiten', 'min' => 15],
                    ['title' => 'Gemütlich essen & trinken', 'min' => 25],
                    ['title' => 'Küche kurz aufräumen', 'min' => 10],
                    ['title' => 'Kurz abduschen & frisch machen', 'min' => 10],
                    ['title' => 'Rasieren & Eincremen', 'min' => 20],
                    ['title' => 'Bequeme Wohlfühlkleidung anziehen', 'min' => 10],
                ]
            ],
            [
                'time' => '18:30',
                'title' => 'Freizeit & Me-Time',
                'icon' => 'sparkles',
                'type' => 'break',
                'duration' => 210, // 3,5 Stunden (bis 22:00)
                'message' => 'Wohlverdienter Feierabend! Jetzt gehört die Zeit komplett dir. Zocken, Filme schauen, Serien streamen oder einfach nichts tun – völlig ohne schlechtes Gewissen.',
                'steps' => [
                    ['title' => 'Zocken, Filme, Hobbys & Seele baumeln lassen', 'min' => 210],
                ]
            ],
            [
                'time' => '22:00',
                'title' => 'Bettfertig & Runterfahren',
                'icon' => 'star',
                'type' => 'hygiene',
                'duration' => 30, // Bis 22:30
                'message' => 'Den Tag in Ruhe beschließen. Zähne putzen, letzte Kleinigkeiten im Bad erledigen, Handy weglegen und zur Ruhe kommen.',
                'steps' => [
                    ['title' => 'Zähne putzen & Mundspülung', 'min' => 5],
                    ['title' => 'Kleinigkeiten Körperpflege & Klo', 'min' => 10],
                    ['title' => 'Schlafanzug an & Schlafzimmer lüften', 'min' => 10],
                    ['title' => 'Handy weglegen & Licht aus', 'min' => 5],
                ]
            ],
            [
                'time' => '22:30',
                'title' => 'Nachtruhe & Regeneration',
                'icon' => 'moon',
                'type' => 'sleep',
                'duration' => 510, // 8,5 Stunden (Bis 07:00 Uhr)
                'message' => 'Schlaf ist nicht verhandelbar! Er ist deine wichtigste Regenerationsquelle für Körper und Geist. Gute Nacht und träum was Schönes!',
                'steps' => [
                    ['title' => 'Augen zu, tiefer Schlaf & Erholung', 'min' => 510],
                ]
            ],
        ];

        // --- HIER IST DER FIX ---
        // FK Checks aus, leeren, FK Checks wieder an
        Schema::disableForeignKeyConstraints();

        ManagementDayRoutineStep::truncate();
        ManagementDayRoutine::truncate();

        Schema::enableForeignKeyConstraints();
        // -------------------------

        foreach ($routines as $r) {
            $routine = ManagementDayRoutine::create([
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
                ManagementDayRoutineStep::create([
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
