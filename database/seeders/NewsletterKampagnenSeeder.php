<?php

namespace Database\Seeders;

use App\Models\FunkiNewsletter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NewsletterKampagnenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            // --- FRÜHJAHR ---
            [
                'target_event_key' => 'valentines',
                'title' => 'Valentinstag - Kampagne',
                'subject' => '💘 Valentinstag: Schenk was für die Ewigkeit!',
                'content' => '<h1>Hallo {first_name},</h1>
                <p>Hand aufs Herz: Rosen verwelken nach einer Woche und Schokolade... nun ja, die hält meistens keine 10 Minuten. 🍫</p>
                <p>Wie wäre es dieses Jahr mit einem Liebesbeweis, der genau so beständig ist wie eure Verbindung? In unserer Manufaktur fertigen wir Dinge, die bleiben. Ein Funkeln, das nicht verblasst.</p>
                <p>Wir haben unsere Werkstatt auf "Romantik-Modus" geschaltet und warten nur darauf, deine schönste Erinnerung für die Ewigkeit festzuhalten.</p>
                <p><strong>Lass uns gemeinsam Augen zum Leuchten bringen!</strong></p>
                <p>Viel Liebe,<br>Dein Funki & das Team</p>',
                'days_offset' => 14
            ],
            [
                'target_event_key' => 'womens_day',
                'title' => 'Weltfrauentag - Kampagne',
                'subject' => '💪 Weltfrauentag: Ein Hoch auf die Heldinnen!',
                'content' => '<h1>Huhu {first_name},</h1>
                <p>heute feiern wir Stärke, Eleganz und Mut! Egal ob du dich selbst belohnen möchtest (Self-Care ist wichtig!) oder einer inspirierenden Powerfrau in deinem Leben einfach mal "Danke" sagen willst.</p>
                <p>Bei <strong>Mein-Seelenfunke</strong> glauben wir daran, dass jeder Mensch ein inneres Strahlen hat. Unsere Unikate sind nur dazu da, es nach außen zu tragen.</p>
                <p>Schau doch mal rein – wir haben da ein paar Dinge vorbereitet, die genau so facettenreich sind wie das Leben selbst.</p>
                <p>Lass dich feiern!</p>',
                'days_offset' => 3
            ],
            [
                'target_event_key' => 'easter',
                'title' => 'Ostern - Kampagne',
                'subject' => '🐰 Ostern: Funki hat da was funkeln sehen...',
                'content' => '<h1>Frohe Ostern, {first_name}!</h1>
                <p>Der Frühling ist da! Während der Osterhase noch verzweifelt versucht, bunte Eier im hohen Gras zu verstecken, haben wir uns gedacht: Warum nicht mal etwas verstecken, das man garantiert findet – weil es strahlt?</p>
                <p>Funki war fleißig und hat die Manufaktur auf Hochglanz poliert. Ob als Geschenk für das Familienfest oder als edle Deko für den eigenen Ostertisch – bei uns findest du das gewisse Etwas, das garantiert nicht in der Sonne schmilzt.</p>
                <p>Hoppelnde Grüße,<br>Dein Seelenfunke-Team</p>',
                'days_offset' => 10
            ],

            // --- SOMMER & FAMILIE ---
            [
                'target_event_key' => 'mothers_day',
                'title' => 'Muttertag - Kampagne',
                'subject' => '💐 Muttertag: Weil "Danke" manchmal glitzern muss',
                'content' => '<h1>Liebe/r {first_name},</h1>
                <p>Mamas sind wie Kristalle: Sie sind stark, haben viele Facetten und bringen Licht in unser Leben, wenn es mal dunkel wird.</p>
                <p>Statt der üblichen Pralinenschachtel (die Papa eh zur Hälfte isst 😉), wie wäre es mit einer Erinnerung, die für immer bleibt? Wir gravieren deine Dankbarkeit so tief ein, dass sie niemals verblasst.</p>
                <p>Sichere dir jetzt dein Unikat für die beste Mama der Welt – damit es auch pünktlich ankommt!</p>',
                'days_offset' => 14
            ],
            [
                'target_event_key' => 'fathers_day',
                'title' => 'Vatertag - Kampagne',
                'subject' => '🛠️ Vatertag: Für echte Typen mit weichem Kern',
                'content' => '<h1>Hallo {first_name},</h1>
                <p>Väter sind oft unser Fels in der Brandung. Sie reparieren Dinge, geben (meistens) gute Ratschläge und sind einfach da.</p>
                <p>Schenke dieses Jahr etwas, das so massiv und beständig ist wie seine Unterstützung. Ein Unikat aus unserer Manufaktur ist robust, edel und garantiert ohne Krawatten-Muster.</p>
                <p>Überrasche ihn mit etwas, das er stolz auf den Schreibtisch oder ins Regal stellen wird.</p>',
                'days_offset' => 10
            ],
            [
                'target_event_key' => 'sale_summer',
                'title' => 'Sommer Sale - Kampagne',
                'subject' => '☀️ Sommer-Sale: Die Preise schmelzen dahin!',
                'content' => '<h1>Sommer-Feeling pur, {first_name}!</h1>
                <p>Puh, ist das heiß draußen! 🥵 Aber keine Sorge, bei uns rieseln die Preise wie Schneeflocken.</p>
                <p>Wir räumen unser Lager auf und schaffen Platz für neue Ideen. Das ist deine Chance, dir echte Premium-Qualität zu einem Preis zu sichern, der so erfrischend ist wie ein Sprung in den Pool.</p>
                <p>Schnapp dir jetzt deine Lieblingsstücke und bringe das Sonnenlicht in deinem Zuhause so richtig zum Brechen. Aber nicht trödeln – was weg ist, ist weg!</p>',
                'days_offset' => 0
            ],

            // --- HERBST & WINTER ---
            [
                'target_event_key' => 'halloween',
                'title' => 'Halloween - Kampagne',
                'subject' => '🎃 Halloween: Süßes, Saures oder Funkelndes?',
                'content' => '<h1>Buuuuh, {first_name}! 👻</h1>
                <p>Die Tage werden kürzer, die Schatten länger. Genau die richtige Zeit, um ein Licht anzuzünden.</p>
                <p>Unsere beleuchteten Unikate vertreiben garantiert jeden Geist (oder laden die netten Geister zum Verweilen ein). Mach es dir gemütlich und entdecke, wie schön Licht und Glas in der dunklen Jahreszeit harmonieren.</p>
                <p>Kein Grusel, nur Glanz. Versprochen.</p>',
                'days_offset' => 7
            ],
            [
                'target_event_key' => 'advent_1',
                'title' => '1. Advent - Kampagne',
                'subject' => '🕯️ 1. Advent: Das erste Lichtlein brennt...',
                'content' => '<h1>Eine besinnliche Zeit, {first_name},</h1>
                <p>jetzt wird es gemütlich. Plätzchenduft liegt in der Luft und die ersten Lichterketten hängen. Auch bei uns in der Manufaktur läuft die Weihnachtsplaylist schon rauf und runter (Funki singt übrigens sehr laut mit 🎶).</p>
                <p>Suchst du noch nach dem perfekten, persönlichen Geschenk, das nicht "von der Stange" kommt? Bei uns findest du Unikate, die Augen zum Leuchten bringen – ganz ohne Einkaufsstress in vollen Innenstädten.</p>
                <p>Stöbere jetzt entspannt durch unseren Shop.</p>',
                'days_offset' => 2
            ],
            [
                'target_event_key' => 'christmas',
                'title' => 'Weihnachten - Kampagne',
                'subject' => '🎄 Weihnachten: Ein Päckchen voller Liebe',
                'content' => '<h1>Ho Ho Ho {first_name},</h1>
                <p>Weihnachten ist das Fest der Liebe und der Erinnerungen. Nichts ist schöner, als einen Moment festzuhalten und ihn unter den Baum zu legen.</p>
                <p>Funki und das ganze Team haben die Laser poliert und die Geschenkboxen gestapelt. Wir sind bereit!</p>
                <p><strong>Wichtiger Tipp:</strong> Gestalte jetzt dein ganz persönliches Weihnachtsgeschenk, bevor unsere Wichtel in den Weihnachtsurlaub gehen. So kommt alles pünktlich und stressfrei bei dir an.</p>
                <p>Wir wünschen dir eine zauberhafte Vorweihnachtszeit.</p>',
                'days_offset' => 20
            ],
            [
                'target_event_key' => 'sale_winter',
                'title' => 'Winter Sale - Kampagne',
                'subject' => '❄️ Winter-Sale: Frostig draußen, heiß im Warenkorb',
                'content' => '<h1>Brrr, ist das kalt, {first_name}!</h1>
                <p>Gut, dass man zum Shoppen nicht rausgehen muss. Wir machen Inventur und du kannst davon profitieren.</p>
                <p>Sichere dir jetzt funkelnde Schnäppchen im großen Winter-Sale von <strong>Mein-Seelenfunke</strong>. Perfekt, um sich selbst eine Freude zu machen oder schon mal ganz entspannt für die kommenden Geburtstage vorzusorgen.</p>
                <p>Kuschel dich in eine Decke und shoppe los!</p>',
                'days_offset' => 0
            ],
            [
                'target_event_key' => 'new_year',
                'title' => 'Neujahr - Kampagne',
                'subject' => '🎆 Neujahr: 3, 2, 1... Dein Jahr wird brillant!',
                'content' => '<h1>Ein frohes neues Jahr, {first_name}!</h1>
                <p>365 neue Tage. 365 neue Chancen. Wir wünschen dir ein Jahr voller Gesundheit, Glück und achtsamer Momente.</p>
                <p>Vielleicht möchtest du deine Vorsätze, dein Jahresmotto oder einen besonderen Wunsch für dieses Jahr nicht nur auf einen Zettel schreiben, sondern "in Stein" (bzw. Glas) meißeln?</p>
                <p><strong>Mein-Seelenfunke</strong> begleitet dich gerne in ein strahlendes neues Jahr. Auf alles, was kommt!</p>',
                'days_offset' => 0
            ],
        ];

        foreach ($templates as $t) {
            FunkiNewsletter::updateOrCreate(
                ['target_event_key' => $t['target_event_key']],
                [
                    'id' => Str::uuid(),
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
