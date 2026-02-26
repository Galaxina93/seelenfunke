<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BlogSeeder extends Seeder
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

        // 2. Kategorie erstellen
        $catId = Str::uuid()->toString();
        DB::table('blog_categories')->insertOrIgnore([
            'id' => $catId,
            'name' => 'Neuigkeiten',
            'slug' => 'neuigkeiten',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = DB::table('blog_categories')->where('slug', 'neuigkeiten')->value('id');

        // 3. Blog Posts erstellen

        $posts = [
            // POST 1: Gründung & Maßstäbe
            [
                'id' => Str::uuid()->toString(),
                'user_id' => $adminId,
                'blog_category_id' => $categoryId,
                'title' => 'Willkommen bei Mein-Seelenfunke – Wir setzen neue Maßstäbe!',
                'slug' => 'willkommen-bei-mein-seelenfunke',
                'excerpt' => 'Es ist soweit: Mein-Seelenfunke öffnet die Tore. Wir sind angetreten, um Emotionen in Glas zu bannen und Qualität neu zu definieren.',
                'content' => '
                    <p>Wir freuen uns riesig, euch endlich bei <strong>Mein-Seelenfunke</strong> begrüßen zu dürfen!</p>

                    <p>Hinter uns liegen Monate der Planung, des Designs und der Leidenschaft. Unser Ziel war es nicht einfach nur, einen weiteren Online-Shop zu eröffnen. Wir wollten einen Ort schaffen, an dem Geschenke wieder eine echte Bedeutung haben.</p>

                    <h3>Unsere Vision</h3>
                    <p>In einer schnelllebigen Welt möchten wir Momente festhalten. Ob durch unsere massiven K9-Kristalle oder zukünftige Kollektionen – jedes Stück wird mit höchster Präzision gefertigt. Wir setzen auf Qualität, die man spüren kann, und auf einen Service, der von Herzen kommt.</p>

                    <p>Dies ist erst der Anfang unserer Reise. Wir haben große Pläne und freuen uns, dass ihr von Anfang an dabei seid, um gemeinsam neue Maßstäbe zu setzen.</p>
                ',
                // Pfad relativ zu storage/app/public/
                'featured_image' => 'blog/mein-seelenfunke-logo.png',
                'status' => 'published',
                'published_at' => Carbon::now()->subDays(2),
                'meta_title' => 'Mein-Seelenfunke Gründung – Neue Maßstäbe für Geschenke',
                'meta_description' => 'Mein-Seelenfunke ist gestartet. Erfahre mehr über unsere Vision, hochwertige Kristallglas-Geschenke und unsere Philosophie.',
                'meta_keywords' => 'Gründung, Vision, Seelenfunke, Qualität, K9 Kristall',
                'is_advertisement' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // POST 2: Funki Funke
            [
                'id' => Str::uuid()->toString(),
                'user_id' => $adminId,
                'blog_category_id' => $categoryId,
                'title' => 'Gestatten: Funki Funke – Mehr als nur ein Maskottchen',
                'slug' => 'gestatten-funki-funke',
                'excerpt' => 'Dürfen wir vorstellen? Funki Funke ist da! Er ist das freundliche Gesicht hinter unserer Technik und freut sich darauf, euch kennenzulernen.',
                'content' => '
                    <p>Hallo zusammen! 👋</p>

                    <p>Es wird Zeit, das bestgehütete Geheimnis unseres Teams zu lüften. Wir haben Zuwachs bekommen, und er ist... nun ja, ziemlich strahlend!</p>

                    <h3>Wer ist Funki?</h3>
                    <p>Funki ist ein zum leben erwecktes Lasergerät. Sein voller Name ist <strong>Funki Funke</strong> (ja, der Nachname ist purer Zufall 😉). Funki ist nicht nur unser Maskottchen, das gut auf Bildern aussieht. Er spielt eine zentrale Rolle in unserem System.</p>

                    <p>Funki liebt Effizienz. In Zukunft werdet ihr ihm öfter begegnen:</p>
                    <ul>
                        <li>Er hilft bei der Bestellabwicklung.</li>
                        <li>Er informiert euch über den Status eurer Pakete.</li>
                        <li>Er sorgt im Hintergrund dafür, dass alle Prozesse reibungslos ineinandergreifen.</li>
                    </ul>

                    <p>Funki freut sich riesig darauf, euch alle kennenzulernen und euch ein Lächeln ins Gesicht zu zaubern, während er im Hintergrund die Fäden der Automatisierung zieht.</p>

                    <p>Sagt "Hallo" zu Funki!</p>
                ',
                // Pfad relativ zu storage/app/public/
                'featured_image' => 'blog/funki.png',
                'status' => 'published',
                'published_at' => Carbon::now(),
                'meta_title' => 'Funki Funke stellt sich vor – Unser Maskottchen',
                'meta_description' => 'Lerne Funki Funke kennen, das Maskottchen von Mein-Seelenfunke. Er unterstützt unsere Automatisierung und den Kundenservice.',
                'meta_keywords' => 'Funki, Maskottchen, Automatisierung, Team, Funke',
                'is_advertisement' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('blog_posts')->insert($posts);
    }
}
