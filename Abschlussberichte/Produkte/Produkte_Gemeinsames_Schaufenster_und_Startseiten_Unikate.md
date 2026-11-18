# Gemeinsames `/shop/`-Schaufenster & Startseiten 3-Unikate-Bühne

> **Stand:** 18.11.2026 | **Abteilungen:** Marketing (`Marketi`) & Produkte (`Produkti`)

## 1. Ausgangslage & Usability-Optimierung („Don't Make Me Think“)
- **Vorher:** Das aus 8 handverlesenen Produkten bestehende Sortiment war auf `/shop/` künstlich in drei Kollektions-Tabs (`Manufaktur-Kollektion`, `Atelier Echtgold & Silber`, `Geschenkgutscheine`) gesplittet. Dadurch blieben 37,5 % des Sortiments (925er Echtsilber-/Gold-Vermeil-Armbänder und der Geschenkgutschein) beim Seitenaufruf unsichtbar. Auf der Startseite führten Produktfotos in `#services` auf vorgefilterte Kategorie-Ansichten statt direkt ins Produkt.
- **Nachher:** 1-Klick-Conversion von der Startseite direkt in den Produktkonfigurator sowie ein einziges, offenes Schaufenster auf `/shop/`.

## 2. Startseite (`resources/views/frontend/pages/partials/services_section.blade.php`)
- **Überschrift:** „Unsere beliebtesten Unikate“ im freundlichen Light-Mode (kein 3-Welten-Umschalter, keine Kategorie-Filterkacheln).
- **Exakt 3 echte Produktkarten:** Kuratierte Priorität (`seelen-kristall`, `goldenes-seelen-herz`, `seelen-anhaenger`) mit automatischem Nachrück-Fallback auf verfügbare aktive Unikate.
- **Direktlink:** Klick auf Karte, Bild, Titel oder `Jetzt gestalten →` öffnet unmittelbar `/produkt/{slug}`.
- **Zentraler Call-to-Action:** Darunter führt `Alle Unikate im Shop entdecken →` ungefiltert nach `/shop`.

## 3. Gemeinsames Schaufenster `/shop/` (`ProductFrontendFilterArea`)
- **Alle aktiven Produkte gemeinsam:** Manufaktur-Unikate, die beiden 925er Silber-Armbänder (**„Das Echtsilber Seelenarmband“** und abmahnsicher **„Das Goldene Seelenarmband“**, komplett ohne störendes Zusatz-Badge über `VORBESTELLBAR`) und der `Geschenkgutschein` (harmonisch am Ende sortiert) erscheinen sofort gemeinsam im Grid.
- **Schlanke Filterleiste:** Suchfeld + saisonal kuratierter Anlass-Filter + Kategorie-Dropdown + bedingter `Angebote`-Filter (nur sichtbar, wenn tatsächlich reduzierte Produkte aktiv sind). Der technische `Alle | Personalisiert | Standard`-Schalter entfällt.
- **Bruchsichere Legacy-Links:** Alte Aufrufe wie `/shop?collectionWorld=atelier` oder `/shop?collectionWorld=voucher` normalisieren fehlertolerant auf das gemeinsame Schaufenster mit Canonical `/shop`.
