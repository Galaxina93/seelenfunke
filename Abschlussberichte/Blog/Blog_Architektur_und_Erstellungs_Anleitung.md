# Abschlussbericht & Redaktions-Leitfaden: Magazin & Ratgeber (`/blog`)

**Projekt:** Mein-Seelenfunke (Manufaktur Gifhorn)  
**Bereich:** Shop-Frontend (`/blog`), Admin-Backend (`/admin/marketing-blog`) & Seeder (`MarketingBlogSeeder.php`)  
**Status:** Produktiv & Verifiziert  

---

## 1. Strategische Ausrichtung: Evergreen-SEO-Trichter statt Firmen-Tagebuch

Klassische Online-Shop-Blogs scheitern im E-Commerce fast immer an zwei Problemen: Entweder wirken sie nach wenigen Monaten verwaist, weil sichtbare Datumsangaben aus der Vergangenheit das Vertrauen senken, oder sie lenken kaufbereite Besucher im Hauptmenü vom eigentlichen Produktkatalog ab.

Für **Mein-Seelenfunke** wurde das Magazin deshalb gezielt als **zeitloser Google-Suchmaschinen-Trichter (Evergreen-Ratgeber)** konzipiert:

1. **Entfernung aus der oberen Hauptnavigation:**  
   In der Kopfzeile (`resources/views/frontend/navigation/navigation.blade.php`) befinden sich ausschließlich kaufrelevante Einstiege (`Startseite`, `Manufaktur`, `Konfigurator`, `Shop`). Wer den Shop direkt betritt, wird ohne Umwege zu den drei Produktwelten geführt.
2. **Auffindbarkeit über Google (`sitemap.xml`) und den Footer:**  
   Im Footer ist der Bereich dezent unter **Magazin & Ratgeber** (`/blog`) verankert. Zusätzlich übergibt der `SeoController` jeden veröffentlichten Artikel automatisch an die `sitemap.xml`. Jeder Ratgeber-Beitrag fungiert damit als eigenständige Google-Landingpage für konkrete Suchanfragen mit Kaufabsicht (z. B. *„Gravursprüche Echtgold Herz“*, *„Unterschied 333 und 585 Gold“*, *„Was muss auf eine Hundemarke“*).
3. **Verzicht auf sichtbare Datumsangaben (Evergreen-Schutz):**  
   Weder auf den Übersichts-Kacheln (`marketing-blog-index.blade.php`) noch im geöffneten Artikel (`marketing-blog-show.blade.php`) wird dem Besucher ein Veröffentlichungsdatum angezeigt. Stattdessen steht dort das zeitlose Label `Manufaktur-Ratgeber`. So bleiben alle Fachartikel über Jahre hinweg aktuell und hochwertig.
4. **Direkte Verlinkung in die 3 Produktwelten:**  
   Jeder Artikel beantwortet eine echte Kundenfrage und führt den Leser über direkte Textlinks und die Manufaktur-Empfehlungsbox am Artikelende unmittelbar zum passenden Produkt im Shop (z. B. `/produkt/echtgold-seelen-herz`, `/produkt/seelen-kristall`, `/produkt/hundemarke-rund-30mm` oder `/shop?collectionWorld=atelier`).

---

## 2. Visuelle Richtlinie: Goldene Line-Art-Embleme & Matte Beige-Akzente

Damit das Magazin ruhig, edel und wie aus einem Guss wirkt, gelten für alle Grafiken und UI-Elemente verbindliche Vorgaben:

### 2.1 Titelbilder (Gold-Embleme auf `#000000` Schwarz)
* **Keine unruhigen Symbolfotos:** Es werden keine zusammengewürfelten Stock- oder Illustrationsbilder verwendet.
* **Format & Aufbau:** Jedes Kachel- und Headerbild ist eine `1200 x 675 px` große WebP-Grafik (Seitenverhältnis 16:9) mit tiefschwarzem Hintergrund (`#000000`) und einem zentrierten, feinen goldenen Line-Art-Emblem aus den Manufaktur-Assets (`public/shop/frontend/other/` bzw. `public/shop/frontend/process/`).
* **Automatische Generierung im Seeder:** Die Methode `generateGoldenBlogEmblems()` in `database/seeders/MarketingBlogSeeder.php` liest die transparenten Gold-Grafiken ein, ermittelt automatisch die sichtbare Bounding-Box der goldenen Linien und rendert das Motiv exakt mittig auf die schwarze `1200 x 675 px` Leinwand unter `storage/app/public/marketing/blog/`.

### 2.2 Farbgebung der Kacheln & Badges
* **Kategorie-Pills & Filter-Buttons:** Nutzen passend zum Footer und zur Shop-Kollektion einen warmen, matten Beigeton (`bg-[#F5F1E8]`, Rahmen `border-[#E2D9C5]`) kombiniert mit tiefschwarzer Schrift (`text-gray-900`).
* **Aktiver Filter-Tab:** Tiefschwarz (`bg-gray-900 text-white`) für maximalen Kontrast im hellen Light-Mode.

---

## 3. Verbindliche Text- & Qualitätsregeln (Null Gedankenstriche, 100 % Manufaktur-Stil)

Alle Texte im Magazin müssen sich wie echte, fachkundige Beratung aus der Gifhorner Manufaktur lesen.

1. **Absolutes Verbot von Gedankenstrichen:**  
   In Überschriften, Vorschautexten (`excerpt`), Artikelinhalten (`content`) sowie SEO-Titeln (`meta_title`) und SEO-Beschreibungen (`meta_description`) dürfen **keine Gedankenstriche** (Halbgeviert- oder Geviertstriche sowie die HTML-Entities `&ndash;` und `&mdash;`) verwendet werden.
   * **Vermeiden:** Trennung von Satzteilen durch Gedankenstriche.
   * **Richtig (mit Doppelpunkt):** `585er Echtgold: Warum sich die Legierung lohnt`
   * **Richtig (mit ausgeschriebenem Bindewort & Komma):** `Größe 12 bis 14 mm, inklusive Geschenkbox`
2. **Keinerlei Hinweise auf automatisierte Texterstellung:**  
   Weder im sichtbaren Text noch in Bild-Badges, Kommentaren oder Metadaten dürfen Hinweise auf maschinelle Texterstellung oder phrasenhafte Einleitungen vorkommen.
3. **Technischer Schutzfilter im System:**  
   In `App\Livewire\Shop\Marketing\MarketingBlog` (`sanitizeBlogText()` und `sanitizeBlogHtml()`) sowie in `App\Services\AI\Functions\AiMarketingBlogFuncs` ist ein automatischer Bereinigungsfilter aktiv. Sobald ein Beitrag gespeichert oder aktualisiert wird, wandelt das System eventuell vorhandene Gedankenstriche automatisch in saubere Satzzeichen (`bis`, `, ` oder `-`) um und entfernt unzulässige Textmarker.

---

## 4. Schritt-für-Schritt-Anleitung: Neuen Blogbeitrag erstellen

Neue Artikel können auf zwei Wegen angelegt werden. Für dauerhafte Kern-Ratgeber wird **Weg A (über den Seeder)** empfohlen, damit die Beiträge bei jeder Neuinstallation oder Datenbank-Synchronisation erhalten bleiben.

### Weg A: Über den Seeder (`database/seeders/MarketingBlogSeeder.php`)

#### Schritt 1: Suchbegriff (Keyword) & Produktziel wählen
Wähle eine konkrete Frage aus, die potenzielle Kunden bei Google eingeben, bevor sie ein personalisiertes Schmuckstück oder Kristallglas kaufen:
* *Beispiel 1:* „Taufgeschenk mit Gravur von Patentante: Ideen & Sprüche“ -> Zielprodukt: Echtgold Seelen-Herz & Seelen-Talisman.
* *Beispiel 2:* „3D-Lasergravur im Kristallglas: Welches Foto eignet sich am besten?“ -> Zielprodukt: Der Seelen Kristall.

#### Schritt 2: Goldenes Emblem in `generateGoldenBlogEmblems()` zuweisen
Öffne `database/seeders/MarketingBlogSeeder.php` und ergänze in der Methode `generateGoldenBlogEmblems()` im Array `$emblemMap` den Dateinamen für das neue WebP-Bild:

```php
$emblemMap = [
    'willkommen-manufaktur.webp'               => public_path('shop/frontend/other/trophy.png'),
    'funki-manufaktur.webp'                    => public_path('shop/frontend/process/lasergravur.webp'),
    'herzensprojekte-kitas.webp'               => public_path('shop/frontend/other/herzensprojekt.png'),
    'hundemarke-ratgeber.webp'                 => public_path('shop/frontend/other/unikat.png'),
    'personalisierte-geschenke-vergleich.webp' => public_path('shop/frontend/other/menge.png'),
    'atelier-echtgold-gravur.webp'             => public_path('shop/frontend/other/handmade.png'),
    // Neues Bild hier eintragen:
    'taufgeschenk-gravur-ratgeber.webp'        => public_path('shop/frontend/other/handmade.png'),
];
```

#### Schritt 3: Beitrag im Array `$posts` eintragen
Füge im selben Seeder unter `$posts` einen neuen Eintrag nach folgendem Muster hinzu (ohne Gedankenstriche):

```php
[
    'id' => Str::uuid()->toString(),
    'user_id' => $adminId,
    'blog_category_id' => $catRatgeberId, // $catRatgeberId, $catNewsId oder $catHerzensId
    'title' => 'Taufgeschenk mit Gravur: Zeitlose Ideen und Sprüche für Patenkinder',
    'slug' => 'taufgeschenk-mit-gravur-ideen-sprueche',
    'excerpt' => 'Ein Taufgeschenk begleitet ein Kind oft das ganze Leben. Hier findest du die schönsten Gravursprüche für Echtgold-Anhänger, Edelstahl-Talismane und Kristallglas.',
    'content' => '
        <p>Zur Taufe wünschen sich Paten und Großeltern ein Geschenk, das auch nach vielen Jahren noch seinen emotionalen und materiellen Wert behält.</p>

        <h3>1. Echtgold oder Edelstahl: Welches Material passt zur Taufe?</h3>
        <p>Für ein lebenslanges Erinnerungsstück empfiehlt sich unser <a href="/produkt/echtgold-seelen-herz">Echtgold Seelen-Herz aus 585er Gold mit 333er Gold-Panzerkette</a>. Wer einen robusten Begleiter für den späteren Schulranzen oder Schlüsselbund sucht, wählt den <a href="/produkt/seelen-talisman">Seelen-Talisman aus Edelstahl</a>.</p>

        <h3>2. Die schönsten kurzen Tauf-Gravursprüche</h3>
        <ul>
            <li><strong>Klassisch mit Datum:</strong> <em>Behütet &amp; Geliebt | 14.06.2026</em></li>
            <li><strong>Mit Namen des Kindes:</strong> <em>Dein Schutzengel für immer</em></li>
            <li><strong>Für die Rückseite:</strong> <em>In Liebe, deine Patentante</em></li>
        </ul>
    ',
    'featured_image' => 'marketing/blog/taufgeschenk-gravur-ratgeber.webp',
    'status' => 'published',
    'published_at' => Carbon::parse('2026-10-30 10:00:00'),
    'meta_title' => 'Taufgeschenk mit Gravur: Sprüche & Schmuck-Ideen | Ratgeber',
    'meta_description' => 'Die schönsten Gravursprüche zur Taufe für Echtgold-Schmuck, Edelstahl-Anhänger und K9-Kristallglas aus der Manufaktur Gifhorn.',
    'meta_keywords' => 'Taufgeschenk mit Gravur, Gravursprüche Taufe, Echtgold Herz Taufe, Patenkind Geschenk Gravur',
    'is_advertisement' => false,
    'author_name' => 'Alina Steinhauer',
    'created_at' => Carbon::parse('2026-10-30 10:00:00'),
    'updated_at' => now(),
],
```

#### Schritt 4: Seeder ausführen
Führe im Terminal den Seeder aus, um das goldene Emblem zu generieren und den Beitrag in der Datenbank zu speichern:

```bash
docker exec mein_php_server php artisan db:seed --class=MarketingBlogSeeder
```

---

### Weg B: Direkt im Admin-Backend (`/admin/marketing-blog`)

1. Öffne im Admin-Backend den Menüpunkt **Marketing -> Magazin & Blog** (`/admin/marketing-blog`) und klicke oben rechts auf **Neuer Beitrag**.
2. **Titel & Permalink (Slug):** Gib einen klaren Ratgeber-Titel mit Doppelpunkt statt Gedankenstrich ein (der URL-Slug wird automatisch erzeugt).
3. **Inhalt strukturieren:**
   * Nutze Zwischenüberschriften (`Überschrift 3` / `<h3>`) und Aufzählungslisten (`<ul>/<li>`), damit Leser auf dem Smartphone schnell die gewünschte Antwort finden.
   * Setze mindestens **2 bis 3 interne Links** auf passende Produkte im Shop (z. B. `/produkt/echtgold-seelen-herz` oder `/shop?collectionWorld=atelier`).
4. **Kurzbeschreibung (Excerpt):** Verfasse 2 prägnante Sätze für die Kachel-Übersicht.
5. **SEO-Felder:**
   * **Meta Titel:** Maximal 60 Zeichen, Haupt-Suchbegriff am Anfang.
   * **Meta Beschreibung:** Maximal 160 Zeichen, klare Zusammenfassung mit Handlungsaufforderung.
6. **Kachelbild:** Lade ein `1200 x 675 px` WebP-Bild mit goldenem Line-Art-Emblem auf schwarzem Hintergrund (`#000000`) hoch.
7. **Status & Speichern:** Stelle den Status auf `Published (Online)` und klicke auf **Veröffentlichen**.

---

## 5. Checkliste vor jeder Veröffentlichung

- [ ] **Kein Gedankenstrich enthalten:** Weder im Titel noch im Text oder in den Meta-Daten kommt ein Gedankenstrich vor (stattdessen Doppelpunkt, Komma oder `bis` verwenden).
- [ ] **Echter Beratungs-Mehrwert:** Der Artikel beantwortet eine reale Frage (Materialkunde, Gravursprüche, Pflegehinweise, Geschenkanlass).
- [ ] **Ehrliche Materialangaben:** Strikte Trennung zwischen `585er / 333er Echtgold & 925er Sterlingsilber` (Atelier) und `14 Karat vergoldetem Edelstahl (Ion-Plating)` bzw. `K9-Kristallglas` (Manufaktur-Kollektion).
- [ ] **Interne Shop-Verlinkung:** Mindestens zwei direkte Links führen auf Produkte oder Kollektions-Welten im Shop.
- [ ] **Goldenes Line-Art-Emblem auf Schwarz:** Das Kachelbild nutzt den einheitlichen `#000000` Hintergrund mit goldenem Manufaktur-Emblem.

---

## 6. Übersicht der verankerten Basis-Artikel (`MarketingBlogSeeder.php`)

| Nr. | Titel | URL-Slug (`/blog/...`) | Kategorie | Emblem-Datei (`storage/app/public/marketing/blog/`) |
| :--- | :--- | :--- | :--- | :--- |
| **1** | Willkommen bei Mein-Seelenfunke: Unsere Manufaktur für bleibende Unikate | `willkommen-bei-mein-seelenfunke` | Manufaktur & Einblicke | `willkommen-manufaktur.webp` |
| **2** | Vom 3D-Konfigurator bis zum UV-Laser: Wie dein Unikat in Gifhorn entsteht | `gestatten-funki-funke` | Manufaktur & Einblicke | `funki-manufaktur.webp` |
| **3** | Ein Funke, der Gutes tut: Unsere regionalen Herzensprojekte | `ein-funke-der-gutes-tut-unsere-herzensprojekte` | Herzensprojekte | `herzensprojekte-kitas.webp` |
| **4** | Was muss auf eine Hundemarke? Pflichtangaben, Tipps und langlebige Lasergravur | `was-muss-auf-eine-hundemarke-ratgeber` | Ratgeber & Geschenkideen | `hundemarke-ratgeber.webp` |
| **5** | Personalisierte Geschenke mit Gravur: Warum K9-Kristall, Edelstahl und Echtgold Jahrzehnte überdauern | `personalisierte-geschenke-hochzeit-jahrestag-ratgeber` | Ratgeber & Geschenkideen | `personalisierte-geschenke-vergleich.webp` |
| **6** | Gravursprüche für Echtgold & Silber: 25 kurze Ideen und der Unterschied zwischen 333er und 585er Gold | `gravursprueche-echtgold-silber-schmuck-ratgeber` | Ratgeber & Geschenkideen | `atelier-echtgold-gravur.webp` |
