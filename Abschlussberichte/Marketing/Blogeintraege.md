# Marketing - Blogeinträge

Dieses Dokument beschreibt das Content-Management-System (CMS) für Blogartikel im Laravel-Projekt. Neben den klassischen CRUD-Funktionalitäten enthält das Modul integrierte Mechanismen für SEO-Optimierung (Meta-Tags) und rechtliche Kennzeichnungspflichten (Werbehinweise).

## Zielsetzung
Das Blog-System dient dem Content-Marketing, um die organische Reichweite (SEO) über wertvollen, themenbezogenen Inhalt zu steigern. Es erlaubt Redakteuren, Artikel zu entwerfen, Kategorien zu verwalten, Medien hochzuladen und Veröffentlichungen zeitlich zu planen.

---

## Beteiligte Komponenten & Modelle

### Backend-Livewire-Controller
* [MarketingBlog](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingBlog.php)
  * Steuert das Erstellen, Bearbeiten und Löschen von Blogposts.
  * Verwaltet das Kategoriemanagement per Modal-Dialog.
  * Übernimmt den Bild-Upload für Vorschaubilder (featured images) und Header-Bilder.

### Frontend-Livewire-Controller
* [MarketingBlogIndex](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingBlogIndex.php)
  * Listet alle veröffentlichten Blog-Artikel chronologisch sortiert auf.
* [MarketingBlogShow](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingBlogShow.php)
  * Detailansicht eines Artikels inklusive Rendern des HTML-Inhalts und der SEO-Meta-Daten.

### Modelle
* [MarketingBlogPost](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Marketing/MarketingBlogPost.php)
  * Enthält die Felder `title`, `slug`, `content`, `excerpt`, `status` (`draft`, `published`, `scheduled`), `published_at`, `featured_image`, `header_image`, `author_name` sowie die Beziehungen zu Kategorie und Autor.
* [MarketingBlogCategory](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Marketing/MarketingBlogCategory.php)
  * Ermöglicht die Gruppierung von Artikeln (`name`, `slug`).

---

## Technische Kernfunktionen & Richtlinien

### 1. Status & Veröffentlichungssteuerung
Ein Blogpost kann drei Zustände annehmen:
* `draft`: Ein reiner Entwurf, der im Frontend verborgen bleibt.
* `published`: Der Artikel ist sofort öffentlich sichtbar.
* `scheduled`: Der Artikel wird erst ab dem in `published_at` definierten Zeitpunkt im Frontend ausgespielt.

### 2. SEO-Optimierung (Search Engine Optimization)
Zur SEO-Verbesserung sind dedizierte Felder im Modell vorhanden, die strikten Längenvalidierungen unterliegen:
* **Meta-Title (`meta_title`)**: Maximal **60 Zeichen**. Dieser Wert überschreibt den Standard-HTML-Titel im `<head>` des Frontends.
* **Meta-Description (`meta_description`)**: Maximal **160 Zeichen**. Liefert die Kurzbeschreibung für Suchmaschinenergebnisse.

### 3. Legal Compliance (Rechtliche Kennzeichnungen)
Zur Einhaltung des Medienstaatsvertrags (MStV) und des Gesetzes gegen den unlauteren Wettbewerb (UWG) sind zwei Flags integriert:
* **Werbung (`is_advertisement`)**: Kennzeichnet den Beitrag optisch im Frontend gut sichtbar als Anzeige/Werbung, falls er bezahlte Produktplatzierungen enthält.
* **Affiliate-Links (`contains_affiliate_links`)**: Informiert den Leser transparent über das Vorhandensein von Provisions-Links.

---

## Dateiverarbeitung & Upload
Über das `WithFileUploads`-Trait von Livewire werden zwei separate Bilder pro Post unterstützt:
1. **Featured Image (`image`)**: Ein quadratisches Kachelbild für Übersichtslisten (Speicherpfad: `marketing/blog` im `public`-Disk, max. 5 MB).
2. **Header-Hintergrundbild (`headerImage`)**: Ein hochauflösendes Bannerbild für die Detailseite (Speicherpfad: `marketing/blog/headers` im `public`-Disk, max. 10 MB).
 Beide Uploads werden live validiert (Erlaubte Dateitypen: `jpeg, png, jpg, webp`).
