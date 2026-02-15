<?php

namespace Database\Seeders;

use App\Models\NewsletterTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NewsletterTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'target_event_key' => 'valentines',
                'title' => 'Valentinstag - Zeit für Liebe',
                'subject' => '✨ Ein Funke Liebe für einen besonderen Menschen',
                'content' => '<h1>Hallo {first_name},</h1><p>Bald ist Valentinstag! In der Seelenfunke-Manufaktur haben wir uns gefragt: Wie hält man Liebe eigentlich fest? Unsere Antwort: In glasklarem Kristall.</p><p>Sichere dir rechtzeitig dein persönliches Unikat, damit dein Geschenk pünktlich ankommt.</p>',
                'days_offset' => 12
            ],
            [
                'target_event_key' => 'easter',
                'title' => 'Ostern - Frühlingserwachen',
                'subject' => '🐰 Dein Nest braucht noch ein bisschen Glanz...',
                'content' => '<h1>Frohe Ostern, {first_name}!</h1><p>Der Frühling ist da und Funki hat zwischen den Blumen etwas Glitzerndes entdeckt. Entdecke unsere Frühlings-Kollektion und verschenke Freude zum Osterfest.</p>',
                'days_offset' => 14
            ],
            [
                'target_event_key' => 'mothers_day',
                'title' => 'Muttertag - Danke sagen',
                'subject' => '💐 Für die beste Mama der Welt: Ein Geschenk für die Ewigkeit',
                'content' => '<h1>Liebe {first_name},</h1><p>Mamas sind wie Kristalle: Einzigartig, wertvoll und sie bringen Licht in unser Leben. Sag dieses Jahr auf eine ganz besondere Weise "Danke" – mit einer persönlichen Gravur, die niemals verblasst.</p>',
                'days_offset' => 10
            ],
            [
                'target_event_key' => 'fathers_day',
                'title' => 'Vatertag - Alltagshelden',
                'subject' => '🛠️ Ein echtes Stück für echte Helden',
                'content' => '<h1>Hallo {first_name},</h1><p>Väter sind unser Fels in der Brandung. Schenke deinem Papa dieses Jahr etwas so Massives wie seine Unterstützung: Einen Seelen-Kristall oder unseren edlen Aluminium-Anhänger.</p>',
                'days_offset' => 10
            ],
            [
                'target_event_key' => 'christmas',
                'title' => 'Weihnachten - Magische Momente',
                'subject' => '🎄 Lass dieses Jahr die Augen unter dem Baum funkeln',
                'content' => '<h1>Ho Ho Ho {first_name},</h1><p>Weihnachten ist das Fest der Liebe und der Erinnerungen. Funki hat die Werkstatt schon festlich geschmückt. Gestalte jetzt dein persönliches Weihnachtsgeschenk, bevor unsere Produktionskapazitäten erschöpft sind!</p>',
                'days_offset' => 20
            ],
            [
                'target_event_key' => 'sale_summer',
                'title' => 'Sommer-Sale',
                'subject' => '☀️ Die Sonne lacht, unsere Preise auch!',
                'content' => '<h1>Sommer-Feeling bei Seelenfunke!</h1><p>Wir schaffen Platz für neue Kollektionen. Sichere dir jetzt deine Lieblingsstücke mit strahlenden Rabatten.</p>',
                'days_offset' => 0
            ],
            [
                'target_event_key' => 'new_year',
                'title' => 'Neujahr - Neue Impulse',
                'subject' => '✨ 365 neue Chancen und ein kleiner Funke Glück',
                'content' => '<h1>Ein frohes neues Jahr, {first_name}!</h1><p>Wir wünschen dir ein funkelndes neues Jahr voller Achtsamkeit. Zeit, neue Vorsätze in Stein... oder lieber in Kristall zu meißeln?</p>',
                'days_offset' => 0
            ],
        ];

        foreach ($templates as $t) {
            NewsletterTemplate::updateOrCreate(
                ['target_event_key' => $t['target_event_key']], // Verhindert Duplikate beim Re-Seeden
                [
                    'id' => Str::uuid(), // Nur falls neu erstellt wird
                    'title' => $t['title'],
                    'subject' => $t['subject'],
                    'content' => $t['content'],
                    'days_offset' => $t['days_offset'],
                    'is_active' => true,
                ]
            );
        }
    }
}
