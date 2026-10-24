<?php

namespace Database\Seeders;

use App\Models\Marketing\MarketingNewsletter;
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
                'subject' => '💘 Valentinstag: Schenke etwas, das nicht nach einer Woche verblüht (+ Aktionsvorteil)',
                'content' => <<<HTML
<h2 style="color: #1f2937; font-family: Georgia, serif; font-size: 22px; margin-bottom: 16px;">Hallo {first_name},</h2>
<p>Hand aufs Herz: Rote Rosen sind wunderschön, aber leider schon nach wenigen Tagen verblüht. Und Pralinen? Die überleben meistens nicht einmal das erste Wochenende. 🍫</p>
<p>Wie wäre es in diesem Jahr mit einer Geste, die wirklich für die Ewigkeit bleibt? In unserer Manufaktur veredeln wir echte Erinnerungsstücke – vom funkelnden <strong>Seelen-Kristall mit 3D-Innengravur</strong> bis zum massiven, handschmeichelnden <strong>Seelenanhänger in Herzform</strong> mit deiner persönlichen Botschaft auf Vorder- und Rückseite.</p>
<div style="background-color: #fdfaf3; border-left: 4px solid #C5A059; padding: 14px 18px; margin: 22px 0; border-radius: 0 12px 12px 0;">
    <strong style="color: #9a742c; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">✨ Unser Valentinstags-Vorteil für dich:</strong><br>
    <span style="color: #4b5563; font-size: 13.5px;">Passend zur Vorbereitungszeit haben wir unsere beliebtesten Liebes- & Paar-Unikate im Shop automatisch für dich im Aktionspreis reduziert – solange unser Manufaktur-Kontingent vor dem Valentinstag reicht!</span>
</div>
<p><em>P.S.: Weil wir jedes Stück einzeln für dich gravieren und sorgfältig verpacken, erinnern wir dich bewusst heute schon. So gestaltest du dein Unikat ganz entspannt zum Vorzugspreis und hältst es garantiert pünktlich zum 14. Februar in den Händen.</em></p>
<p style="margin-top: 20px;">Herzliche Grüße aus der Manufaktur,<br><strong>Dein Mein-Seelenfunke Team</strong> ✨</p>
HTML,
                'days_offset' => 14
            ],
            [
                'target_event_key' => 'womens_day',
                'title' => 'Weltfrauentag - Kampagne',
                'subject' => '💐 Weltfrauentag am 08. März: Ein bleibendes Dankeschön für besondere Frauen',
                'content' => <<<HTML
<h2 style="color: #1f2937; font-family: Georgia, serif; font-size: 22px; margin-bottom: 16px;">Hallo {first_name},</h2>
<p>am 8. März ist Weltfrauentag – ein wunderbarer Anlass, um einfach mal von Herzen „Danke" zu sagen. Ob an die beste Freundin, die Mama, die Partnerin oder ganz bewusst als kleine Auszeichnung an dich selbst.</p>
<p>Bei <strong>Mein-Seelenfunke</strong> machen wir solche Momente der Wertschätzung greifbar: Mit einer feinen, unvergänglichen Lasergravur in Kristallglas oder unserem massiven Aluminium-Herz entsteht in wenigen Klicks im 3D-Konfigurator ein echtes Einzelstück.</p>
<div style="background-color: #fdfaf3; border-left: 4px solid #C5A059; padding: 14px 18px; margin: 22px 0; border-radius: 0 12px 12px 0;">
    <strong style="color: #9a742c; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">💐 Weltfrauentags-Aktion im Shop aktiv:</strong><br>
    <span style="color: #4b5563; font-size: 13.5px;">Sichere dir jetzt unsere ausgewählten Geschenk-Unikate zum reduzierten Aktionspreis direkt im Online-Shop.</span>
</div>
<p><em>P.S.: Damit dein persönliches Einzelstück rechtzeitig gefertigt und geliefert wird, schreiben wir dir schon heute. Nimm dir einen Moment Zeit und gestalte deine Wunschgravur ganz in Ruhe!</em></p>
<p style="margin-top: 20px;">Herzliche Grüße aus der Werkstatt,<br><strong>Dein Mein-Seelenfunke Team</strong> ✨</p>
HTML,
                'days_offset' => 12
            ],
            [
                'target_event_key' => 'easter',
                'title' => 'Ostern - Kampagne',
                'subject' => '🐰 Ostern steht vor der Tür: Eine Überraschung fürs Nest, die nicht schmilzt',
                'content' => <<<HTML
<h2 style="color: #1f2937; font-family: Georgia, serif; font-size: 22px; margin-bottom: 16px;">Hallo {first_name},</h2>
<p>der Frühling kündigt sich an und das Osterfest rückt näher! Während die Schokohasen schon überall in den Regalen stehen, haben wir in unserer Werkstatt eine deutlich beständigere Überraschung für das Osternest vorbereitet.</p>
<p>Egal, ob du ein persönliches Familiengeschenk im <strong>Seelen-Kristall</strong> festhalten möchtest, einen edlen <strong>Seelenanhänger</strong> als Glücksbringer suchst oder den <strong>Feierabend-Funke</strong> zum ersten Frühlings-Anstoßen gestaltest – unsere Gravuren schmelzen garantiert nicht in der Frühlingssonne.</p>
<div style="background-color: #fdfaf3; border-left: 4px solid #C5A059; padding: 14px 18px; margin: 22px 0; border-radius: 0 12px 12px 0;">
    <strong style="color: #9a742c; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">🌸 Oster-Manufaktur-Vorteil:</strong><br>
    <span style="color: #4b5563; font-size: 13.5px;">Während unserer Oster-Vorbestellerphase profitierst du im Shop automatisch von reduzierten Aktionspreisen auf unsere personalisierbaren Unikate.</span>
</div>
<p><em>P.S.: Da die Osterpost erfahrungsgemäß viel zu tun hat und jedes Stück einzeln gelasert wird, lohnt sich das frühe Gestalten doppelt: Du sparst im Aktionszeitraum und hast alles pünktlich im Versteck!</em></p>
<p style="margin-top: 20px;">Frühlingshafte Grüße,<br><strong>Dein Mein-Seelenfunke Team</strong> ✨</p>
HTML,
                'days_offset' => 14
            ],

            // --- SOMMER & FAMILIE ---
            [
                'target_event_key' => 'mothers_day',
                'title' => 'Muttertag - Kampagne',
                'subject' => '🌷 Bald ist Muttertag: Schenke Mama eine Erinnerung für die Ewigkeit',
                'content' => <<<HTML
<h2 style="color: #1f2937; font-family: Georgia, serif; font-size: 22px; margin-bottom: 16px;">Hallo {first_name},</h2>
<p>Mütter sind unser Fels in der Brandung. Sie sind einfach immer da, hören zu, fiebern mit und halten die Familie zusammen.</p>
<p>Statt dem klassischen Blumenstrauß von der Tankstelle oder vom Floristen haben wir eine Idee, die ihr jedes Mal ein Lächeln ins Gesicht zaubert, wenn sie daran vorbeigeht: Eine persönliche Gravur im edlen <strong>Seelen-Kristall</strong> oder auf unserem massiven <strong>Herz-Seelenanhänger</strong> sagt mehr als tausend Worte – und hält ein Leben lang.</p>
<div style="background-color: #fdfaf3; border-left: 4px solid #C5A059; padding: 14px 18px; margin: 22px 0; border-radius: 0 12px 12px 0;">
    <strong style="color: #9a742c; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">💝 Muttertags-Aktion im Shop gestartet:</strong><br>
    <span style="color: #4b5563; font-size: 13.5px;">Unsere beliebtesten Mama-Geschenke sind ab sofort für kurze Zeit im Shop reduziert. Gestalte dein Unikat direkt online mit unseren liebevollen Muttertags-Vorlagen!</span>
</div>
<p><em>P.S.: Der Muttertag gehört zu den arbeitsreichsten Wochen in unserer Manufaktur. Wer jetzt frühzeitig bestellt, sichert sich nicht nur den Aktionspreis, sondern auch eine entspannte, pünktliche Lieferung.</em></p>
<p style="margin-top: 20px;">Liebe Grüße aus der Werkstatt,<br><strong>Dein Mein-Seelenfunke Team</strong> ✨</p>
HTML,
                'days_offset' => 14
            ],
            [
                'target_event_key' => 'fathers_day',
                'title' => 'Vatertag - Kampagne',
                'subject' => '🍺 Vatertag voraus: Handfeste Unikate für echte Typen (+ Aktionspreis)',
                'content' => <<<HTML
<h2 style="color: #1f2937; font-family: Georgia, serif; font-size: 22px; margin-bottom: 16px;">Hallo {first_name},</h2>
<p>ob als stiller Zuhörer, handwerklicher Notdienst oder ehrlicher Ratgeber in allen Lebenslagen: Väter machen einen verdammt guten Job.</p>
<p>Überrasche ihn in diesem Jahr mit etwas, das genauso viel Charakter hat wie er selbst – ganz ohne langweilige Socken oder Krawatten. Unser absoluter Favorit zum Vatertag: <strong>Der Feierabend-Funke</strong>, unser massives Kristall-Weizenbierglas mit spülmaschinenfester 360°-Rundum-Lasergravur, oder der schwere <strong>Seelenanhänger</strong> für den Schlüsselbund.</p>
<div style="background-color: #fdfaf3; border-left: 4px solid #C5A059; padding: 14px 18px; margin: 22px 0; border-radius: 0 12px 12px 0;">
    <strong style="color: #9a742c; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">🛠️ Vatertags-Special jetzt live:</strong><br>
    <span style="color: #4b5563; font-size: 13.5px;">Sichere dir den „Feierabend-Funke" und ausgewählte Geschenke jetzt zum reduzierten Vatertags-Aktionspreis direkt im Shop!</span>
</div>
<p><em>P.S.: Damit das gute Stück rechtzeitig zum gemeinsamen Grillen oder zur Vatertagstour einsatzbereit ist, legen wir dir die Bestellung heute schon ans Herz.</em></p>
<p style="margin-top: 20px;">Herzliche Grüße aus der Manufaktur,<br><strong>Dein Mein-Seelenfunke Team</strong> ✨</p>
HTML,
                'days_offset' => 14
            ],
            [
                'target_event_key' => 'sale_summer',
                'title' => 'Sommer Sale - Kampagne',
                'subject' => '☀️ Großer Sommer-Sale: Erfrischende Aktionspreise auf unsere Unikate',
                'content' => <<<HTML
<h2 style="color: #1f2937; font-family: Georgia, serif; font-size: 22px; margin-bottom: 16px;">Hallo {first_name},</h2>
<p>die Temperaturen steigen, die Abende auf Balkon und Terrasse werden länger! Passend zur sonnigen Jahreszeit schaffen wir in unserem Manufaktur-Lager Platz für neue Ideen im Herbst.</p>
<p>Für dich ist das die beste Gelegenheit des Sommers: Ob ein graviertes Weizenglas für die nächste Grillparty, ein funkelnder Kristall für anstehende Sommer-Hochzeiten und Geburtstage oder ein Unikat für dich selbst – wir haben unsere Bestseller kräftig reduziert.</p>
<div style="background-color: #fdfaf3; border-left: 4px solid #C5A059; padding: 14px 18px; margin: 22px 0; border-radius: 0 12px 12px 0;">
    <strong style="color: #9a742c; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">☀️ Sommer-Sale (Nur für kurze Zeit):</strong><br>
    <span style="color: #4b5563; font-size: 13.5px;">Im gesamten Shop findest du ab sofort unsere Sommer-Streichpreise auf alle vorrätigen Gravur-Unikate – ganz ohne Gutscheincode direkt im Warenkorb abgezogen.</span>
</div>
<p>Hab einen wundervollen, sonnigen Sommer und viel Freude beim Stöbern!</p>
<p style="margin-top: 20px;">Sonnige Grüße,<br><strong>Dein Mein-Seelenfunke Team</strong> ✨</p>
HTML,
                'days_offset' => 0
            ],

            // --- HERBST & WINTER ---
            [
                'target_event_key' => 'halloween',
                'title' => 'Halloween - Kampagne',
                'subject' => '🎃 Herbst- & Lichterzauber: Wenn aus Glas echte Magie wird (+ Aktion)',
                'content' => <<<HTML
<h2 style="color: #1f2937; font-family: Georgia, serif; font-size: 22px; margin-bottom: 16px;">Hallo {first_name},</h2>
<p>die Tage werden wieder merklich kürzer, draußen raschelt das Herbstlaub und drinnen beginnt die gemütlichste Zeit des Jahres.</p>
<p>Egal, ob du Halloween liebst oder es dir einfach zu Hause warm und stimmungsvoll machen möchtest: Unsere fein gravierten Kristall- und Glas-Unikate fangen jedes Kerzen- und Raumlicht ein und brechen es in tausend feinen Facetten.</p>
<div style="background-color: #fdfaf3; border-left: 4px solid #C5A059; padding: 14px 18px; margin: 22px 0; border-radius: 0 12px 12px 0;">
    <strong style="color: #9a742c; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">🍂 Herbst-Lichter-Aktion im Shop:</strong><br>
    <span style="color: #4b5563; font-size: 13.5px;">Entdecke jetzt unsere herbstlichen Aktionspreise auf ausgewählte Kristall- und Glas-Unikate direkt im Shop.</span>
</div>
<p><em>P.S.: Auch als frühe Geschenk-Idee für Herbst-Geburtstage perfekt geeignet – wir gravieren dein Wunschmotiv sofort nach deiner Bestellung!</em></p>
<p style="margin-top: 20px;">Gemütliche Herbstgrüße,<br><strong>Dein Mein-Seelenfunke Team</strong> ✨</p>
HTML,
                'days_offset' => 14
            ],
            [
                'target_event_key' => 'advent_1',
                'title' => '1. Advent - Kampagne',
                'subject' => '🕯️ Bald ist 1. Advent: Entspannt vor dem Weihnachtstrubel bestellen (+ Frühbucher-Vorteil)',
                'content' => <<<HTML
<h2 style="color: #1f2937; font-family: Georgia, serif; font-size: 22px; margin-bottom: 16px;">Hallo {first_name},</h2>
<p>die erste Tasse heißer Tee, der Duft nach Zimt und die ersten Lichterketten im Fenster: Bei uns in der Manufaktur läuft die Vorweihnachtszeit bereits auf Hochtouren.</p>
<p>Wir wissen, wie stressig die Geschenkesuche im Dezember werden kann. Deshalb möchten wir es dir in diesem Jahr besonders leicht machen: Lehn dich entspannt zurück und gestalte deine Weihnachtsgeschenke schon jetzt zum Beginn der Adventszeit – ganz ohne Hektik.</p>
<div style="background-color: #fdfaf3; border-left: 4px solid #C5A059; padding: 14px 18px; margin: 22px 0; border-radius: 0 12px 12px 0;">
    <strong style="color: #9a742c; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">🕯️ Advents-Frühbucher-Vorteil:</strong><br>
    <span style="color: #4b5563; font-size: 13.5px;">Weil frühe Bestellungen unsere Werkstatt vor Weihnachten enorm entlasten, bedanken wir uns ab sofort mit unserem automatischen Advents-Aktionspreis direkt im Shop!</span>
</div>
<p><em>P.S.: Sichere dir jetzt deinen Platz in unserer Fertigung und genieße einen wundervoll entspannten Advent, während wir deine Geschenke mit höchster Sorgfalt veredeln.</em></p>
<p style="margin-top: 20px;">Eine besinnliche Vorweihnachtszeit wünscht dir<br><strong>Dein Mein-Seelenfunke Team</strong> ✨</p>
HTML,
                'days_offset' => 14
            ],
            [
                'target_event_key' => 'christmas',
                'title' => 'Weihnachten - Kampagne',
                'subject' => '🎄 Weihnachten: Schenke ein Unikat, das Herzen berührt (+ Weihnachts-Aktion)',
                'content' => <<<HTML
<h2 style="color: #1f2937; font-family: Georgia, serif; font-size: 22px; margin-bottom: 16px;">Hallo {first_name},</h2>
<p>gibt es an Heiligabend etwas Schöneres, als in leuchtende Augen zu blicken, wenn unter dem Baum ein Geschenk ausgepackt wird, das wirklich eine persönliche Bedeutung trägt?</p>
<p>Unsere Lasermaschinen sind poliert und die edlen Geschenkboxen stehen bereit: Ob der massive <strong>Seelen-Kristall</strong> für die Familie, der <strong>Seelenanhänger</strong> für den Lieblingsmenschen oder das gravierte <strong>Weizenglas</strong> – wir fertigen dein persönliches Weihnachtswunder.</p>
<div style="background-color: #fdfaf3; border-left: 4px solid #C5A059; padding: 14px 18px; margin: 22px 0; border-radius: 0 12px 12px 0;">
    <strong style="color: #9a742c; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">🎄 Weihnachts-Manufaktur-Aktion aktiv:</strong><br>
    <span style="color: #4b5563; font-size: 13.5px;">Bestelle jetzt rechtzeitig vor dem großen Paketdienst-Endspurt und profitiere im Shop automatisch von unseren Weihnachts-Aktionspreisen!</span>
</div>
<p><strong>Ein ehrlicher Tipp aus der Werkstatt:</strong> Weihnachten ist unsere intensivste Zeit des Jahres. Wenn du dein Unikat jetzt gestaltest, garantieren wir dir eine entspannte Fertigung und pünktliche Lieferung bis unter den Weihnachtsbaum.</p>
<p style="margin-top: 20px;">Festliche Grüße aus der Manufaktur,<br><strong>Dein Mein-Seelenfunke Team</strong> ✨</p>
HTML,
                'days_offset' => 21
            ],
            [
                'target_event_key' => 'new_year',
                'title' => 'Neujahr - Kampagne',
                'subject' => '🎆 Frohes neues Jahr! Halte deine schönsten Ziele & Momente in Kristall fest',
                'content' => <<<HTML
<h2 style="color: #1f2937; font-family: Georgia, serif; font-size: 22px; margin-bottom: 16px;">Hallo {first_name},</h2>
<p>365 neue Tage liegen vor uns – ein ganzes Jahr voller neuer Chancen, Pläne und unvergesslicher Momente. Wir wünschen dir von Herzen Gesundheit, Glück und wundervolle Augenblicke im neuen Jahr!</p>
<p>Hast du ein besonderes Lebensmotto für dieses Jahr oder steht schon der erste runde Geburtstag, ein Jubiläum oder eine Hochzeit im Kalender? Manchmal hilft es, bedeutende Worte buchstäblich in Kristall oder Metall gravieren zu lassen, um sie jeden Tag vor Augen zu haben.</p>
<div style="background-color: #fdfaf3; border-left: 4px solid #C5A059; padding: 14px 18px; margin: 22px 0; border-radius: 0 12px 12px 0;">
    <strong style="color: #9a742c; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">🎆 Unser Neujahrs-Startvorteil zum Jahresbeginn:</strong><br>
    <span style="color: #4b5563; font-size: 13.5px;">Starte glänzend ins neue Jahr: In der ersten Januarwoche sind unsere Kristall- & Herz-Unikate im Shop automatisch für dich reduziert.</span>
</div>
<p style="margin-top: 20px;">Auf ein strahlendes neues Jahr,<br><strong>Dein Mein-Seelenfunke Team</strong> ✨</p>
HTML,
                'days_offset' => 0
            ],
            [
                'target_event_key' => 'sale_winter',
                'title' => 'Winter Sale - Kampagne',
                'subject' => '❄️ Großer Winter- & Inventur-Sale: Jetzt Manufaktur-Unikate zum Vorteilspreis sichern',
                'content' => <<<HTML
<h2 style="color: #1f2937; font-family: Georgia, serif; font-size: 22px; margin-bottom: 16px;">Hallo {first_name},</h2>
<p>draußen ist es kalt und winterlich – der perfekte Moment, um es sich mit einer heißen Tasse auf dem Sofa gemütlich zu machen und entspannt zu stöbern.</p>
<p>Nach dem großen Weihnachtstrubel machen wir in unserer Manufaktur Inventur und haben unseren großen <strong>Winter-Sale</strong> gestartet. Ob du dir selbst eine Freude machen möchtest oder schon ganz entspannt für die ersten Geburtstage, Hochzeiten oder den Valentinstag vorsorgen willst: Jetzt ist der beste Zeitpunkt dafür!</p>
<div style="background-color: #fdfaf3; border-left: 4px solid #C5A059; padding: 14px 18px; margin: 22px 0; border-radius: 0 12px 12px 0;">
    <strong style="color: #9a742c; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">❄️ Winter-Sale im Shop aktiv:</strong><br>
    <span style="color: #4b5563; font-size: 13.5px;">Sichere dir jetzt handgefertigte Manufaktur-Qualität zu unseren stärksten Winter-Streichpreisen direkt im Shop – solange unser Aktions-Kontingent reicht.</span>
</div>
<p style="margin-top: 20px;">Winterliche Grüße aus der Werkstatt,<br><strong>Dein Mein-Seelenfunke Team</strong> ✨</p>
HTML,
                'days_offset' => 0
            ],
        ];

        foreach ($templates as $t) {
            MarketingNewsletter::updateOrCreate(
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
