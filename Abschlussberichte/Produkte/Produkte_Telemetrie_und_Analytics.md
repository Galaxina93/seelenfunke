# Abschlussbericht: Produkt-Telemetrie & Konfigurator-/Rechner-Analytics

**Datum:** 21. Oktober 2026  
**Status:** Erfolgreich implementiert, getestet und migriert  
**Projekt:** Seelenfunke Onlineshop  

---

## 1. Ausgangslage & Zielsetzung
Der Shopbetreiber benötigte ein professionelles, 100% datenschutzkonformes (DSGVO & § 25 TDDDG) Tracking- und Analytics-System für:
1. **Den Produktkonfigurator:**
   * Genaue Erfassung der Einstiegs-Entscheidung („Vorlage nutzen“ vs. „Selbst konfigurieren“).
   * Ermittlung der beliebtesten Design-Vorlagen (welche Vorlage wurde wie oft gewählt?).
   * Erfassung der Nutzung von Symbolen aus der Symbol-Bibliothek (welche Motive und wie oft?).
   * Überwachung von Kunden-Uploads (Dateiformate PNG, SVG, JPG, PDF und aufgetretene Upload-Fehler).
   * Buchungsquote und generierter Zusatzumsatz des Express-Zuschlags („Eiliges Geschenk“).
2. **Den Mengenrechner (`/calculator`):**
   * Aufrufzahlen, Einstiegsbereitschaft bei AGB-Akzeptanz.
   * 5-Stufen-Trichter mit exakter Drop-Off-Analyse (Wann und warum brechen Kunden ab?).
   * Erfassung von Angebotsvolumen (€) und Validierungsfehlern.
3. **Zentrale Administration:**
   * Integration in die bestehende Produkt-Analyse [`/admin/product-analytics`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductAnalytics.php) über ein übersichtliches 3-Tab-System.

---

## 2. Eingehaltene Naming- & Strukturkonventionen
Exakt nach Kundenvorgabe wurden alle neuen Datenbankstrukturen, Models und Services nach der bestehenden Ordner- und Produktlogik mit dem Präfix `product` bzw. `Product` benannt:
- **Migration:** [`database/migrations/2026_10_21_000000_create_product_analytics_events_table.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/database/migrations/2026_10_21_000000_create_product_analytics_events_table.php)
- **Tabelle:** `product_analytics_events`
- **Model:** [`app/Models/Product/ProductAnalyticsEvent.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Product/ProductAnalyticsEvent.php)
- **Service:** [`app/Services/ProductTrackingService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/ProductTrackingService.php)
- **Testsuite:** [`tests/Feature/Livewire/Shop/Product/ProductAnalyticsTrackingTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Livewire/Shop/Product/ProductAnalyticsTrackingTest.php)

---

## 3. Umgesetzte Komponenten & Code-Änderungen

### 3.1 Datenbank & Migration (`product_analytics_events`)
* Migration sowohl auf der Entwicklungs- als auch auf der Test-Datenbank (`seelenfunke_testing`) ausgeführt.
* Indizierte Spalten für performante Aggregationen:
  * `session_hash`: SHA-256 Hash aus Session-ID und App-Key (vollkommen anonym, keine IP-Speicherung).
  * `source`: `configurator` oder `calculator`.
  * `event_name`: Spezifisches Ereignis (z.B. `mode_chosen`, `template_selected`, `symbol_added`, `file_uploaded`, `express_toggled`, `configurator_saved`, `calc_view`, `calc_started`, `calc_quote_submitted`).
  * `step`: Funnel-Stufe (0, 1, 2, 3, 4).
  * `product_id`, `template_id`: UUID-Verknüpfungen zu Produkten und Vorlagen.
  * `item_identifier`: Motiv-Dateiname, Vorlagentitel oder Dateiendung.
  * `value_cents`: Monetärer Wert (z.B. Express-Gebühr, Anfragewert).
  * `device_type`: Desktop, Tablet oder Mobile (zur Usability-Erkennung).

### 3.2 Service [`ProductTrackingService`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/ProductTrackingService.php)
* Schlanke, statische Tracking-Methoden für alle relevanten Konfigurator- und Rechner-Aktionen.
* Robuste Fehlerbehandlung (Exceptions werden abgefangen, damit das Kunden-Erlebnis niemals beeinträchtigt wird).
* User-Agent-Erkennung für Geräteklassen ohne Tracking-Cookies.

### 3.3 Event-Integration in Livewire-Komponenten
* **[`ProductShow.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductShow.php):**
  * `mount()`: Erfasst `configurator_view`.
  * `openTemplatesList()`: Erfasst `mode_chosen` (`template`).
  * `startCustomConfig()`: Erfasst `mode_chosen` (`custom`).
  * `selectTemplate($id)`: Erfasst `template_selected`.
  * `cancelConfig()`: Erfasst `configurator_abandoned`.
* **[`ProductConfigurator.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductConfigurator/ProductConfigurator.php) & [`HandlesConfiguratorLogic.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductConfigurator/Traits/HandlesConfiguratorLogic.php):**
  * `addStandardVector($filename)` & `toggleLogo()`: Erfasst `symbol_added` mit Dateiname und Kategorie.
  * `updated('is_express')`: Erfasst `express_toggled` mit Status und Betrag.
  * `updated('new_files')`: Erfasst `file_uploaded` mit Dateiformat und Dateigröße.
  * `_uploadErrored()`: Erfasst `file_upload_error`.
  * `save()`: Erfasst `configurator_saved` bei Warenkorb-Übernahme inkl. Metadaten.
* **[`ProductCalculator.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCalculator/ProductCalculator.php):**
  * `mount()`: Erfasst `calc_view` (Stufe 0).
  * `startCalculator()`: Erfasst `calc_started` (Stufe 1) bzw. `calc_validation_failed` bei abgewiesenen AGB.
  * `openConfig($productId)`: Erfasst `calc_product_opened` (Stufe 2).
  * `saveItemFromConfigurator()`: Erfasst `calc_item_saved`.
  * `cancelConfig()`: Erfasst `calc_item_canceled`.
  * `goNext()`: Erfasst `calc_form_reached` (Stufe 3).
  * `submit()`: Erfasst `calc_quote_submitted` (Stufe 4) mit Angebotssumme und Express-Status.

### 3.4 API-Beacon in [`routes/api.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api.php)
* POST `/api/product-telemetry/beacon` für unkomplizierten Empfang von Browser-Schließ-Events (`pagehide`) via `navigator.sendBeacon`.

### 3.5 Admin-Dashboard [`ProductAnalytics.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductAnalytics.php)
* Erweitert um das 3-Tab-System:
  1. **Produktkonfigurator:** 4 KPI-Karten, Balken-Verhältnis Vorlage vs. Selbst, Upload-Formate (PNG, SVG, JPG, PDF, WEBP), Rangliste der Top-10 Vorlagen mit Bildvorschau, Grid der beliebtesten Symbole mit SVG-Icon-Vorschau und Express-Analyse.
  2. **Mengenrechner (/calculator):** Besucherzahlen, Lead-Quote, 5-Stufen-Trichterdiagramm mit prozentualem Absprung (Drop-Off) je Schritt, Analyse von Hürden/Validierungsfehlern und automatische Optimierungsempfehlungen.
  3. **Wirtschaftlichkeit & LUCID:** Nahtlose Beibehaltung der bestehenden Margenberechnung, Verpackungsmengen (LUCID) und Schadens-/Qualitätsmetriken.

---

## 4. Qualitätssicherung & Testergebnisse
* **Neue Testsuite [`ProductAnalyticsTrackingTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Livewire/Shop/Product/ProductAnalyticsTrackingTest.php):**
  * Alle 5 Tests erfolgreich (21 Assertions).
* **Gesamte Product-Feature-Suite:**
  * 69 Tests erfolgreich bestanden (317 Assertions, Dauer: 23,76 s).
* **View Cache:** Erneuert via `php artisan view:clear`.
