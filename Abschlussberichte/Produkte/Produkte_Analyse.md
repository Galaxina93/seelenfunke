# System-Dokumentation: Produkte / Analyse

Das Produkt-Analyse-Modul ([`/admin/product-analytics`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductAnalytics.php)) dient der ganzheitlichen Auswertung des Sortiments: Es vereint detaillierte betriebswirtschaftliche Deckungsbeitragsrechnungen (Margen, Maschinenzeit, Material, Verpackung und LUCID) mit einem datenschutzkonformen First-Party-Telemetriesystem für den Produktkonfigurator und den Mengenrechner (`/calculator`).

---

## 1. Übersicht & Zielsetzung

1. **Konfigurator-Verhaltenstracking (DSGVO-konform / Cookieless):**
   * Messung der Entscheidungen: Wie oft wird „Vorlage nutzen“ vs. „Selbst konfigurieren“ gewählt?
   * Vorlagen-Hitliste: Welche Vorlagen werden am häufigsten als Gestaltungsgrundlage genutzt?
   * Symbol-Bibliothek: Beliebteste Gravur-Motive und Vektoren.
   * Upload-Quote: Nutzung eigener Bilder/Dateien (PNG, SVG, JPG, PDF) und Erfassung von Upload-Fehlern.
   * Express-Attach-Rate: Nutzung und Umsatzpotenzial der Option „Eiliges Geschenk (Express)“.
   * Conversion-Rate: Verhältnis von Konfigurator-Aufrufen zu tatsächlichen Warenkorb-Speicherungen.

2. **Mengenrechner (/calculator) Funnel- & Abbruch-Analyse:**
   * 5-Stufen-Trichter: Von Aufruf (Stufe 0) über Start/AGB (Stufe 1), Konfiguration (Stufe 2), Kontaktformular (Stufe 3) bis zur abgesendeten Preisanfrage (Stufe 4).
   * Drop-Off-Raten: Erkennung, an welchen Schritten Interessenten abspringen und warum (z.B. Hürde beim Disclaimer oder Formularfeldern).
   * Wirtschaftliche Leads: Erfassung von Quote-Volumen (€) und durchschnittlichem Anfragewert.

3. **Betriebswirtschaftliche Kennzahlen & LUCID:**
   * Deckungsbeitrag unter Einbeziehung von Einkauf, Laser-Maschinenzeit (Strom/Verschleiß), Verpackung, Versand und Marketing.
   * Bestandsreichweiten-Prognosen (Velocity & Reach Days) zur Vermeidung von Lieferengpässen.
   * Automatisierte Verpackungsmengen-Bilanzierung für das Verpackungsregister (LUCID).

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`App\Livewire\Shop\Product\ProductAnalytics`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductAnalytics.php)
- **Blade-View:** [`resources/views/livewire/shop/product/product-analytics.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/product-analytics.blade.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Produkte`)

### 2.2 Datenbank-Modell & Migration
- **Tabelle:** `product_analytics_events` (angelegt via [`2026_10_21_000000_create_product_analytics_events_table.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/database/migrations/2026_10_21_000000_create_product_analytics_events_table.php))
- **Model:** [`App\Models\Product\ProductAnalyticsEvent`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Product/ProductAnalyticsEvent.php)
  - Speichert anonymisierte Events mit `session_hash` (SHA-256), `source` (`configurator` / `calculator`), `event_name`, `step`, `product_id`, `template_id`, `item_identifier`, `value_cents`, `device_type`, `meta_data` und `created_at`.
  - Vollkommen frei von IP-Adressen und personenbezogenen Daten (Art. 6 Abs. 1 lit. f DSGVO, kein Cookie-Banner nach § 25 TDDDG nötig).

### 2.3 Service-Schicht
- **Klasse:** [`App\Services\ProductTrackingService`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/ProductTrackingService.php)
  - Zentrale Schnittstelle zum performanten Erfassen aller Telemetrie-Events.
  - Fail-safe Architektur (fängt Ausnahmen lautlos ab, sodass die Live-Nutzererfahrung niemals gestört wird).
- **API-Beacon:** POST `/api/product-telemetry/beacon` in [`routes/api.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/routes/api.php) für Page-Exit / Abandonment-Signale via `navigator.sendBeacon`.

---

## 3. UI-Struktur: Das 3-Tab-System

In [`product-analytics.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/product-analytics.blade.php) schaltet der Administrator nahtlos zwischen drei Auswertungsbereichen um:

1. **Tab 1: Produktkonfigurator (`activeTab = 'configurator'`)**
   - KPI-Karten für Conversion-Rate, Vorlagen-Quote, Express-Attach-Rate und Gestaltungselemente.
   - Visuelle Auswertung der Modus-Wahl (Vorlage vs. Selbst konfigurieren).
   - Dateiformat-Verteilung bei Uploads (PNG, SVG, JPG, PDF, WEBP) inkl. Fehlermonitoring.
   - Rangliste der Top-10 meistgewählten Design-Vorlagen mit Bildvorschau.
   - Beliebteste Gravur-Symbole aus der Symbol-Bibliothek mit direkter Icon-Vorschau.
   - Express-Wirtschaftlichkeit mit ausgewiesenem Zusatzumsatz.

2. **Tab 2: Mengenrechner (/calculator) (`activeTab = 'calculator'`)**
   - KPI-Karten für Besucher, Einstiegsquote, Preisanfragen (Leads) und Gesamtvolumen (€).
   - 5-Stufen-Trichterdiagramm mit prozentualer Drop-Off-Rate je Stufe.
   - Erkennung von Hürden (AGB-Disclaimer, Formular-Validierungsfehler, abgebrochene Zwischenschritte).
   - Automatische Optimierungsempfehlungen bei erhöhten Absprüngen.

3. **Tab 3: Wirtschaftlichkeit & LUCID (`activeTab = 'economics'`)**
   - Wahre Zahlen & Margen (Einkauf, Laserlaufzeit, Verpackung, Reingewinn, Deckungsbeitrag) – 1:1 originalgetreue Stage-Detailansicht für die Spalten **Kostenstruktur** und **Netto VK & Marge**.
   - **Kompakter Kostenstruktur-Dropdown:** Standardmäßig wird nur der EK als schlanker Button (`EK: X,XX € [+5] ▾`) angezeigt, um in den Tabellenzeilen Platz zu sparen. Per Klick klappt die detaillierte Kostenaufschlüsselung (Laser, Material, Versand, Marketing, Tara und Gesamtkosten) geschmeidig über Alpine (`x-collapse`) auf und zu.
   - Gesetzlicher LUCID-Verpackungsbericht nach Materialarten (PPK, Kunststoff, Glas etc.) mit PDF-Export.
   - Qualitäts- & Schadens-Tracker (Lieferschäden, Top 5 Problemprodukte, Kundenbewertungen, Lieferantenrisiko).

---

## 4. Testabdeckung
- Automatisiert getestet in [`tests/Feature/Livewire/Shop/Product/ProductAnalyticsTrackingTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Livewire/Shop/Product/ProductAnalyticsTrackingTest.php):
  - `test_product_tracking_service_writes_anonymous_event`
  - `test_product_show_tracks_view_mode_and_template_selection`
  - `test_product_configurator_tracks_symbol_and_express`
  - `test_product_calculator_tracks_funnel_progression`
  - `test_product_analytics_admin_component_renders_and_computes_stats`
