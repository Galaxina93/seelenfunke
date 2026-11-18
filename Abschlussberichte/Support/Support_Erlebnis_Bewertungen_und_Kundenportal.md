# Support Erlebnis-Bewertungen & Kundenportal „Meine Bewertungen“

> **Stand:** 18.11.2026 | **Abteilungen:** Support (`Bestelli`) & Produkte (`Produkti`)

## 1. Überblick & Unternehmerischer Zweck
Schnelles, friktionsfreies Echtzeit-Feedback verifizierter Bestandskunden an den drei zentralen Erlebnis-Knotenpunkten von **Mein-Seelenfunke**, gekoppelt an eine zentrale Auswertung unter **Shopverwaltung -> Support -> Bewertungen** (`/admin/support/reviews`) sowie den **Operativen Status** auf `/admin/dashboard`.

## 2. Dauerhafte Kassen-Buttons & 5-Sterne Mikro-Widget (`<x-shop.experience-star-widget>`)
- **Kein 15-Sekunden-Countdown mehr im Konfigurator:** Nach Klick auf `In den Warenkorb` bleibt `hasAddedToCart = true` in `scripts_frontend_1.blade.php` und `footer.blade.php` dauerhaft aktiv, sodass `Direkt zur Kasse` und `Zum Warenkorb wechseln` ruhig stehen bleiben (während der Hauptbutton nach `2500 ms` für weitere Exemplare wieder freigegeben wird).
- **3 Platzierungen (nur eingeloggte, E-Mail-verifizierte Kunden; 0 Gäste):**
  1. **Produktkonfigurator (`area="configurator"`):** Direkt oberhalb von `Direkt zur Kasse` / `Zum Warenkorb wechseln` nach erfolgreichem Hinzufügen in den Warenkorb.
  2. **3D-Funkenreich (`area="funkenreich"`):** Direkt oberhalb von `Ins Funkenreich` im Kunden-Dashboard und Funki-Cockpit, sobald das Funkenreich mindestens einmal besucht wurde (`sf_visited_funkenreich_v1`).
  3. **Angebots-Kalkulator (`area="calculator"`):** Direkt oberhalb der Angebotsanfrage in `product-calculator.blade.php`.
- **1-Klick-Interaktion (`POST /customer/experience-reviews`):**
  - **4 bis 5 Sterne:** Sofortige Speicherung (`status = 'done'`) ohne Textfeld-Zwang; ruhige Bestätigung `Danke!` + `Ändern` zur direkten Verklicker-Korrektur. Beim nächsten Seitenaufruf wird das bereits bewertete Widget automatisch ausgeblendet.
  - **1 bis 3 Sterne:** Sofortige Speicherung (`status = 'open'`) + Aufklappen eines kompakten Einzeilers (`Was können wir verbessern?`, max. 500 Zeichen, HTML-bereinigt).

## 3. Kundenportal „Meine Bewertungen“ (`/reviews`) & Zentrale „Deine Erfahrung teilen“-Architektur
- Eigene Livewire-Seite `App\Livewire\Customer\CustomerReviewsComponent` (verlinkt in der Desktop-Kopfleiste und im Profil-Dropdown auf Desktop & Mobil; die 5er-Mobile-Bottom-Bar bleibt schlank).
- **Tab 1 (`Produktbewertungen` · 100 % DRY Single-Source-of-Truth):** Sowohl `ProductReviews` (`/produkt/{slug}`) als auch `CustomerReviewsComponent` (`/reviews`) nutzen denselben zentralen Trait `App\Livewire\Traits\ManagesCustomerProductReviewForm` und die zentrale Blade-Komponente `<x-shop.product-review-experience-form>` (interaktive 5-Sterne-Auswahl mit dynamischem Funki-Maskottchen, optionale Zusammenfassung, 1000-Zeichen-Feedback, Drag-&-Drop Foto-/Video-Upload mit EXIF/GPS-Entfernung & automatischer `1600x1600`-WebP-Komprimierung, `Anonym bewerten`, AGB-§-8a-/Datenschutz-Einwilligung, Lightbox-Slider im selben Fenster sowie faire Rückbuchung gutgeschriebener Bewertungs-Funken beim Löschen).
- **Tab 2 (`Erlebnis-Feedback`):** Alle 3 System-Bereiche (`Produktkonfigurator`, `3D-Funkenreich`, `Angebots-Kalkulator`) jederzeit einsehen, neu bewerten oder nachträglich abändern (`UNIQUE(customer_id, area)`).

## 4. Admin-Cockpit (`/admin/support/reviews`) & `/admin/dashboard`
- Livewire-Komponente `App\Livewire\Shop\Support\SupportReviews` im `Support`-Abteilungs-Theming (Light- & Dark-Mode) mit Bereichs-Durchschnitten, Filterung nach offenen `1 bis 3 ★`-Rückmeldungen, `Details`-Modal und `Offen`/`Erledigt`-Umschaltung.
- Strikte Rollen-Isolation gegen `influencer` und `tax_advisor` sowie Einbindung offener `1 bis 3 ★`-Einträge in `MasterAnalytics` auf `/admin/dashboard`.
