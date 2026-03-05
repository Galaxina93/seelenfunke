<?php

namespace Database\Seeders;

use App\Models\Newsletter\Newsletter;
use Illuminate\Database\Seeder;

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
                'subject' => '💘 Valentinstag: Schenk etwas für die Ewigkeit',
                'content' => <<<HTML
<h1>Hallo {first_name},</h1>
<p>Hand aufs Herz: Rote Rosen sind wunderschön, aber leider nach einer Woche schon verblüht. Und Pralinen? Die halten meistens nicht mal bis zum nächsten Tag. 🍫</p>
<p>Wie wäre es dieses Jahr mit einer kleinen, aber feinen Aufmerksamkeit, die wirklich bleibt? In unserer Manufaktur fertigen wir persönliche Erinnerungsstücke. Keine Massenware, sondern echte Unikate, die wir mit viel Sorgfalt für dich personalisieren.</p>
<p>Lass uns gemeinsam dafür sorgen, dass dieser Tag in besonderer Erinnerung bleibt.</p>
<br>
<p><em>P.S.: Weil wir jedes Stück von Hand veredeln und der Versand ein paar Tage dauern kann, sagen wir dir lieber heute schon Bescheid. So kannst du ganz in Ruhe aussuchen und hältst dein Geschenk rechtzeitig zum Valentinstag in den Händen!</em></p>
<p>Liebe Grüße aus der Manufaktur,<br>Dein Mein-Seelenfunke Team</p>
HTML,
                'days_offset' => 14
            ],
            [
                'target_event_key' => 'womens_day',
                'title' => 'Weltfrauentag - Kampagne',
                'subject' => '💪 Weltfrauentag: Ein Hoch auf die Heldinnen',
                'content' => <<<HTML
<h1>Hallo {first_name},</h1>
<p>bald ist Weltfrauentag! Ein schöner Anlass, um einfach mal "Danke" zu sagen. Ob an die beste Freundin, die Mutter, eine Kollegin, die immer aushilft, oder ganz einfach an dich selbst.</p>
<p>Wir bei <strong>Mein-Seelenfunke</strong> lieben es, kleine Momente der Wertschätzung greifbar zu machen. Schau doch mal bei uns vorbei. Wir haben ein paar schöne Ideen vorbereitet, die von Herzen kommen und ewig halten.</p>
<br>
<p><em>P.S.: Damit dein Unikat auch pünktlich ankommt, schreiben wir dir heute schon. Nimm dir die Zeit, die du brauchst, um in Ruhe das Passende zu finden!</em></p>
<p>Herzliche Grüße!</p>
HTML,
                'days_offset' => 12
            ],
            [
                'target_event_key' => 'easter',
                'title' => 'Ostern - Kampagne',
                'subject' => '🐰 Ostern: Wir haben da etwas für dein Nest',
                'content' => <<<HTML
<h1>Hallo {first_name},</h1>
<p>der Frühling kündigt sich an und das Osterfest steht fast vor der Tür. Während die Schokohasen schon überall in den Regalen stehen, haben wir in der Werkstatt eine etwas beständigere Alternative vorbereitet.</p>
<p>Egal, ob du eine kleine Überraschung für das Familiennest suchst oder eine edle Dekoration für den Ostertisch. Bei uns findest du personalisierte Stücke, die garantiert nicht in der Frühlingssonne wegschmelzen.</p>
<br>
<p><em>P.S.: Da die Osterpost manchmal etwas länger braucht und wir für die Gravur ein paar Tage einplanen, erinnern wir dich lieber rechtzeitig. So liegt am Ende auch wirklich alles pünktlich im Versteck!</em></p>
<p>Frühlingshafte Grüße,<br>Dein Team von Mein-Seelenfunke</p>
HTML,
                'days_offset' => 14
            ],

            // --- SOMMER & FAMILIE ---
            [
                'target_event_key' => 'mothers_day',
                'title' => 'Muttertag - Kampagne',
                'subject' => '💐 Muttertag: Ein ganz persönliches Dankeschön',
                'content' => <<<HTML
<h1>Hallo {first_name},</h1>
<p>Mütter sind oft unser Fels in der Brandung. Sie sind einfach immer da, hören zu und haben meistens recht.</p>
<p>Statt dem klassischen Blumenstrauß haben wir eine Idee für dich: Schenk ihr in diesem Jahr doch eine Erinnerung, die für immer bleibt. Eine persönliche Gravur auf Glas oder Schiefer sagt mehr als tausend Worte und hält ein Leben lang.</p>
<br>
<p><em>P.S.: Der Muttertag ist für uns eine besonders arbeitsreiche Zeit. Deshalb melden wir uns jetzt schon bei dir. So kannst du dein Geschenk in aller Ruhe gestalten und wir können es rechtzeitig und mit der gewohnten Liebe anfertigen.</em></p>
<p>Liebe Grüße aus der Werkstatt</p>
HTML,
                'days_offset' => 14
            ],
            [
                'target_event_key' => 'fathers_day',
                'title' => 'Vatertag - Kampagne',
                'subject' => '🛠️ Vatertag: Handfestes für echte Typen',
                'content' => <<<HTML
<h1>Hallo {first_name},</h1>
<p>ob als stiller Zuhörer, handwerklicher Notdienst oder Ratgeber in allen Lebenslagen: Väter machen einen verdammt guten Job.</p>
<p>Dieses Jahr kannst du ihn mit etwas überraschen, das genauso beständig ist wie er selbst. Ein massives Unikat aus unserer Manufaktur, ganz ohne Sockenmuster oder Krawattennadeln. Einfach ein ehrliches, robustes Geschenk, das auf dem Schreibtisch oder im Regal richtig gut aussieht.</p>
<br>
<p><em>P.S.: Wir wollen sichergehen, dass dein Geschenk rechtzeitig zur Bollerwagentour oder zum gemeinsamen Grillen ankommt. Deswegen erinnern wir dich heute schon daran!</em></p>
<p>Viele Grüße!</p>
HTML,
                'days_offset' => 14
            ],
            [
                'target_event_key' => 'sale_summer',
                'title' => 'Sommer Sale - Kampagne',
                'subject' => '☀️ Sommer-Sale: Erfrischende Angebote',
                'content' => <<<HTML
<h1>Hallo {first_name},</h1>
<p>die Temperaturen steigen, die Laune auch! Passend zur sonnigen Jahreszeit räumen wir unser Lager etwas auf, um Platz für neue Ideen im Herbst zu schaffen.</p>
<p>Das ist die perfekte Gelegenheit für dich. Sichere dir unsere personalisierten Unikate zu einem wirklich erfrischenden Preis. Ob als Mitbringsel zur nächsten Gartenparty oder einfach für dich selbst – schau doch mal rein, was wir reduziert haben.</p>
<p>Hab einen wundervollen Sommer!</p>
<p>Sonnige Grüße,<br>Dein Team von Mein-Seelenfunke</p>
HTML,
                'days_offset' => 0
            ],

            // --- HERBST & WINTER ---
            [
                'target_event_key' => 'halloween',
                'title' => 'Halloween - Kampagne',
                'subject' => '🎃 Es wird früh dunkel: Zeit für etwas Licht',
                'content' => <<<HTML
<h1>Hallo {first_name},</h1>
<p>die Tage werden wieder merklich kürzer und die Abende gemütlicher. Genau die richtige Zeit, um drinnen ein schönes Licht anzuzünden.</p>
<p>Egal, ob du Halloween feierst oder einfach nur den Herbst liebst: Unsere beleuchteten Glas-Unikate bringen eine wunderbare, warme Atmosphäre in den Raum. Das sanfte Licht bricht sich elegant in der Gravur und sorgt für absolute Gemütlichkeit.</p>
<br>
<p><em>P.S.: Wir melden uns bewusst heute schon bei dir, damit dein neues Lieblingsstück pünktlich zu den ersten richtig kühlen Herbstabenden (oder der Gruselnacht) bei dir eintrifft.</em></p>
<p>Mach es dir schön!</p>
HTML,
                'days_offset' => 14
            ],
            [
                'target_event_key' => 'advent_1',
                'title' => '1. Advent - Kampagne',
                'subject' => '🕯️ Die Vorweihnachtszeit beginnt',
                'content' => <<<HTML
<h1>Hallo {first_name},</h1>
<p>ist es bei dir schon soweit? Die erste Tasse Tee, vielleicht schon Spekulatius und die Lichterketten hängen? Bei uns in der Manufaktur duftet es definitiv schon nach Weihnachten.</p>
<p>Wir wissen, wie stressig die Suche nach dem passenden Weihnachtsgeschenk manchmal sein kann. Deshalb laden wir dich ein: Lehn dich zurück, trink einen Schluck Tee und stöbere ganz entspannt durch unseren Shop. Bei uns findest du Unikate, die wirklich von Herzen kommen und nicht von der Stange sind.</p>
<br>
<p><em>P.S.: Die Adventszeit verfliegt immer schneller, als man denkt! Wir erinnern dich heute schon daran, damit du dem ganzen Lieferstress in ein paar Wochen ganz gelassen aus dem Weg gehen kannst.</em></p>
<p>Eine besinnliche Zeit wünscht dir<br>Dein Team von Mein-Seelenfunke</p>
HTML,
                'days_offset' => 14
            ],
            [
                'target_event_key' => 'christmas',
                'title' => 'Weihnachten - Kampagne',
                'subject' => '🎄 Weihnachten: Schenke dieses Jahr eine Erinnerung',
                'content' => <<<HTML
<h1>Hallo {first_name},</h1>
<p>es ist das Fest der Liebe und der gemeinsamen Momente. Gibt es etwas Schöneres, als an Heiligabend in leuchtende Augen zu blicken, wenn ein Geschenk ausgepackt wird, das eine echte Bedeutung hat?</p>
<p>Wir haben unsere Maschinen poliert und reichlich Verpackungsmaterial vorbereitet. Wir sind bereit, deine schönsten Momente in Glas oder Schiefer zu verewigen.</p>
<br>
<p><strong>Ein ehrlicher Tipp aus der Werkstatt:</strong> Weihnachten ist unsere intensivste Zeit des Jahres. Wenn du dein Geschenk jetzt schon gestaltest und bestellst, hilfst du uns enorm bei der Planung – und du hast die absolute Sicherheit, dass dein Paket pünktlich und ohne den üblichen Paketdienst-Stress unter dem Baum liegt.</p>
<p>Wir freuen uns darauf, etwas Besonderes für dich anzufertigen!</p>
HTML,
                'days_offset' => 21
            ],
            [
                'target_event_key' => 'sale_winter',
                'title' => 'Winter Sale - Kampagne',
                'subject' => '❄️ Winter-Sale: Zeit für etwas Schönes',
                'content' => <<<HTML
<h1>Hallo {first_name},</h1>
<p>wir hoffen, du bist gut und gesund ins neue Jahr gestartet! Draußen ist es kalt und ungemütlich – der perfekte Moment, um vom Sofa aus ein bisschen zu stöbern.</p>
<p>Wir machen Inventur und haben unseren großen Winter-Sale gestartet. Sichere dir jetzt handgefertigte Qualität zu stark reduzierten Preisen. Vielleicht um dir selbst eine Freude zu machen oder um schon mal völlig entspannt für die ersten Geburtstage des Jahres vorzusorgen?</p>
<p>Wir laden dich herzlich ein, durch unsere Angebote zu scrollen.</p>
<p>Liebe Grüße!</p>
HTML,
                'days_offset' => 0
            ],
            [
                'target_event_key' => 'new_year',
                'title' => 'Neujahr - Kampagne',
                'subject' => '🎆 Willkommen im neuen Jahr!',
                'content' => <<<HTML
<h1>Hallo {first_name},</h1>
<p>365 neue Tage liegen vor uns. Ein ganzes Jahr voller neuer Chancen, Herausforderungen und vor allem schöner Momente.</p>
<p>Wir wünschen dir für die kommende Zeit viel Gesundheit, Zufriedenheit und Momente, die es wert sind, festgehalten zu werden.</p>
<p>Gibt es ein Motto für dein neues Jahr? Oder ein bestimmtes Ziel? Manchmal hilft es, sich solche Dinge "in Stein" (oder Glas) gravieren zu lassen und sie sich auf den Schreibtisch zu stellen, um sie nicht aus den Augen zu verlieren.</p>
<p>Auf ein wunderbares, strahlendes neues Jahr!</p>
<p>Dein Mein-Seelenfunke Team</p>
HTML,
                'days_offset' => 0
            ],
        ];

        foreach ($templates as $t) {
            Newsletter::updateOrCreate(
                ['target_event_key' => $t['target_event_key']],
                [
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
