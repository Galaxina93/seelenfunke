<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
            $this->command?->warn('Kein Admin in der Tabelle "admins" gefunden. Abbruch.');
            return;
        }

        // 2. Edle goldene Line-Art-Embleme auf tiefschwarzem Grund (#050505) erzeugen
        $this->generateGoldenBlogEmblems();

        // 3. Kategorien sicherstellen
        $catNewsId = DB::table('marketing_blog_categories')->where('slug', 'neuigkeiten')->value('id');
        if (!$catNewsId) {
            $catNewsId = Str::uuid()->toString();
            DB::table('marketing_blog_categories')->insert([
                'id' => $catNewsId,
                'name' => 'Manufaktur & Einblicke',
                'slug' => 'neuigkeiten',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('marketing_blog_categories')->where('id', $catNewsId)->update([
                'name' => 'Manufaktur & Einblicke',
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

        // 4. Zeitlose Evergreen-Ratgeber & Manufaktur-Beiträge (100 % frei von Gedankenstrichen)
        $posts = [
            // POST 1: Gründung & Maßstäbe
            [
                'id' => Str::uuid()->toString(),
                'user_id' => $adminId,
                'blog_category_id' => $catNewsId,
                'title' => 'Willkommen bei Mein-Seelenfunke: Unsere Manufaktur für bleibende Unikate',
                'slug' => 'willkommen-bei-mein-seelenfunke',
                'excerpt' => 'Mein-Seelenfunke verbindet präzise UV- und Faser-Lasertechnologie mit echter Handarbeit in Gifhorn: Entdecke unsere Philosophie für 585er Echtgold, Edelstahl und K9-Kristallglas.',
                'content' => '
                    <p>Willkommen bei <strong>Mein-Seelenfunke</strong> aus Gifhorn!</p>

                    <p>Hinter unserer Manufaktur stehen intensive Entwicklungsarbeit, technologische Feinabstimmung und echte Leidenschaft für besondere Materialien. Unser Anspruch ist es, Geschenke und Schmuckstücke zu schaffen, die nicht in einer Schublade verschwinden, sondern über Jahrzehnte hinweg eine persönliche Geschichte erzählen.</p>

                    <h3>Unsere Philosophie: Drei klar getrennte Welten</h3>
                    <p>In einer schnelllebigen Welt voller flüchtiger Massenware setzen wir auf kompromisslose Materialqualität in drei klar gegliederten Bereichen:</p>
                    <ul>
                        <li><strong>Manufaktur-Kollektion:</strong> Reines optisches <a href="/produkt/seelen-kristall">K9-Kristallglas</a>, spiegelpolierter und 14 Karat vergoldeter Edelstahl wie beim <a href="/produkt/goldenes-seelen-herz">Goldenen Seelen-Herz</a> oder <a href="/produkt/seelen-talisman">Seelen-Talisman</a> sowie eloxiertes Aluminium.</li>
                        <li><strong>Atelier Echtgold &amp; Silber:</strong> Gestempeltes <a href="/produkt/echtgold-seelen-herz">585er und 333er Echtgold sowie 925er Sterlingsilber</a>, das wir auf Bestellung berührungslos mit unserem 5W UV-Präzisionslaser veredeln.</li>
                        <li><strong>Seelenfunken Wertgutscheine:</strong> Wahlweise sofort digital per PDF oder als lasergravierte Aluminiumkarte im schwarzen Edel-Umschlag mit goldenem Wachssiegel.</li>
                    </ul>

                    <h3>Handwerk mit Herz in Gifhorn</h3>
                    <p>Jedes Unikat durchläuft in unserer Gifhorner Manufaktur eine persönliche 4-Augen-Qualitätskontrolle. Gleichzeitig fließt aus jeder Bestellung ein fester Beitrag in unsere regionalen <a href="/blog/ein-funke-der-gutes-tut-unsere-herzensprojekte">Herzensprojekte für Kitas und Kinder vor Ort</a>.</p>

                    <p>Schön, dass du Teil unserer Reise bist!</p>
                ',
                'featured_image' => 'marketing/blog/willkommen-manufaktur.webp',
                'status' => 'published',
                'published_at' => Carbon::parse('2026-10-15 10:00:00'),
                'meta_title' => 'Mein-Seelenfunke Manufaktur Gifhorn | Echtgold, Edelstahl & K9-Kristall',
                'meta_description' => 'Erfahre mehr über die Laser-Manufaktur Mein-Seelenfunke in Gifhorn: 585er Echtgold, vergoldeter Edelstahl und K9-Kristallglas mit Präzisionsgravur.',
                'meta_keywords' => 'Manufaktur Gifhorn, Mein Seelenfunke, K9 Kristall, 585 Echtgold Gravur, Lasergravur Deutschland',
                'is_advertisement' => false,
                'author_name' => 'Alina Steinhauer',
                'created_at' => Carbon::parse('2026-10-15 10:00:00'),
                'updated_at' => now(),
            ],

            // POST 2: Funki Funke & Präzisions-Workflow
            [
                'id' => Str::uuid()->toString(),
                'user_id' => $adminId,
                'blog_category_id' => $catNewsId,
                'title' => 'Vom 3D-Konfigurator bis zum UV-Laser: Wie dein Unikat in Gifhorn entsteht',
                'slug' => 'gestatten-funki-funke',
                'excerpt' => 'Ein Blick hinter die Kulissen unserer Laser-Manufaktur: Wie dein Wunschtext aus dem Live-Konfigurator millimetergenau in Kristallglas, Edelstahl oder Echtgold verewigt wird.',
                'content' => '
                    <p>Viele Kundinnen und Kunden fragen sich beim Gestalten im Shop: <strong>Wie kommt mein Entwurf eigentlich so gestochen scharf auf das fertige Schmuckstück oder in das Kristallglas?</strong></p>

                    <h3>1. Live-Vorschau im Konfigurator oder direkter Wunschtext</h3>
                    <p>In unserem <a href="/shop">Online-Shop</a> gestaltest du dein Unikat entweder direkt im interaktiven 2D- und 3D-Konfigurator oder gibst bei unserem <a href="/produkt/echtgold-seelen-herz">Atelier Echtgold Seelen-Herz</a> bequem deinen Wunschtext für Vorder- und Rückseite sowie dein Wunschsymbol an.</p>

                    <h3>2. Berührungslose 5W UV-Präzisionsgravur</h3>
                    <p>Während herkömmliche mechanische Diamantstichel dünnes Edelmetall oft eindrücken oder verkratzen, arbeitet unser <strong>5W UV-Kaltlichtlaser (355 nm Wellenlänge)</strong> in Gifhorn komplett berührungslos:</p>
                    <ul>
                        <li><strong>Mikrometer-Präzision:</strong> Selbst feinste Schreibschriften, Koordinaten, Daten und Symbole werden gestochen scharf auf kleinstem Raum abgebildet.</li>
                        <li><strong>Dauerhaft abriebfest:</strong> Die Gravur ist fest im Material verankert und blättert niemals ab.</li>
                        <li><strong>Zertifizierte Sicherheit:</strong> Als ausgebildete Laserschutzbeauftragte nach OStrV und TROS Laserstrahlung prüfen wir jeden Rohling vor und nach dem Laserprozess persönlich von Hand.</li>
                    </ul>

                    <p>Unser digitales Maskottchen <strong>Funki Funke</strong> begleitet deine Bestellung dabei vom ersten Klick im Konfigurator bis zur Versandbestätigung per DHL.</p>
                ',
                'featured_image' => 'marketing/blog/funki-manufaktur.webp',
                'status' => 'published',
                'published_at' => Carbon::parse('2026-10-18 10:00:00'),
                'meta_title' => 'UV-Lasergravur & 3D-Konfigurator: So entsteht dein Unikat | Mein-Seelenfunke',
                'meta_description' => 'Blick hinter die Kulissen der Manufaktur Mein-Seelenfunke in Gifhorn: Berührungslose 5W UV-Lasergravur auf Echtgold, Edelstahl und K9-Kristallglas.',
                'meta_keywords' => 'UV Lasergravur, 3D Konfigurator Gravur, Funki Funke, Manufaktur Gifhorn, Schmuck gravieren lassen',
                'is_advertisement' => false,
                'author_name' => 'Alina Steinhauer',
                'created_at' => Carbon::parse('2026-10-18 10:00:00'),
                'updated_at' => now(),
            ],

            // POST 3: Herzensprojekte
            [
                'id' => Str::uuid()->toString(),
                'user_id' => $adminId,
                'blog_category_id' => $catHerzensId,
                'title' => 'Ein Funke, der Gutes tut: Unsere Herzensprojekte für Kitas & Kinder vor Ort',
                'slug' => 'ein-funke-der-gutes-tut-unsere-herzensprojekte',
                'excerpt' => 'Bei Mein-Seelenfunke verbinden wir präzises Handwerk mit echter sozialer Verantwortung. Mit jeder Bestellung fließt ein fester Beitrag direkt in regionale Herzensprojekte für Kinder und Kitas.',
                'content' => '
                    <p>Wenn wir in unserer Gifhorner Manufaktur ein personalisiertes Schmuckstück oder Kristallglas veredeln, geht es fast immer um große Gefühle: Liebe, Dankbarkeit, Verbundenheit oder das liebevolle Gedenken an einen besonderen Menschen. Für Gründerin Alina Steinhauer stand von Tag eins an fest: Ein Unternehmen darf nicht nur Werte schöpfen, sondern sollte auch der Region etwas zurückgeben.</p>

                    <p>Deshalb sind unsere <strong>Herzensprojekte</strong> fest in der DNA von Mein-Seelenfunke verankert.</p>

                    <h3>Was sind die Herzensprojekte?</h3>
                    <p>Unsere Herzensprojekte sind eine direkte, regionale Initiative für die Kleinsten in unserer Gesellschaft. Wir unterstützen Kindergärten, Kitas und gemeinnützige Kinderprojekte direkt bei uns vor Ort in Gifhorn und der umliegenden Region:</p>
                    <ul>
                        <li><strong>Langlebige Holzspielzeuge:</strong> Hochwertige, unbedenkliche Spiel- und Bewegungsmaterialien für Gruppenräume.</li>
                        <li><strong>Kreativ- und Fördermaterialien:</strong> Farben, Holzbausätze und kindgerechte Werkzeuge, die Fantasie und handwerkliches Geschick wecken.</li>
                        <li><strong>Aktionen &amp; Spielplatzausstattung:</strong> Direkte Sachspenden und Unterstützung für Projekte, die den Kita-Alltag bereichern.</li>
                    </ul>

                    <h3>Wie funktioniert das Ganze für dich?</h3>
                    <p>Das Wichtigste: <strong>Du musst als Kunde nichts extra tun oder bezahlen.</strong></p>
                    <p>Bei jeder einzelnen Bestellung in unserem <a href="/shop">Online-Shop</a> reservieren wir automatisch einen festen Betrag aus unseren Unternehmenseinnahmen für den Herzensprojekte-Fonds. Es gibt an der Kasse keine Spendenaufrufe und keinen Cent Aufpreis. Die Unterstützung finanzieren wir zu 100 % aus eigenen Mitteln als Dankeschön für dein Vertrauen in unsere Manufaktur.</p>

                    <blockquote class="italic border-l-4 border-amber-500 pl-4 py-2 text-gray-700 bg-amber-500/10 rounded-r my-6">
                        „Ein Seelenfunke soll nicht nur im Zuhause unserer Kunden ein warmes Licht spenden, sondern auch in den Herzen von Kindern vor Ort einen Funken Begeisterung entfachen.“<br>
                        <span class="text-sm font-bold text-gray-900 not-italic">Alina Steinhauer, Gründerin von Mein-Seelenfunke</span>
                    </blockquote>

                    <hr class="my-6 border-gray-200">
                    <p class="text-xs text-gray-500"><em>Transparenzhinweis: Unsere Herzensprojekte sind eine freiwillige unternehmerische Initiative von Mein Seelenfunke (Cause-Related Marketing). Als Kunde tätigst du mit deinem Einkauf keine eigenständige Spende und erhältst keine Spendenquittung. Die Zuwendungen an Kitas und regionale Kinderinitiativen finanzieren wir vollumfänglich aus unseren erwirtschafteten Unternehmensmitteln.</em></p>
                ',
                'featured_image' => 'marketing/blog/herzensprojekte-kitas.webp',
                'status' => 'published',
                'published_at' => Carbon::parse('2026-10-21 10:00:00'),
                'meta_title' => 'Herzensprojekte: Ein Funke, der Gutes tut | Mein-Seelenfunke',
                'meta_description' => 'Mit jeder Bestellung bei Mein-Seelenfunke unterstützen wir Kitas und Kinder vor Ort in unserer Region Gifhorn. Erfahre mehr über unsere Herzensprojekte.',
                'meta_keywords' => 'Herzensprojekte, Kitas Gifhorn, Kinderförderung, Soziales Engagement, Mein Seelenfunke',
                'is_advertisement' => false,
                'author_name' => 'Alina Steinhauer',
                'created_at' => Carbon::parse('2026-10-21 10:00:00'),
                'updated_at' => now(),
            ],

            // POST 4: SEO-Ratgeber Hundemarke mit Gravur aus Edelstahl
            [
                'id' => Str::uuid()->toString(),
                'user_id' => $adminId,
                'blog_category_id' => $catRatgeberId,
                'title' => 'Hundemarke mit Gravur aus Edelstahl: Welche Angaben gehören auf Vorder- und Rückseite?',
                'slug' => 'hundemarke-mit-gravur-edelstahl-ratgeber',
                'excerpt' => 'Ob Regen, Matsch oder Bad im See: Eine Hundemarke muss im Alltag extremen Belastungen standhalten. Erfahre, welche Angaben im Notfall zählen und warum rostfreie Lasergravur nicht verblasst.',
                'content' => '
                    <p>Für Hundebesitzer ist eine individuell gravierte Hundemarke weit mehr als ein schönes Schmuckstück am Halsband: Im Ernstfall ist sie der schnellste Weg, damit dein Vierbeiner ohne Umweg über das Tierheim direkt zu dir nach Hause zurückkehrt.</p>

                    <p>Wer schon einmal eine dünn bedruckte oder leicht geritzte Alu-Marke gekauft hat, kennt das Problem: Nach wenigen Wochen im Wald, beim Toben im Sand oder nach dem Schwimmen im See reibt sich die Schrift ab und die Telefonnummer ist kaum noch lesbar.</p>

                    <h3>1. Warum spiegelpolierter Edelstahl das ideale Material für Hundemarken ist</h3>
                    <p>Bei <strong><a href="/produkt/seelen-pfote-hundemarke">Die Seelen-Pfote</a></strong> setzen wir in unserer Gifhorner Manufaktur auf <strong>massiven, rostfreien Edelstahl (40 × 22 × 1,8 mm, ca. 12 g)</strong> in Knochenform mit oben mittig angegossener Aufhänge-Öse und ausgestanztem Pfotenabdruck:</p>
                    <ul>
                        <li><strong>100 % rostfrei &amp; wasserfest:</strong> Egal ob Salzwasser, Regen oder Schnee, Edelstahl oxidiert nicht und färbt nicht auf helles Hundefell ab.</li>
                        <li><strong>Integrierte Aufhänge-Öse:</strong> Durch die stabile Metallöse oben mittig reißt die Marke selbst bei starker Zugbelastung im Unterholz nicht aus.</li>
                        <li><strong>Ausgestanzter Pfoten-Durchbruch:</strong> Der echte Pfoten-Ausschnitt im rechten Flügel sorgt für einen unverwechselbaren Look bei angenehmem Tragegewicht.</li>
                    </ul>

                    <h3>2. Welche Angaben gehören auf Vorder- und Rückseite?</h3>
                    <p>Im kostenlosen <strong><a href="/produkt/seelen-pfote-hundemarke">2D- &amp; 3D-Online-Konfigurator</a></strong> kannst du beide Seiten deiner Hundemarke ohne Aufpreis gestalten:</p>
                    <ul>
                        <li><strong>Vorderseite:</strong> Rufname deines Hundes und ein passendes Symbol (z. B. Herz, Krone oder Schutzengel).</li>
                        <li><strong>Rückseite:</strong> Deine Mobilnummer (am besten mit Ländervorwahl <code>+49...</code> für Reisen ins Ausland) sowie ein kurzer Hinweis wie <em>„Ich bin gechipt (TASSO)“</em> oder <em>„Brauche Medikamente“</em>.</li>
                    </ul>

                    <p>Gestalte jetzt deine individuelle <a href="/produkt/seelen-pfote-hundemarke"><strong>Seelen-Pfote Edelstahl-Hundemarke im 3D-Konfigurator</strong></a> direkt aus unserer Manufaktur in Gifhorn!</p>
                ',
                'featured_image' => 'marketing/blog/hundemarke-ratgeber.webp',
                'status' => 'published',
                'published_at' => Carbon::parse('2026-10-24 10:00:00'),
                'meta_title' => 'Hundemarke mit Gravur aus Edelstahl: Ratgeber & Pflichtangaben | Mein-Seelenfunke',
                'meta_description' => 'Welche Angaben gehören auf eine Hundemarke? Warum massiver Edelstahl (40×22 mm) mit Lasergravur jedes Abenteuer übersteht. Tipps für Vorder- & Rückseite.',
                'meta_keywords' => 'Hundemarke mit Gravur, Hundemarke Edelstahl, Was muss auf Hundemarke stehen, Hundemarke personalisiert, Lasergravur Hundemarke',
                'is_advertisement' => false,
                'author_name' => 'Alina Steinhauer',
                'created_at' => Carbon::parse('2026-10-24 10:00:00'),
                'updated_at' => now(),
            ],

            // POST 5: SEO-Ratgeber Personalisierte Geschenke Vergleich
            [
                'id' => Str::uuid()->toString(),
                'user_id' => $adminId,
                'blog_category_id' => $catRatgeberId,
                'title' => 'Personalisierte Geschenke mit Lasergravur: Welches Unikat passt zu welchem Anlass?',
                'slug' => 'personalisierte-geschenke-lasergravur-vergleich',
                'excerpt' => 'Vom 585er Echtgold-Herz über das massive K9-Kristallglas bis zum vergoldeten Edelstahl-Talisman: Unser großer Geschenk-Kompass für Jahrestag, Hochzeit, Geburt und Jubiläum.',
                'content' => '
                    <p>Ein persönliches Geschenk zeigt auf den ersten Blick: Hier hat sich jemand wirklich Gedanken gemacht. Damit du für jeden Anlass sofort das passende Material und Unikat findest, haben wir unsere beliebtesten Stücke aus der Manufaktur und dem Atelier für dich gegenübergestellt.</p>

                    <h3>1. Für große Lebensmomente, Geburt, Taufe &amp; Jahrestag: Das Echtgold Seelen-Herz (585er Gold)</h3>
                    <p>Wenn es echtes Edelmetall für die Ewigkeit sein soll, ist unser <a href="/produkt/echtgold-seelen-herz"><strong>Echtgold Seelen-Herz im Komplett-Set (585er Gelbgold-Herzanhänger inklusive 333er Echtgold-Panzerkette)</strong></a> aus unserem <a href="/shop?collectionWorld=atelier">Atelier Echtgold &amp; Silber</a> die krönende Wahl. Geliefert inklusive mattschwarzer Schmuckschachtel mit Gold-Logo und versichertem Versand.</p>

                    <h3>2. Für Partnerin, Mama &amp; beste Freundin: Das Goldene Seelen-Herz (14k Ion-Plating Edelstahl)</h3>
                    <p>Unser <a href="/produkt/goldenes-seelen-herz"><strong>Goldenes Seelen-Herz</strong></a> aus massivem, 14 Karat vergoldetem Edelstahl mit edler Collierschlaufe (22 × 25 × 4 mm) bietet warmen Goldglanz für den Alltag und ist auf Vorder- und Rückseite frei im 3D-Konfigurator gestaltbar.</p>

                    <h3>3. Für Hochzeiten, Jubiläen &amp; besondere Ehrungen: Der Seelen Kristall (K9-Kristallglas)</h3>
                    <p>Unser <a href="/produkt/seelen-kristall"><strong>Seelen Kristall</strong></a> (180 × 200 × 40 mm, ca. 920 g optisches K9-Kristallglas mit Facettenschliff) fängt das Licht im Raum ein und bringt feine Lasergravuren edel gefrostet zum Leuchten.</p>

                    <h3>4. Für den täglichen Begleiter am Schlüsselbund: Seelen-Talisman &amp; Seelenanhänger</h3>
                    <p>Der spiegelpolierte <a href="/produkt/seelen-talisman"><strong>Seelen-Talisman</strong></a> (28 × 50 mm) und der schwarz eloxierte <a href="/produkt/seelen-anhaenger"><strong>Seelenanhänger in Herzform</strong></a> sind unverwüstliche Alltagsbegleiter für Partner, Papa, Großeltern oder zum Führerschein.</p>

                    <h3>5. Für Geburtstage, Vatertag &amp; Vereine: Der Feierabend-Funke (0,5 l Kristall-Weizenglas)</h3>
                    <p>Das personalisierte <a href="/produkt/weizenglas-personalisiert"><strong>Weizenglas „Der Feierabend-Funke“</strong></a> mit präziser 360°-Lasergravur sorgt bei jedem Anstoßen für Freude. Für Vereine und Firmen lassen sich größere Stückzahlen direkt in unserem <a href="/calculator"><strong>B2B-Projekt-Kalkulator</strong></a> berechnen.</p>
                ',
                'featured_image' => 'marketing/blog/personalisierte-geschenke-vergleich.webp',
                'status' => 'published',
                'published_at' => Carbon::parse('2026-10-26 09:00:00'),
                'meta_title' => 'Personalisierte Geschenke mit Lasergravur: Geschenk-Kompass | Mein-Seelenfunke',
                'meta_description' => '585er Echtgold-Herz, K9-Kristallglas, vergoldeter Edelstahl-Talisman oder Weizenglas? Finde das perfekte personalisierte Geschenk für jeden Anlass.',
                'meta_keywords' => 'Personalisierte Geschenke, Lasergravur Geschenke, Echtgold Herz mit Gravur, Schlüsselanhänger mit Gravur, K9 Kristallglas',
                'is_advertisement' => false,
                'author_name' => 'Alina Steinhauer',
                'created_at' => Carbon::parse('2026-10-26 09:00:00'),
                'updated_at' => now(),
            ],

            // POST 6: NEU - SEO-Magnet für Gravursprüche & Edelmetall-Ratgeber (333 / 585 Gold & 925 Silber vs. Edelstahl)
            [
                'id' => Str::uuid()->toString(),
                'user_id' => $adminId,
                'blog_category_id' => $catRatgeberId,
                'title' => '35 kurze Gravursprüche für Ketten & Anhänger + Ratgeber: 585er Echtgold vs. Edelstahl',
                'slug' => 'gravursprueche-echtgold-silber-schmuck-ratgeber',
                'excerpt' => 'Du suchst den perfekten kurzen Spruch für einen Herzanhänger, Talisman oder ein Gravurarmband? Hier findest du die 35 schönsten Gravur-Ideen sowie den ehrlichen Vergleich zwischen 585er Echtgold, 333er Gold und 14k Edelstahl.',
                'content' => '
                    <p>Ein gravierter Herzanhänger oder Talisman gehört zu den persönlichsten Geschenken überhaupt. Doch vor der Bestellung stehen fast alle vor derselben Frage: <strong>Welche Worte passen auf wenige Millimeter Fläche und treffen trotzdem mitten ins Herz?</strong></p>

                    <p>In unserer Gifhorner Laser-Manufaktur veredeln wir sowohl unser exklusives <a href="/produkt/echtgold-seelen-herz"><strong>Echtgold Seelen-Herz (585er Gelbgold mit 333er Goldkette)</strong></a> als auch unsere <a href="/produkt/goldenes-seelen-herz"><strong>Manufaktur-Kollektion aus 14k vergoldetem Edelstahl</strong></a>. Da bei uns Vorder- und Rückseite inklusive sind, kannst du auf der Vorderseite einen emotionalen Spruch wählen und auf der Rückseite ein Datum, Initialen oder Koordinaten verewigen.</p>

                    <h3>1. Die schönsten kurzen Gravursprüche für Partner &amp; Liebe (1 bis 4 Wörter)</h3>
                    <ul>
                        <li><em>Für immer wir</em></li>
                        <li><em>Mein Seelenfunke</em></li>
                        <li><em>Du &amp; Ich · Unendlich</em></li>
                        <li><em>Mein Lieblingsmensch</em></li>
                        <li><em>In ewiger Liebe</em></li>
                        <li><em>Heimathafen</em></li>
                        <li><em>Bis zum Mond &amp; zurück</em></li>
                        <li><em>Mein Herz schlägt für dich</em></li>
                        <li><em>Unsere Koordinaten: 52°25\'N 10°32\'E</em></li>
                    </ul>

                    <h3>2. Gravur-Ideen für Familie, Mama, Geburt &amp; Taufe</h3>
                    <ul>
                        <li><em>Beste Mama der Welt</em></li>
                        <li><em>Kleines Wunder + Geburtsdatum &amp; Uhrzeit auf der Rückseite</em></li>
                        <li><em>Beschützt auf all deinen Wegen</em></li>
                        <li><em>Familie ist, wo das Leben beginnt</em></li>
                        <li><em>Immer an deiner Seite</em></li>
                        <li><em>Glaube · Liebe · Hoffnung</em></li>
                        <li><em>Für immer im Herzen</em></li>
                    </ul>

                    <h3>3. Materialkunde: 585er Echtgold vs. 333er Gold vs. 14k Ion-Plating Edelstahl</h3>
                    <p>Damit du genau das Schmuckstück wählst, das zu deinem Anlass und Budget passt, findest du hier den ehrlichen Unterschied:</p>
                    <ul>
                        <li><strong>585er Echtgold (14 Karat, gestempelt 585):</strong> Besteht zu 58,5 % aus reinem Feingold. Es besitzt einen satten, warmen Goldton, läuft nicht an, ist besonders hautverträglich und behält seinen realen Edelmetallwert über Generationen hinweg. Genau dieses Gold verwenden wir für den Herzanhänger unseres <a href="/produkt/echtgold-seelen-herz">Echtgold Seelen-Herzens</a>.</li>
                        <li><strong>333er Echtgold (8 Karat, gestempelt 333):</strong> Besteht zu 33,3 % aus Feingold und ist durch seine Legierung besonders zugfest und formstabil im Alltag. Deshalb kombinieren wir unser 585er Gold-Herz im Set mit einer feinen, reißfesten 333er Echtgold-Panzerkette.</li>
                        <li><strong>14 Karat vergoldeter Edelstahl (Ion-Plating):</strong> Der Kern besteht aus unverwüstlichem Edelstahl, der im Vakuum-Ionen-Verfahren mit einer abriebfesten Goldschicht veredelt wird. Unser <a href="/produkt/goldenes-seelen-herz">Goldenes Seelen-Herz (49,90 €)</a> ist damit die perfekte Wahl, wenn du ein langlebiges, wasserfestes Geschenk unter 50 € suchst.</li>
                    </ul>

                    <p>Bereit für dein Unikat? Entdecke jetzt unser <a href="/shop?collectionWorld=atelier"><strong>Atelier Echtgold &amp; Silber</strong></a> oder gestalte dein Schmuckstück direkt in der <a href="/shop?collectionWorld=manufaktur"><strong>Manufaktur-Kollektion</strong></a>!</p>
                ',
                'featured_image' => 'marketing/blog/atelier-echtgold-gravur.webp',
                'status' => 'published',
                'published_at' => Carbon::parse('2026-10-28 09:00:00'),
                'meta_title' => '35 kurze Gravursprüche für Ketten & Anhänger + Echtgold Ratgeber | Mein-Seelenfunke',
                'meta_description' => 'Die 35 schönsten kurzen Gravursprüche für Herzanhänger, Ketten & Armbänder (Liebe, Familie, Taufe) + Material-Vergleich: 585er Echtgold vs. 333er Gold & Edelstahl.',
                'meta_keywords' => 'Gravursprüche kurz, Gravur Ideen Kette, Herzanhänger Gravur Text, Unterschied 333 und 585 Gold, 585 Gold Herz mit Gravur',
                'is_advertisement' => false,
                'author_name' => 'Alina Steinhauer',
                'created_at' => Carbon::parse('2026-10-28 09:00:00'),
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

    /**
     * Erzeugt für alle Blog-Beiträge konsistente, edle Gold-Line-Art-Embleme auf tiefschwarzem Grund (#000000)
     * im Format 1200x675 (16:9 WebP) für ein ruhiges, markenkonformes Magazin-Erscheinungsbild.
     */
    private function generateGoldenBlogEmblems(): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        $targetDir = storage_path('app/public/marketing/blog');
        File::ensureDirectoryExists($targetDir);

        $emblemMap = [
            'willkommen-manufaktur.webp'               => public_path('shop/frontend/other/trophy.png'),
            'funki-manufaktur.webp'                    => public_path('shop/frontend/process/lasergravur.webp'),
            'herzensprojekte-kitas.webp'               => public_path('shop/frontend/other/herzensprojekt.png'),
            'hundemarke-ratgeber.webp'                 => public_path('shop/frontend/other/unikat.png'),
            'personalisierte-geschenke-vergleich.webp' => public_path('shop/frontend/other/menge.png'),
            'atelier-echtgold-gravur.webp'             => public_path('shop/frontend/other/handmade.png'),
        ];

        foreach ($emblemMap as $targetFilename => $sourcePath) {
            if (!is_file($sourcePath)) {
                continue;
            }
            $this->compositeEmblemOnBlackCanvas($sourcePath, $targetDir . '/' . $targetFilename);
        }
    }

    private function compositeEmblemOnBlackCanvas(string $sourcePath, string $destPath): void
    {
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $src = match ($ext) {
            'webp' => @imagecreatefromwebp($sourcePath),
            'png'  => @imagecreatefrompng($sourcePath),
            default => null,
        };

        if (!$src) {
            return;
        }

        // Falls 8-Bit-Paletten-PNG: in echtes RGBA-TrueColor wandeln, damit Transparenz sauber erhalten bleibt
        imagepalettetotruecolor($src);
        imagealphablending($src, true);
        imagesavealpha($src, true);

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        // Sichtbare Bounding-Box der goldenen Linien ermitteln, damit jedes Emblem perfekt zentriert & skaliert sitzt
        $minX = $srcW;
        $minY = $srcH;
        $maxX = 0;
        $maxY = 0;

        for ($y = 0; $y < $srcH; $y += 2) {
            for ($x = 0; $x < $srcW; $x += 2) {
                $rgba = imagecolorat($src, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha < 115) {
                    if ($x < $minX) $minX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($x > $maxX) $maxX = $x;
                    if ($y > $maxY) $maxY = $y;
                }
            }
        }

        if ($maxX <= $minX || $maxY <= $minY) {
            $minX = 0;
            $minY = 0;
            $maxX = $srcW - 1;
            $maxY = $srcH - 1;
        }

        $cropW = max(1, $maxX - $minX + 1);
        $cropH = max(1, $maxY - $minY + 1);

        $canvasW = 1200;
        $canvasH = 675;
        $canvas = imagecreatetruecolor($canvasW, $canvasH);
        imagealphablending($canvas, true);

        // Tiefschwarzer Hintergrund (#000000 wie im Referenzbild)
        $black = imagecolorallocate($canvas, 0, 0, 0);
        imagefilledrectangle($canvas, 0, 0, $canvasW, $canvasH, $black);

        // Emblem auf ca. 68 % der Canvas-Höhe skalieren
        $maxTargetH = (int) round($canvasH * 0.70);
        $maxTargetW = (int) round($canvasW * 0.62);
        $scale = min($maxTargetW / $cropW, $maxTargetH / $cropH);

        $dstW = max(1, (int) round($cropW * $scale));
        $dstH = max(1, (int) round($cropH * $scale));
        $dstX = (int) round(($canvasW - $dstW) / 2);
        $dstY = (int) round(($canvasH - $dstH) / 2);

        imagecopyresampled($canvas, $src, $dstX, $dstY, $minX, $minY, $dstW, $dstH, $cropW, $cropH);

        imagewebp($canvas, $destPath, 92);
        imagedestroy($src);
        imagedestroy($canvas);
    }
}
