<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MarketingBlogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Admin ID holen
        $adminId = DB::table('admins')->value('id');

        if (!$adminId) {
            $this->command->warn('Kein Admin in der Tabelle "admins" gefunden. Abbruch.');
            return;
        }

        // 2. Kategorien sicherstellen
        $catNewsId = DB::table('marketing_blog_categories')->where('slug', 'neuigkeiten')->value('id');
        if (!$catNewsId) {
            $catNewsId = Str::uuid()->toString();
            DB::table('marketing_blog_categories')->insert([
                'id' => $catNewsId,
                'name' => 'Neuigkeiten',
                'slug' => 'neuigkeiten',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $catHerzensId = DB::table('marketing_blog_categories')->where('slug', 'herzensprojekte')->value('id');
        if (!$catHerzensId) {
            $catHerzensId = Str::uuid()->toString();
            DB::table('marketing_blog_categories')->insert([
                'id' => $catHerzensId,
                'name' => 'Herzensprojekte',
                'slug' => 'herzensprojekte',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Blog Posts erstellen

        $posts = [
            // POST 1: Gründung & Maßstäbe (01.11.2026)
            [
                'id' => Str::uuid()->toString(),
                'user_id' => $adminId,
                'blog_category_id' => $catNewsId,
                'title' => 'Willkommen bei Mein-Seelenfunke! Wir setzen neue Maßstäbe!',
                'slug' => 'willkommen-bei-mein-seelenfunke',
                'excerpt' => 'Es ist soweit: Mein-Seelenfunke öffnet die Tore. Wir sind angetreten, um Emotionen in reinstes K9-Kristallglas zu bannen und Qualität neu zu definieren.',
                'content' => '
                    <p>Wir freuen uns riesig, euch endlich bei <strong>Mein-Seelenfunke</strong> begrüßen zu dürfen!</p>

                    <p>Hinter uns liegen Monate intensiver Vorbereitung, technologischer Feinabstimmung und purer Leidenschaft in unserer Gifhorner Manufaktur. Unser Ziel war es von Beginn an nicht, einfach nur einen weiteren Online-Shop zu eröffnen. Wir wollten einen Ort schaffen, an dem Geschenke wieder eine tiefe, bleibende Bedeutung erhalten.</p>

                    <h3>Unsere Philosophie: Fokus auf Perfektion</h3>
                    <p>In einer schnelllebigen Welt voller flüchtiger Massenware haben wir uns ganz bewusst auf ein außergewöhnliches Material spezialisiert: <strong>reines K9-Kristallglas</strong>. Wir kombinieren hochmoderne 3D-Lasertechnologie mit zertifiziertem Sachverstand und manueller Endkontrolle mit dem weißen Handschuh. Jedes Stück ist ein Unikat, das Emotionen für die Ewigkeit festhält.</p>

                    <h3>Handwerk mit Herz</h3>
                    <p>Doch technische Perfektion ist für uns nur die halbe Miete. „Mein-Seelenfunke“ ist ein echtes Herzensprojekt. Wir möchten nicht nur Freude in eure Wohnzimmer bringen, sondern auch unserer Region etwas zurückgeben. Was das genau bedeutet, erfahrt ihr in unseren kommenden Beiträgen.</p>

                    <p>Dies ist der Beginn einer wunderbaren Reise. Schön, dass ihr von Anfang an dabei seid!</p>
                ',
                'featured_image' => 'marketing/blog/mein-seelenfunke-logo.png',
                'status' => 'published',
                'published_at' => Carbon::parse('2026-11-01 10:00:00'),
                'meta_title' => 'Mein-Seelenfunke Gründung und Neue Maßstäbe für Geschenke',
                'meta_description' => 'Mein-Seelenfunke ist gestartet. Erfahre mehr über unsere Vision, hochwertige Kristallglas-Geschenke und unsere Philosophie.',
                'meta_keywords' => 'Gründung, Vision, Seelenfunke, Qualität, K9 Kristall, Gifhorn',
                'is_advertisement' => false,
                'author_name' => 'Mein Seelenfunke',
                'created_at' => Carbon::parse('2026-11-01 10:00:00'),
                'updated_at' => now(),
            ],

            // POST 2: Funki Funke (04.11.2026)
            [
                'id' => Str::uuid()->toString(),
                'user_id' => $adminId,
                'blog_category_id' => $catNewsId,
                'title' => 'Gestatten: Funki Funke! Mehr als nur ein Maskottchen',
                'slug' => 'gestatten-funki-funke',
                'excerpt' => 'Dürfen wir vorstellen? Funki Funke ist da! Er ist das freundliche Gesicht hinter unserer Technik und freut sich darauf, euch kennenzulernen.',
                'content' => '
                    <p>Hallo zusammen! 👋</p>

                    <p>Es wird Zeit, das wohl bestgehütete Geheimnis unseres Teams zu lüften. Wir haben Zuwachs bekommen, und er ist... nun ja, ziemlich strahlend und voller Energie!</p>

                    <h3>Wer ist Funki?</h3>
                    <p>Funki ist unser lebendig gewordenes Laser-Maskottchen mit dem passenden Namen <strong>Funki Funke</strong> (der Nachname ist natürlich Ehrensache 😉). Doch Funki ist weit mehr als eine hübsche Zeichnung für Social Media: Er verkörpert die Seele unseres High-Tech-Workflows.</p>

                    <p>Überall dort, wo im Shop, in der Manufaktur und in der Kundenkommunikation intelligente Automatisierung greift, hat Funki seine Finger im Spiel:</p>
                    <ul>
                        <li>Er begleitet deine Bestellung vom ersten Pixel bis zur Endpolitur im Laser.</li>
                        <li>Er informiert dich stets transparent über den Fertigungs- und Versandstatus deines Unikats.</li>
                        <li>Er sorgt im Hintergrund dafür, dass modernste Technologie und handwerkliche Liebe nahtlos ineinandergreifen.</li>
                    </ul>

                    <p>Funki freut sich riesig darauf, euch kennenzulernen und euch bei jeder Bestellung ein Lächeln ins Gesicht zu zaubern.</p>

                    <p>Sagt gern „Hallo“ zu Funki auf unseren Social-Media-Kanälen!</p>
                ',
                'featured_image' => 'marketing/blog/funki.png',
                'status' => 'published',
                'published_at' => Carbon::parse('2026-11-04 10:00:00'),
                'meta_title' => 'Funki Funke stellt sich vor :) Unser Maskottchen',
                'meta_description' => 'Lerne Funki Funke kennen, das Maskottchen von Mein-Seelenfunke. Er unterstützt unsere Automatisierung und den Kundenservice.',
                'meta_keywords' => 'Funki, Maskottchen, Automatisierung, Team, Funke, Laser',
                'is_advertisement' => false,
                'author_name' => 'Mein Seelenfunke',
                'created_at' => Carbon::parse('2026-11-04 10:00:00'),
                'updated_at' => now(),
            ],

            // POST 3: Herzensprojekte (07.11.2026)
            [
                'id' => Str::uuid()->toString(),
                'user_id' => $adminId,
                'blog_category_id' => $catHerzensId,
                'title' => 'Ein Funke, der Gutes tut: Unsere Herzensprojekte für Kitas & Kinder vor Ort',
                'slug' => 'ein-funke-der-gutes-tut-unsere-herzensprojekte',
                'excerpt' => 'Bei Mein-Seelenfunke verbinden wir präzises Handwerk mit echter sozialer Verantwortung. Mit jeder Bestellung fließt ein fester Beitrag direkt in regionale Herzensprojekte für Kinder und Kitas. Erfahre hier alle Details!',
                'content' => '
                    <p>Wenn wir in unserer Gifhorner Manufaktur ein personalisiertes Kristallglas veredeln, geht es fast immer um große Gefühle: Liebe, Dankbarkeit, Verbundenheit oder das liebevolle Gedenken an einen besonderen Menschen. Doch für Gründerin Alina Steinhauer stand von Tag eins an fest: Ein Unternehmen darf nicht nur Werte schöpfen, sondern sollte auch der Gesellschaft etwas zurückgeben.</p>

                    <p>Deshalb rufen wir heute offiziell unsere <strong>Herzensprojekte</strong> ins Leben. Ein Konzept, das tief in der DNA von Mein-Seelenfunke verwurzelt ist.</p>

                    <h3>Was sind die Herzensprojekte?</h3>
                    <p>Unsere Herzensprojekte sind eine direkte, regionale Initiative für die Kleinsten in unserer Gesellschaft. Wir unterstützen Kindergärten, Kitas und gemeinnützige Kinderprojekte direkt bei uns vor Ort in Gifhorn und der umliegenden Region.</p>

                    <p>Dabei geht es uns nicht um abstrakte Geldströme, sondern um greifbare, nachhaltige Freude:</p>
                    <ul>
                        <li><strong>Langlebige Holzspielzeuge:</strong> Hochwertige, unbedenkliche Spiel- und Bewegungsmaterialien für Gruppenräume.</li>
                        <li><strong>Kreativ- und Fördermaterialien:</strong> Farben, Holzbausätze und kindgerechte Werkzeuge, die Fantasie und handwerkliches Geschick wecken.</li>
                        <li><strong>Aktionen & Spielplatzausstattung:</strong> Direkte Sachspenden und Unterstützung für kleine Projekte, die den Kita-Alltag bunter machen.</li>
                    </ul>

                    <h3>Wie funktioniert das Ganze für dich?</h3>
                    <p>Das Schönste daran: <strong>Du musst als Kunde nichts extra tun oder bezahlen.</strong></p>
                    <p>Bei jeder einzelnen Bestellung in unserem Shop reservieren wir automatisch einen festen Betrag aus unseren Unternehmenseinnahmen für den Herzensprojekte-Fonds. Es gibt an der Kasse keine Spendenaufrufe und keinen Cent Aufpreis. Die Unterstützung finanzieren wir zu 100 % aus eigenen Mitteln als Dankeschön für dein Vertrauen in unsere Manufaktur.</p>

                    <h3>Persönlich, nah & transparent</h3>
                    <p>Wir möchten, dass du ganz genau siehst, was dein Einkauf bewirkt. Deshalb übergeben wir Sachmittel persönlich vor Ort an die Kitas. In unserer Community und hier auf dem Blog werden wir regelmäßig Fotos, Berichte und Updates veröffentlichen. So kannst du miterleben, wie aus vielen einzelnen Bestellungen echte strahlende Kinderaugen werden.</p>

                    <blockquote class="italic border-l-4 border-amber-500 pl-4 py-2 text-gray-700 bg-amber-500/10 rounded-r my-6">
                        „Ein Seelenfunke soll nicht nur im Zuhause unserer Kunden ein warmes Licht spenden, sondern auch in den Herzen von Kindern vor Ort einen Funken Begeisterung entfachen.“<br>
                        <span class="text-sm font-bold text-gray-900 not-italic">Alina Steinhauer, Gründerin von Mein-Seelenfunke</span>
                    </blockquote>

                    <p>Wir danken dir von Herzen, dass du Teil dieser Reise bist. Gemeinsam schaffen wir bleibende Erinnerungen und schenken gleichzeitig den Kleinsten ein Lächeln.</p>

                    <hr class="my-6 border-gray-200">
                    <p class="text-xs text-gray-500"><em>Transparenzhinweis: Unsere Herzensprojekte sind eine freiwillige unternehmerische Initiative von Mein Seelenfunke (Cause-Related Marketing). Als Kunde tätigst du mit deinem Einkauf keine eigenständige Spende und erhältst keine Spendenquittung. Die Zuwendungen an Kitas und regionale Kinderinitiativen finanzieren wir vollumfänglich aus unseren erwirtschafteten Unternehmensmitteln.</em></p>
                ',
                'featured_image' => 'marketing/blog/herzensprojekte.png',
                'status' => 'published',
                'published_at' => Carbon::parse('2026-11-07 10:00:00'),
                'meta_title' => 'Herzensprojekte: Ein Funke, der Gutes tut | Mein-Seelenfunke',
                'meta_description' => 'Mit jeder Bestellung bei Mein-Seelenfunke unterstützen wir Kitas und Kinder vor Ort in unserer Region. Erfahre mehr über unsere Herzensprojekte.',
                'meta_keywords' => 'Herzensprojekte, Kitas Gifhorn, Kinderförderung, Soziales Engagement, Mein Seelenfunke, Spende',
                'is_advertisement' => false,
                'author_name' => 'Alina Steinhauer',
                'created_at' => Carbon::parse('2026-11-07 10:00:00'),
                'updated_at' => now(),
            ],
        ];

        DB::table('marketing_blog_posts')->upsert(
            $posts, 
            ['slug'],
            [
                'user_id',
                'blog_category_id',
                'title',
                'excerpt',
                'content',
                'featured_image',
                'status',
                'published_at',
                'meta_title',
                'meta_description',
                'meta_keywords',
                'is_advertisement',
                'author_name',
                'updated_at',
            ]
        );
    }
}
