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
                'message' => 'Der Wecker klingelt – jetzt gilt es! Kein Schlummern, keine Ausreden. Sei stärker als dein innerer Schweinehund und spring direkt aus dem Bett. Gewinner werden am Morgen gemacht!',
                'steps' => [
                    ['title' => 'Aufstehen ohne Zögern & eiskaltes Wasser ins Gesicht', 'min' => 5],
                    ['title' => 'Aufs Klo gehen', 'min' => 5],
                    ['title' => 'Kälteschutz / Gesichtscreme auftragen', 'min' => 5],
                    ['title' => 'In Sportkleidung werfen & Laufschuhe anziehen', 'min' => 10],
                    ['title' => 'Großes Glas Wasser trinken & Fokus schärfen', 'min' => 5],
                ]
            ],
            [
                'time' => '07:30',
                'title' => 'Morgensport: Laufen & Ganzkörper-Workout',
                'icon' => 'trophy',
                'type' => 'sport',
                'duration' => 75,
                'message' => 'Gas geben, durchziehen, durchhalten! Das ist der einzige Sportblock des Tages. Gib alles auf der Strecke und bei den Übungen. Nicht aufgeben, wenn es brennt – genau da wächst du! Danach hast du für den restlichen Tag frei von Sport.',
                'steps' => [
                    ['title' => 'Morgenrunde laufen / walken (anfangs kürzere Wege testen)', 'min' => 45],
                    ['title' => 'Dehnübungen & Mobilität (Warmdown Laufen / Warmup Workout)', 'min' => 10],
                    ['title' => 'Rücken- & Rumpfübungen (Schutz vor Schreibtisch-Schmerzen)', 'min' => 10],
                    ['title' => 'Bauch-, Bein- & Po-Power – Bis zum Brennen durchziehen', 'min' => 10],
                ]
            ],
            [
                'time' => '08:45',
                'title' => 'Bad, Styling & Hormone',
                'icon' => 'sparkles',
                'type' => 'hygiene',
                'duration' => 75,
                'message' => 'Richte dich her wie ein Champion! Nimm dir die Zeit für deine Pflege, deine Hormone und dein Styling. Wer sich unaufhaltsam fühlt, tritt auch so auf. Zeig der Welt dein bestes Ich!',
                'steps' => [
                    ['title' => 'Ausgiebig heiß/kalt duschen & abtrocknen', 'min' => 15],
                    ['title' => 'Hormone auftragen & einziehen lassen', 'min' => 5],
                    ['title' => 'Hautpflege & Eincremen', 'min' => 10],
                    ['title' => 'Haare föhnen, stylen & richten', 'min' => 15],
                    ['title' => 'Schminken & Make-up auflegen', 'min' => 15],
                    ['title' => 'Zähne gründlich putzen', 'min' => 5],
                    ['title' => 'Starkes Tagesoutfit anziehen', 'min' => 10],
                ]
            ],
            [
                'time' => '10:00',
                'title' => 'Frühstück & Mentaler Fokus',
                'icon' => 'cake',
                'type' => 'food',
                'duration' => 30,
                'message' => 'Treibstoff für Spitzenleistungen. Frühstücke gesund, tanke Kraft und mach dich mental bereit. Sei fokussierter und hungriger auf Erfolg als alle anderen da draußen!',
                'steps' => [
                    ['title' => 'Gesundes Frühstück / Power-Shake zubereiten', 'min' => 10],
                    ['title' => 'In Ruhe frühstücken & Kaffee / Tee genießen', 'min' => 15],
                    ['title' => 'Tagesziel Nr. 1 visualisieren & mental heiß machen', 'min' => 5],
                ]
            ],
            [
                'time' => '10:30',
                'title' => 'Deep Work Block I: Fokusprojekt',
                'icon' => 'briefcase',
                'type' => 'work',
                'duration' => 120, // 2,0 Stunden (bis 12:30)
                'message' => 'Eat the Frog! Jetzt wird gnadenlos abgeliefert: Keine Ausreden, kein Social Media, voller Tunnelblick auf dein wichtigstes Projekt. Vollgas geben und durchziehen – in diesen 2 Stunden baust du deine Zukunft auf!',
                'steps' => [
                    ['title' => 'Priorität 1: Programmieren / Kernaufgabe gnadenlos durchziehen', 'min' => 120],
                ]
            ],
            [
                'time' => '12:30',
                'title' => 'Mittagspause & Regeneration',
                'icon' => 'fire',
                'type' => 'food',
                'duration' => 60, // Bis 13:30
                'message' => 'Mahlzeit! Erste Halbzeit mit Bravour gemeistert. Verlass den Schreibtisch, koche dir etwas Gutes, atme durch und tanke neue Energie für die zweite Tageshälfte.',
                'steps' => [
                    ['title' => 'Mittagessen zubereiten / kochen', 'min' => 25],
                    ['title' => 'In Ruhe essen & Kraft tanken', 'min' => 20],
                    ['title' => 'Kurze Verdauungspause & Zähne putzen', 'min' => 15],
                ]
            ],
            [
                'time' => '13:30',
                'title' => 'Haushalt, Termine & Life-Orga',
                'icon' => 'sun',
                'type' => 'break',
                'duration' => 60, // Bis 14:30
                'message' => 'Klarheit im Raum schafft Klarheit im Geist! Wäsche, Wohnung aufräumen, Bett neu beziehen oder private Termine und Anrufe erledigen: Schnell und sauber durchziehen, keine Baustellen im Leben liegen lassen!',
                'steps' => [
                    ['title' => 'Wäsche waschen / aufhängen / zusammenlegen', 'min' => 15],
                    ['title' => 'Wohnung aufräumen, saugen oder Bett frisch beziehen', 'min' => 20],
                    ['title' => 'Private Termine, Erledigungen, Anrufe & Behörden-Orga', 'min' => 25],
                ]
            ],
            [
                'time' => '14:30',
                'title' => 'Work Block II: Operations & Business',
                'icon' => 'computer-desktop',
                'type' => 'work',
                'duration' => 150, // 2,5 Stunden (bis 17:00)
                'message' => 'Zweite Halbzeit: Durchhalten und die Ziellinie überqueren! E-Mails, Marketing-Videos schneiden/planen, Bestellungen und Tagesgeschäft abreißen. Gib noch einmal alles – sei disziplinierter als die Konkurrenz!',
                'steps' => [
                    ['title' => 'Marketing-Videos, E-Mails, Bestellungen & Business-Endspurt', 'min' => 150],
                ]
            ],
            [
                'time' => '17:00',
                'title' => 'Feierabend-Reset & Tagesabschluss',
                'icon' => 'sparkles',
                'type' => 'break',
                'duration' => 30, // Bis 17:30
                'message' => 'Sieg für heute eingefahren! Klappe den Laptop zu, räume den Schreibtisch auf und ziehe einen klaren mentalen Schlussstrich. Wer mit voller Power abgeliefert hat, darf mit stolzer Brust Feierabend machen!',
                'steps' => [
                    ['title' => 'Schreibtisch clean aufräumen & Rechner herunterfahren', 'min' => 10],
                    ['title' => 'Durchatmen, Beine hochlegen & Arbeitsmodus beenden', 'min' => 20],
                ]
            ],
            [
                'time' => '17:30',
                'title' => 'Abendbrot & Abendpflege',
                'icon' => 'moon',
                'type' => 'hygiene',
                'duration' => 75, // Bis 18:45
                'message' => 'Gemütlich Abendbrot essen und den Schmutz des Tages abwaschen. Kurz abduschen, rasieren, eincremen und in bequeme Wohlfühlkleidung schlüpfen. Du hast heute alles gegeben!',
                'steps' => [
                    ['title' => 'Abendbrot zubereiten', 'min' => 15],
                    ['title' => 'Gemütlich essen & trinken', 'min' => 20],
                    ['title' => 'Küche sauber hinterlassen', 'min' => 10],
                    ['title' => 'Kurz abduschen, frisch machen & rasieren', 'min' => 20],
                    ['title' => 'Eincremen & Wohlfühlkleidung anziehen', 'min' => 10],
                ]
            ],
            [
                'time' => '18:45',
                'title' => 'Freizeit & Me-Time',
                'icon' => 'sparkles',
                'type' => 'break',
                'duration' => 195, // 3h 15m (bis 22:00)
                'message' => 'Absolut verdienter Feierabend! Jetzt wird gezockt, gestreamt oder gechillt – ganz ohne schlechtes Gewissen. Du hast heute mehr getan als die meisten. Genieße deine freie Zeit in vollen Zügen!',
                'steps' => [
                    ['title' => 'Zocken, Filme, Hobbys & Seele baumeln lassen', 'min' => 195],
                ]
            ],
            [
                'time' => '22:00',
                'title' => 'Bettfertig & Runterfahren',
                'icon' => 'star',
                'type' => 'hygiene',
                'duration' => 30, // Bis 22:30
                'message' => 'System langsam herunterfahren. Zähne putzen, letzte Handgriffe im Bad erledigen, Bildschirme ausschalten. Zeit, die Batterien für morgen aufzuladen.',
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
                'message' => 'Regeneration für Champions! Mach die Augen zu und schlafe tief und fest. Nur wer gut schläft, kann morgen wieder auf Hochtouren Gas geben und stärker sein als alle anderen. Gute Nacht!',
                'steps' => [
                    ['title' => 'Augen zu, tiefer Schlaf & Regeneration für den nächsten Sieg', 'min' => 510],
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
