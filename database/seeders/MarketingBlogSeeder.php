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

        $catRatgeberId = DB::table('marketing_blog_categories')->where('slug', 'ratgeber-geschenkideen')->value('id');
        if (!$catRatgeberId) {
            $catRatgeberId = Str::uuid()->toString();
            DB::table('marketing_blog_categories')->insert([
                'id' => $catRatgeberId,
                'name' => 'Ratgeber & Geschenkideen',
                'slug' => 'ratgeber-geschenkideen',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Blog Posts erstellen

        $posts = [
            // POST 1: Gründung & Maßstäbe (15.10.2026)
            [
                'id' => Str::uuid()->toString(),
                'user_id' => $adminId,
                'blog_category_id' => $catNewsId,
                'title' => 'Willkommen bei Mein-Seelenfunke! Wir setzen neue Maßstäbe!',
                'slug' => 'willkommen-bei-mein-seelenfunke',
                'excerpt' => 'Es ist soweit: Mein-Seelenfunke öffnet die Tore. Wir sind angetreten, um Emotionen in reinstes K9-Kristallglas und massiven Edelstahl zu bannen und Qualität neu zu definieren.',
                'content' => '
                    <p>Wir freuen uns riesig, euch endlich bei <strong>Mein-Seelenfunke</strong> begrüßen zu dürfen!</p>

                    <p>Hinter uns liegen Monate intensiver Vorbereitung, technologischer Feinabstimmung und purer Leidenschaft in unserer Gifhorner Manufaktur. Unser Ziel war es von Beginn an nicht, einfach nur einen weiteren Online-Shop zu eröffnen. Wir wollten einen Ort schaffen, an dem Geschenke wieder eine tiefe, bleibende Bedeutung erhalten.</p>

                    <h3>Unsere Philosophie: Fokus auf Perfektion</h3>
                    <p>In einer schnelllebigen Welt voller flüchtiger Massenware haben wir uns ganz bewusst auf außergewöhnliche Materialien spezialisiert: <strong>reines K9-Kristallglas</strong>, <strong>massiven 304 Edelstahl</strong> und <strong>eloxiertes Aluminium</strong>. Wir kombinieren hochmoderne 3D- und Faser-Lasertechnologie mit zertifiziertem Sachverstand und manueller Endkontrolle. Jedes Stück – vom <a href="/produkt/seelen-kristall">Seelen Kristall</a> bis zum <a href="/produkt/seelen-talisman">Seelen-Talisman</a> – ist ein Unikat, das Emotionen für die Ewigkeit festhält.</p>

                    <h3>Handwerk mit Herz</h3>
                    <p>Doch technische Perfektion ist für uns nur die halbe Miete. „Mein-Seelenfunke“ ist ein echtes Herzensprojekt. Wir möchten nicht nur Freude in eure Wohnzimmer bringen, sondern auch unserer Region etwas zurückgeben. Was das genau bedeutet, erfahrt ihr in unseren Beiträgen zu unseren <a href="/blog/ein-funke-der-gutes-tut-unsere-herzensprojekte">Herzensprojekten</a>.</p>

                    <p>Dies ist der Beginn einer wunderbaren Reise. Schön, dass ihr von Anfang an dabei seid!</p>
                ',
                'featured_image' => 'marketing/blog/willkommen-manufaktur.webp',
                'status' => 'published',
                'published_at' => Carbon::parse('2026-10-15 10:00:00'),
                'meta_title' => 'Mein-Seelenfunke Gründung und Neue Maßstäbe für Geschenke',
                'meta_description' => 'Mein-Seelenfunke ist gestartet. Erfahre mehr über unsere Vision, hochwertige Kristallglas- & Edelstahl-Geschenke mit Lasergravur aus Gifhorn.',
                'meta_keywords' => 'Gründung, Vision, Seelenfunke, Qualität, K9 Kristall, Gifhorn, Lasergravur',
                'is_advertisement' => false,
                'author_name' => 'Mein Seelenfunke',
                'created_at' => Carbon::parse('2026-10-15 10:00:00'),
                'updated_at' => now(),
            ],

            // POST 2: Funki Funke (18.10.2026)
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
                        <li>Er begleitet deine Bestellung im interaktiven <a href="/shop">2D- & 3D-Produktkonfigurator</a> vom ersten Pixel bis zur Endpolitur im Laser.</li>
                        <li>Er informiert dich stets transparent über den Fertigungs- und Versandstatus deines Unikats.</li>
                        <li>Er sorgt im Hintergrund dafür, dass modernste Technologie und handwerkliche Liebe nahtlos ineinandergreifen.</li>
                    </ul>

                    <p>Funki freut sich riesig darauf, euch kennenzulernen und euch bei jeder Bestellung ein Lächeln ins Gesicht zu zaubern.</p>

                    <p>Sagt gern „Hallo“ zu Funki auf unseren Social-Media-Kanälen!</p>
                ',
                'featured_image' => 'marketing/blog/funki-manufaktur.webp',
                'status' => 'published',
                'published_at' => Carbon::parse('2026-10-18 10:00:00'),
                'meta_title' => 'Funki Funke stellt sich vor – Unser Manufaktur-Maskottchen',
                'meta_description' => 'Lerne Funki Funke kennen, das Maskottchen von Mein-Seelenfunke. Er begleitet deine Wunschgravur vom 3D-Konfigurator bis zum Versand.',
                'meta_keywords' => 'Funki, Maskottchen, Automatisierung, Team, Funke, Laser',
                'is_advertisement' => false,
                'author_name' => 'Mein Seelenfunke',
                'created_at' => Carbon::parse('2026-10-18 10:00:00'),
                'updated_at' => now(),
            ],

            // POST 3: Herzensprojekte (21.10.2026)
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
                    <p>Bei jeder einzelnen Bestellung in unserem <a href="/shop">Online-Shop</a> reservieren wir automatisch einen festen Betrag aus unseren Unternehmenseinnahmen für den Herzensprojekte-Fonds. Es gibt an der Kasse keine Spendenaufrufe und keinen Cent Aufpreis. Die Unterstützung finanzieren wir zu 100 % aus eigenen Mitteln als Dankeschön für dein Vertrauen in unsere Manufaktur.</p>

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
                'featured_image' => 'marketing/blog/herzensprojekte-kitas.webp',
                'status' => 'published',
                'published_at' => Carbon::parse('2026-10-21 10:00:00'),
                'meta_title' => 'Herzensprojekte: Ein Funke, der Gutes tut | Mein-Seelenfunke',
                'meta_description' => 'Mit jeder Bestellung bei Mein-Seelenfunke unterstützen wir Kitas und Kinder vor Ort in unserer Region. Erfahre mehr über unsere Herzensprojekte.',
                'meta_keywords' => 'Herzensprojekte, Kitas Gifhorn, Kinderförderung, Soziales Engagement, Mein Seelenfunke, Spende',
                'is_advertisement' => false,
                'author_name' => 'Alina Steinhauer',
                'created_at' => Carbon::parse('2026-10-21 10:00:00'),
                'updated_at' => now(),
            ],

            // POST 4: SEO-Ratgeber Hundemarke mit Gravur aus Edelstahl (24.10.2026)
            [
                'id' => Str::uuid()->toString(),
                'user_id' => $adminId,
                'blog_category_id' => $catRatgeberId,
                'title' => 'Hundemarke mit Gravur aus Edelstahl: Warum rostfreie Lasergravur jedes Abenteuer übersteht',
                'slug' => 'hundemarke-mit-gravur-edelstahl-ratgeber',
                'excerpt' => 'Ob Regen, Matsch oder Bad im See: Eine Hundemarke muss im Alltag extremen Belastungen standhalten. Erfahre, warum massiver Edelstahl mit Faserlaser-Gravur herkömmlichen Marken haushoch überlegen ist.',
                'content' => '
                    <p>Für Hundebesitzer ist eine individuell gravierte Hundemarke weit mehr als ein schönes Schmuckstück am Halsband: Im Ernstfall ist sie die schnellste Lebensversicherung, damit dein Vierbeiner sicher nach Hause zurückkehrt.</p>

                    <p>Doch wer schon einmal eine billig bedruckte oder flach geritzte Marke gekauft hat, kennt das Problem: Nach wenigen Wochen im Wald, beim Toben im Sand oder nach dem Schwimmen im See blättert die Farbe ab und die Telefonnummer ist kaum noch zu entziffern.</p>

                    <h3>1. Warum spiegelpolierter Edelstahl das ideale Material für Hundemarken ist</h3>
                    <p>Bei <strong><a href="/produkt/seelen-pfote-hundemarke">Die Seelen-Pfote</a></strong> setzen wir in unserer Gifhorner Manufaktur bewusst auf <strong>massiven, rostfreien Edelstahl (40 × 22 × 1,8 mm, ca. 12 g)</strong> in Knochenform mit oben mittig angegossener Aufhänge-Öse und ausgestanztem Pfotenabdruck unten rechts:</p>
                    <ul>
                        <li><strong>100 % rostfrei & wasserfest:</strong> Egal ob Salzwasser, Regen oder Schnee – Edelstahl oxidiert nicht und färbt nicht auf helles Hundefell ab.</li>
                        <li><strong>Integrierte Aufhänge-Öse:</strong> Durch die stabile, abgerundete Metallöse oben mittig reißt die Marke selbst bei starker Zugbelastung im Unterholz nicht aus.</li>
                        <li><strong>Ausgestanzter Pfoten-Durchbruch:</strong> Der echte Pfoten-Ausschnitt im rechten Flügel verleiht der Marke einen unverwechselbaren Look bei optimalem Gewicht.</li>
                    </ul>

                    <h3>2. Faserlaser-Anlassgravur statt oberflächlichem Druck</h3>
                    <p>In unserer Manufaktur gravieren wir jede Marke mit einem hochpräzisen Faserlaser. Durch die thermische Anlassgravur (Annealing) verfärbt sich das Gefüge des Edelstahls dauerhaft in ein <strong>kontrastreiches Tief-Anthrazit</strong>. Da keine Farbe aufgetragen wird, kann sich auch nach Jahren nichts abreiben.</p>

                    <h3>3. Welche Angaben gehören auf Vorder- und Rückseite?</h3>
                    <p>Im kostenlosen <strong><a href="/produkt/seelen-pfote-hundemarke">2D- & 3D-Online-Konfigurator</a></strong> kannst du beide Seiten deiner Hundemarke live gestalten:</p>
                    <ul>
                        <li><strong>Vorderseite:</strong> Rufname deines Hundes und ein passendes Symbol (z. B. Herz, Krone oder Schutzengel).</li>
                        <li><strong>Rückseite:</strong> Deine Mobilnummer (am besten mit Ländervorwahl <code>+49...</code> für den Urlaub), ein Hinweis wie „Ich bin gechipt (TASSO)“ oder „Brauche Medikamente“.</li>
                    </ul>

                    <p>Gestalte jetzt deine individuelle <a href="/produkt/seelen-pfote-hundemarke"><strong>Seelen-Pfote Edelstahl-Hundemarke im 3D-Konfigurator</strong></a> – versandfertig in 1–3 Werktagen direkt aus unserer Manufaktur in Gifhorn!</p>
                ',
                'featured_image' => 'produkte/products/seelen-pfote-hundemarke/seelen-pfote-lifestyle.jpg',
                'status' => 'published',
                'published_at' => Carbon::parse('2026-10-24 10:00:00'),
                'meta_title' => 'Hundemarke mit Gravur aus Edelstahl – Ratgeber & 3D-Konfigurator | Mein-Seelenfunke',
                'meta_description' => 'Warum eine Hundemarke aus massivem Edelstahl (40×22 mm, ca. 12 g) mit Faserlaser-Gravur jedes Abenteuer übersteht: Tipps für Vorder- & Rückseite im 3D-Konfigurator.',
                'meta_keywords' => 'Hundemarke mit Gravur, Hundemarke Edelstahl, Knochen Hundemarke Pfote, Hundemarke personalisiert, Lasergravur Hundemarke',
                'is_advertisement' => false,
                'author_name' => 'Alina Steinhauer',
                'created_at' => Carbon::parse('2026-10-24 10:00:00'),
                'updated_at' => now(),
            ],

            // POST 5: SEO-Ratgeber Personalisierte Geschenke Vergleich (26.10.2026)
            [
                'id' => Str::uuid()->toString(),
                'user_id' => $adminId,
                'blog_category_id' => $catRatgeberId,
                'title' => 'Personalisierte Geschenke mit Lasergravur: Unsere 5 beliebtesten Unikate im Überblick',
                'slug' => 'personalisierte-geschenke-lasergravur-vergleich',
                'excerpt' => 'Vom 3D-Kristallglas über den vergoldeten Edelstahl-Talisman bis zum gravierten Weizenglas: Welches personalisierte Geschenk passt zu welchem Anlass? Unser großer Manufaktur-Guide.',
                'content' => '
                    <p>Ein persönliches Geschenk zeigt auf den ersten Blick: Hier hat sich jemand wirklich Gedanken gemacht. In unserer Manufaktur <strong>Mein-Seelenfunke</strong> in Gifhorn veredeln wir fünf handverlesene Grundprodukte, die du direkt im Browser im interaktiven 2D- und 3D-Echtzeit-Konfigurator gestalten kannst.</p>

                    <h3>1. Der Seelen Kristall (180 × 200 × 40 mm, optisches K9-Kristallglas)</h3>
                    <p>Unser <a href="/produkt/seelen-kristall"><strong>Seelen Kristall</strong></a> (ca. 920 g massives K9-Kristallglas mit edlem Facettenschliff) ist das ultimative Erinnerungsstück für Hochzeiten, Jubiläen, Ehrungen oder als leuchtendes Fotogeschenk.</p>

                    <h3>2. Der Seelen-Talisman (28 × 50 × 2,2 mm, Spiegelgold-Edelstahl)</h3>
                    <p>Der <a href="/produkt/seelen-talisman"><strong>Seelen-Talisman</strong></a> ist ein massiver, spiegelpolierter Edelstahl-Schlüsselanhänger in edlem Gelbgold-Finish (ca. 24 g). Die tief-anthrazitfarbene Faserlaser-Gravur auf Vorder- und Rückseite macht ihn zum täglichen Begleiter für den Autoschlüssel, Jahrestag oder Vatertag.</p>

                    <h3>3. Der Seelenanhänger (45 × 40 × 2,3 mm, Herzform)</h3>
                    <p>Unser <a href="/produkt/seelen-anhaenger"><strong>Seelenanhänger in Herzform</strong></a> aus schwarz eloxiertem Metall überzeugt durch seinen brillanten Silber-Weiß-Gravurkontrast – perfekt für Valentinstag, Muttertag oder als Freundschaftsbeweis.</p>

                    <h3>4. Die Seelen-Pfote (40 × 22 × 1,8 mm, Edelstahl-Hundemarke)</h3>
                    <p>Für alle Hundeliebhaber: <a href="/produkt/seelen-pfote-hundemarke"><strong>Die Seelen-Pfote</strong></a> (ca. 12 g) aus hochglanzpoliertem silbernem Edelstahl in Knochenform mit ausgestanztem Pfotenabdruck schützt deinen Vierbeiner mit Name und Notfall-Telefonnummer.</p>

                    <h3>5. Der Feierabend-Funke (Ø 74 × 218 mm, 0,5 l Kristall-Weizenglas)</h3>
                    <p>Das personalisierte <a href="/produkt/weizenglas-personalisiert"><strong>Weizenglas „Der Feierabend-Funke“</strong></a> mit feiner 360°-Laser-Frosting-Gravur ist der Klassiker für Geburtstage, Vatertag, Vereinsfeiern und Junggesellenabschiede.</p>

                    <h3>Für Firmen & Vereine: Mengenrabatte & Online-Kalkulator</h3>
                    <p>Schon ab 3 Stück profitierst du automatisch von unseren Staffelpreisen (bis zu 15 % Rabatt). Für größere Vereins- oder Firmenevents kannst du dein Projekt jederzeit unverbindlich in unserem <a href="/calculator"><strong>B2B-Projekt-Kalkulator</strong></a> berechnen.</p>
                ',
                'featured_image' => 'produkte/products/seelen-talisman/seelen-talisman-main.jpg',
                'status' => 'published',
                'published_at' => Carbon::parse('2026-10-26 09:00:00'),
                'meta_title' => 'Personalisierte Geschenke mit Lasergravur: Top 5 Unikate im Vergleich | Mein-Seelenfunke',
                'meta_description' => 'Kristallglas-Trophäe, Gold-Edelstahl-Schlüsselanhänger, Herz-Anhänger, Hundemarke oder Weizenglas? Finde das perfekte personalisierte Geschenk im 3D-Konfigurator.',
                'meta_keywords' => 'Personalisierte Geschenke, Lasergravur Geschenke, Schlüsselanhänger mit Gravur, Weizenglas personalisiert, K9 Kristallglas Gravur',
                'is_advertisement' => false,
                'author_name' => 'Alina Steinhauer',
                'created_at' => Carbon::parse('2026-10-26 09:00:00'),
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
