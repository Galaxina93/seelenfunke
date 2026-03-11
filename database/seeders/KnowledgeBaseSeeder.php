<?php

namespace Database\Seeders;

use App\Models\KnowledgeBase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KnowledgeBaseSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            // E-COMMERCE BEGRIFFE
            [
                'title' => 'Marge (Gewinnmarge)',
                'category' => 'E-Commerce Fachbegriffe',
                'tags' => ['Finanzen', 'Kalkulation', 'Gewinn', 'Pricing'],
                'content' => '<h3>Was ist die Marge?</h3><p>Die Marge (auch Gewinnmarge oder Handelsspanne genannt) drückt aus, wie viel Prozent des Verkaufspreises nach Abzug der direkten Kosten (Einkauf, Produktion) als Gewinn beim Unternehmen verbleiben.</p><h3>Berechnung</h3><p><strong>Bruttomarge in % = ((Verkaufspreis - Einkaufspreis) / Verkaufspreis) * 100</strong></p><h3>Bedeutung für Mein Seelenfunke</h3><p>Da wir in der Manufaktur physische Produkte (Rohlinge) veredeln, müssen wir bei der Margenberechnung nicht nur den Glaskristall, sondern auch die anteiligen Laserkosten, die edle Geschenkbox und das Füllmaterial berücksichtigen. Eine gesunde Marge ist essenziell, um Puffer für Marketing (CAC) und unerwartete Kosten zu haben.</p>'
            ],
            [
                'title' => 'Conversion Rate (Konversionsrate)',
                'category' => 'E-Commerce Fachbegriffe',
                'tags' => ['Marketing', 'KPI', 'Analytics', 'Shop-Performance'],
                'content' => '<h3>Was ist die Conversion Rate?</h3><p>Die Conversion Rate (CR) misst den prozentualen Anteil der Website-Besucher, die eine gewünschte Aktion (in unserem Fall: einen Kauf) ausführen.</p><h3>Berechnung</h3><p><strong>CR = (Anzahl der Käufe / Anzahl der Besucher) * 100</strong></p><h3>Bedeutung für Mein Seelenfunke</h3><p>Ein guter Shop hat in der Regel eine CR zwischen 1,5% und 3%. Wenn unsere Conversion Rate sinkt, kann das an einer unklaren Benutzerführung im 3D-Konfigurator, zu hohen Versandkosten oder technischen Fehlern im Checkout liegen. Jeder Prozentpunkt mehr bedeutet direkten Umsatz, ohne mehr Geld für Werbung ausgeben zu müssen.</p>'
            ],
            [
                'title' => 'AOV (Average Order Value)',
                'category' => 'E-Commerce Fachbegriffe',
                'tags' => ['KPI', 'Umsatz', 'Warenkorb'],
                'content' => '<h3>Was ist der AOV?</h3><p>Der AOV (durchschnittlicher Bestellwert) zeigt, wie viel Geld ein Kunde durchschnittlich pro Bestellung in unserem Shop ausgibt.</p><h3>Berechnung</h3><p><strong>AOV = Gesamtumsatz / Anzahl der Bestellungen</strong></p><h3>Bedeutung für Mein Seelenfunke</h3><p>Um den AOV zu steigern, bieten wir Express-Produktion, Mengenrabatte für B2B-Kunden oder Cross-Selling (z. B. passende Leuchtsockel für unsere Seelenkristalle) an. Ein höherer AOV macht unsere Werbeausgaben (CAC) deutlich profitabler.</p>'
            ],
            [
                'title' => 'CAC (Customer Acquisition Cost)',
                'category' => 'E-Commerce Fachbegriffe',
                'tags' => ['Marketing', 'Kosten', 'Ads'],
                'content' => '<h3>Was ist der CAC?</h3><p>Die Customer Acquisition Cost beschreibt die Kosten, die aufgewendet werden müssen, um einen neuen zahlenden Kunden zu gewinnen.</p><h3>Berechnung</h3><p><strong>CAC = Gesamte Marketingausgaben / Anzahl der neu gewonnenen Kunden</strong></p><h3>Bedeutung für Mein Seelenfunke</h3><p>Wenn wir 500 € für Instagram-Ads ausgeben und dadurch 50 Kunden einen Seelenkristall kaufen, liegt unser CAC bei 10 €. Solange unsere Marge pro Kristall höher ist als diese 10 €, arbeiten wir profitabel.</p>'
            ],
            [
                'title' => 'CLV (Customer Lifetime Value)',
                'category' => 'E-Commerce Fachbegriffe',
                'tags' => ['Kundenbindung', 'Umsatz', 'Strategie'],
                'content' => '<h3>Was ist der CLV?</h3><p>Der Customer Lifetime Value schätzt den gesamten Umsatz (oder Gewinn), den ein Kunde während seiner gesamten "Lebenszeit" als Kunde im Shop generiert.</p><h3>Bedeutung für Mein Seelenfunke</h3><p>Geschenke werden oft mehrfach gekauft (Hochzeit, Taufe, Jubiläum). Wenn ein Kunde durch exzellente Qualität (unser "Weiß-Effekt" beim Laser) und tolle Verpackung begeistert ist, kauft er wieder. Das Gamification-Dashboard (Funki-Spiele, Funken-Währung) dient exakt diesem Zweck: Es erhöht die Kundenbindung und somit den CLV drastisch.</p>'
            ],

            // NEU: ENTWICKLUNG & CLI
            [
                'title' => 'App & Projekt Exporte (CLI)',
                'category' => 'Entwicklung & CLI',
                'tags' => ['App', 'Flutter', 'Dart', 'Export', 'Local Server', 'Release'],
                'content' => '<h3>Lokaler Server für die App</h3><p>Um das Laravel Backend so zu starten, dass die lokale App (Emulator oder physisches Gerät im gleichen Netzwerk) darauf zugreifen kann, binden wir den Server an alle Netzwerkschnittstellen:</p><p><code>php artisan serve --host 0.0.0.0 --port 8000</code></p><h3>Backend Projekt Export</h3><p>Eigene Commands zum Exportieren der Backend-Struktur:</p><ul><li>Vollständiger Export: <code>php artisan project:export</code></li><li>Kurzer Export: <code>php artisan project:export --short</code></li></ul><h3>App (Flutter) Export & Release</h3><ul><li>App Projekt exportieren: <code>dart run export_project.dart</code></li><li>App live auf ein angeschlossenes Handy releasen: <code>flutter run --release</code></li></ul>'
            ],
            [
                'title' => 'Node.js & NPM Setup (NPM RUN WATCH)',
                'category' => 'Entwicklung & CLI',
                'tags' => ['NPM', 'Node', 'NVM', 'Frontend', 'Build', 'CSS', 'JS'],
                'content' => '<h3>NVM & Node.js installieren</h3><p>Falls Node.js fehlt oder die falsche Version genutzt wird, hilft der Node Version Manager (NVM):</p><ul><li>NVM installieren: <code>curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash</code></li><li>Konfiguration neu laden: <code>source ~/.bashrc</code></li><li>Node.js Version 20 installieren & nutzen: <code>nvm install 20</code></li></ul><h3>Frontend Build komplett neu aufsetzen (Clean Install)</h3><p>Wenn es Probleme mit den Assets oder dem Vite/Mix Build gibt, hilft dieser harte Reset:</p><ul><li>1. <code>rm -rf node_modules</code></li><li>2. <code>rm package-lock.json</code></li><li>3. <code>npm install</code></li><li>4. <code>npm run watch</code></li></ul>'
            ],
            [
                'title' => 'Livewire Befehle',
                'category' => 'Entwicklung & CLI',
                'tags' => ['Livewire', 'Artisan', 'Docker', 'Component'],
                'content' => '<h3>Neue Livewire Komponente anlegen</h3><p>Wenn das System über Docker läuft, muss der Artisan-Befehl für Livewire zwingend im Container ausgeführt werden.</p><p><strong>Beispiel für eine neue Komponente:</strong><br><code>docker compose exec web php artisan make:livewire Customer.Game.CristallGame</code></p><p>Dadurch stimmt der Namespace und die Dateien werden direkt mit den richtigen Berechtigungen im Container erzeugt.</p>'
            ],
            [
                'title' => 'Queue Worker & Checkout Testing',
                'category' => 'Entwicklung & CLI',
                'tags' => ['Queue', 'Worker', 'Checkout', 'Jobs', 'Sail', 'Testing'],
                'content' => '<h3>Checkout-Prozess testen (Background Jobs)</h3><p>Beim Checkout werden Mails (z.B. Bestellbestätigung) und PDF-Rechnungen in sogenannten Queues (Warteschlangen) verarbeitet, damit der Kunde nicht auf den Seitenaufbau warten muss.</p><h3>Worker im Docker Container starten</h3><p>Da wir im Docker Container arbeiten, müssen wir den Queue Worker über Laravel Sail abfeuern:</p><ol><li>Berechtigung für Sail setzen (falls noch nicht geschehen):<br><code>chmod +x vendor/bin/sail</code></li><li>Worker starten, um anstehende Jobs abzuarbeiten:<br><code>./vendor/bin/sail artisan queue:work</code></li></ol>'
            ],

            // NEU: SERVER & INFRASTRUKTUR
            [
                'title' => 'Docker & Container Management',
                'category' => 'Server & Infrastruktur',
                'tags' => ['Docker', 'Container', 'Commands', 'Deployment', 'Compose'],
                'content' => '<h3>Grundlegende Docker Compose Befehle</h3><ul><li><strong>Container (im Hintergrund) starten:</strong> <code>docker compose up -d</code></li><li><strong>Alten Container stoppen:</strong> <code>docker compose down</code></li><li><strong>Container neu bauen & starten:</strong> <code>docker compose up -d --build</code></li><li><strong>Container zwingend ohne Cache neu bauen (nur Web):</strong> <code>docker compose build --no-cache web</code></li><li><strong>Container restlos löschen (Vorsicht, löscht auch Volumes/DB-Inhalte!):</strong> <code>docker compose down -v</code></li></ul><h3>Artisan-Befehle direkt im PHP Container ausführen</h3><p>Wenn du Befehle nicht über Sail, sondern direkt über den Container-Namen ausführen willst:</p><ul><li>Migration ausführen:<br><code>docker exec -it mein_php_server php artisan migrate</code></li><li>Datenbank komplett neu aufsetzen & seeden:<br><code>docker exec -it mein_php_server php artisan migrate:fresh --seed</code></li></ul>'
            ],
            [
                'title' => 'Linux Berechtigungen & Pfade',
                'category' => 'Server & Infrastruktur',
                'tags' => ['Linux', 'Permissions', 'Chmod', 'Chown', 'Hosts', 'Windows'],
                'content' => '<h3>Wichtige Linux Schreibrechte (Laravel)</h3><p>Damit Laravel fehlerfrei Logs schreiben und Caches generieren kann, benötigen diese Ordner spezielle Schreibrechte:</p><p><code>chmod -R 777 storage bootstrap/cache</code></p><h3>Dateibesitzer korrigieren</h3><p>Wenn Docker-Container Dateien anlegen (z.B. bei einem <code>composer install</code> im Container), gehören diese oft dem Root-User. Um wieder selbst im Code-Editor speichern zu können, muss der Besitzer zurück auf deinen Linux-User geändert werden:</p><p><code>sudo chown -R $USER:$USER .</code></p><h3>Windows Hosts-Datei</h3><p>Falls lokale Domains (z.B. mein-seelenfunke.test) eingerichtet werden müssen, liegt die Hosts-Datei unter Windows hier:</p><p><code>C:\Windows\System32\drivers\etc\hosts</code></p>'
            ],
            [
                'title' => 'Mein Seelenfunke - Unternehmensprofil & Produkte',
                'category' => 'Firmenwissen',
                'tags' => ['seelenfunke', 'produkte', 'k9-kristall', 'schiefer', 'metall', 'manufaktur', 'geschenke'],
                'content' => "<h3>Mein Seelenfunke - Ein Funke, der bleibt</h3>
<p>Wir stellen personalisierte Unikate für die Ewigkeit her. Unsere handveredelten Geschenke bestehen aus hochwertigen Materialien wie Glas (insbesondere K9-Kristall), Schiefer und Metall.</p>
<h4>Unsere Materialien & Veredelungen</h4>
<ul>
<li><strong>K9-Kristall:</strong> Exklusive Glas-Unikate mit höchster optischer Reinheit. Durch Laser-Innengravur entstehen faszinierende 3D-Motive direkt im Glas, ohne die Oberfläche zu beschädigen.</li>
<li><strong>Schiefer & Metall:</strong> Weitere hochwertige Materialien, die wir für individuelle, unvergessliche Geschenke veredeln.</li>
</ul>
<p><strong>Philosophie:</strong> Wir produzieren in Deutschland (Made in Germany), bieten schnellen Versand und verpacken jedes Stück mit Liebe. Kunden können über unseren Angebotskalkulator individuelle Wünsche anfragen oder direkt im Shop personalisierte Unikate bestellen.</p>
<h4>Wichtige Bereiche der Website:</h4>
<ul>
<li>Shop & Angebotskalkulator</li>
<li>Funki Chat (KI-Assistenz)</li>
<li>Seelen-Kristall (3D-Laser-Motive)</li>
<li>Blog & Manufaktur-Einblicke</li>
</ul>",
            ],
            [
                'title' => 'Theresa - Wichtige Daten',
                'category' => 'Team & Kontakte',
                'tags' => ['theresa', 'geburtstag', 'team'],
                'content' => "<p>Theresa hat am <strong>11. März</strong> Geburtstag.</p>",
            ]
        ];

        foreach ($articles as $article) {
            KnowledgeBase::updateOrCreate(
                ['slug' => Str::slug($article['title'])],
                [
                    'title' => $article['title'],
                    'category' => $article['category'],
                    'content' => $article['content'],
                    'tags' => $article['tags'],
                    'is_published' => true,
                ]
            );
        }
    }
}
