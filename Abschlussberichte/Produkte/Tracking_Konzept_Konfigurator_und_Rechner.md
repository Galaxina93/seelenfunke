# Konzept: Professionelles & DSGVO-konformes Event-Tracking für Konfigurator & Mengenrechner

**Status:** Konzeptionierung (Keine Code-Änderungen an Produktivsystemen)  
**Datum:** 21. Oktober 2026  
**Zielsysteme:**  
- Produktkonfigurator ([`ProductShow.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductShow.php) & [`ProductConfigurator.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductConfigurator/ProductConfigurator.php))  
- Mengenrechner ([`ProductCalculator.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCalculator/ProductCalculator.php) / `/calculator`)  
- Admin-Dashboard ([`ProductAnalytics.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductAnalytics.php) / `/admin/product-analytics`)

---

## 1. Rechtlicher Rahmen: 100% DSGVO- & TDDDG-Konformität (Ohne Cookie-Banner-Zwang)

### 1.1 Gesetzliche Ausgangslage in Deutschland
* **§ 25 Abs. 1 TDDDG (ehemals TTDSG):** Speichern von Informationen auf dem Endgerät des Endnutzers oder Zugriff auf Informationen, die bereits im Endgerät gespeichert sind, erfordern eine vorherige informierte Einwilligung (Opt-in via Cookie-Banner).
* **§ 25 Abs. 2 Nr. 2 TDDDG (Ausnahme):** Eine Einwilligung ist **nicht** erforderlich, wenn der Speicher- oder Zugriffsvorgang unbedingt erforderlich ist, um einen vom Nutzer ausdrücklich gewünschten Dienst zur Verfügung zu stellen.
* **Art. 6 Abs. 1 lit. f DSGVO (Berechtigtes Interesse):** Verarbeitung nicht-personenbezogener bzw. wirksam anonymisierter Telemetriedaten zur Sicherstellung der Funktionalität, Usability-Optimierung und wirtschaftlichen Angebotsgestaltung.

### 1.2 Warum Drittanbieter-Tools (Google Analytics, Hotjar, Mixpanel) hier scheitern
1. **Hohe Abbruch-/Verlustrate:** 30 % bis 60 % aller Besucher lehnen Drittanbieter-Tracking im Cookie-Banner ab oder nutzen Adblocker. Zahlen zu Vorlagen und Symbolen wären drastisch verzerrt.
2. **Datentransfer-Risiko:** Datentransfer in US-Drittländer erfordert komplexe Auftragsverarbeitungsverträge (AVV) und Opt-in.
3. **Performance-Verlust:** Externe Tracker laden schwere JavaScript-Bundles, die den Konfigurator verlangsamen.

### 1.3 Der empfohlene Weg: First-Party Cookieless Telemetrie
* **Keine Tracking-Cookies / kein LocalStorage-Tracking:** Es werden **keine** persistenten Werbe- oder Tracking-Cookies gesetzt.
* **Pseudonyme Session-Klammer:** Als Klammer für zusammengehörige Interaktionen eines Besuchs dient entweder:
  * Die technisch notwendige Laravel-Session-ID (bereits für den Warenkorb vorhanden), ODER
  * Eine flüchtige, zufällige Session-UUID, die nur für den aktuellen Tab-Besuch im Arbeitsspeicher existiert.
* **Keine Speicherung von IP-Adressen:** IP-Adressen werden **weder in der Datenbank noch in Logs gespeichert** (oder sofort über einen täglichen Einweg-Salt `hash('sha256', $ip . date('Y-m-d') . config('app.key'))` anonymisiert, um Mehrfachklicks binnen 24 Stunden ohne Personenbezug zu aggregieren).
* **Keine PII (Personally Identifiable Information):** Keine Namen, E-Mail-Adressen oder Adressdaten in den Tracking-Tabellen.
* **Ergebnis:** Das Tracking kann **vollkommen rechtssicher unter Art. 6 Abs. 1 lit. f DSGVO betrieben werden, ohne dass der Kunde im Cookie-Banner zustimmen muss**, da keine Zugriffe auf das Endgerät nach § 25 TDDDG stattfinden und keine personenbezogenen Profile gebildet werden. 100% aller Interaktionen werden erfasst.
* **Speicherbegrenzung (Data Retention):** Ein automatischer Bereinigungs-Job (Cronjob) löscht feingranulare Roh-Events nach 90 Tagen; langfristig bleiben nur tageweise aggregierte Summen erhalten.

---

## 2. Erfassungspunkte im Produktkonfigurator

Im Code des Konfigurators gibt es exakte Stellen, an denen Events ohne Latenz erfasst werden können:

| Anforderung des Nutzers | Code-Einsprungstelle | Erfasstes Event & Parameter |
| :--- | :--- | :--- |
| **„Vorlage nutzen“ vs. „Selbst konfigurieren“** | `ProductShow::openTemplatesList()` vs. `ProductShow::startCustomConfig()` | Event: `configurator_mode_chosen`<br>Data: `mode = 'template' \| 'custom'`, `product_id` |
| **Welche Vorlage wie oft gewählt** | `ProductShow::selectTemplate($templateId)` | Event: `template_selected`<br>Data: `template_id`, `template_name`, `product_id` |
| **Wie oft & welche Symbole genutzt** | `HandlesConfiguratorLogic::addStandardVector($filename)` & `toggleLogo()` | Event: `symbol_added`<br>Data: `symbol_file`, `category`, `product_id`, `side = 'front' \| 'back'` |
| **Wie oft eigene Dateien hochgeladen** | `ProductConfigurator::updatedNewFiles()` | Event: `file_uploaded`<br>Data: `file_extension` (png, svg, jpg, pdf), `file_size_kb`, `product_id`<br>Event: `file_upload_error` bei Abbruch/Formatfehler |
| **„Eiliges Geschenk (Express)“ gewählt** | `ProductConfigurator::updatedIsExpress()` / `$propertyName === 'is_express'` | Event: `express_toggled`<br>Data: `is_express = true \| false`, `surcharge_cents`, `product_id` |
| **Konfiguration in den Warenkorb gelegt** | `ProductConfigurator::save()` | Event: `configurator_completed`<br>Data: `product_id`, `total_price`, `has_express`, `symbol_count`, `upload_count`, `duration_seconds` |
| **Abbruch vor Warenkorb** | `ProductShow::cancelConfig()` oder `beforeunload` Beacon | Event: `configurator_abandoned`<br>Data: `product_id`, `last_state`, `time_spent_seconds` |

---

## 3. Erfassungspunkte im Mengenrechner (`/calculator`)

Der Mengenrechner ([`ProductCalculator.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCalculator/ProductCalculator.php)) arbeitet mit einem 5-stufigen State (`$step = 0 .. 4`). Dies ermöglicht eine lückenlose Funnel- und Abbruch-Analyse:

### 3.1 Funnel-Stufen & Interaktionen
1. **Stufe 0 (Willkommen & AGB-Hinweis):**
   * Aufruf `/calculator` -> Event `calc_page_view`.
   * Klick auf `startCalculator()` -> Event `calc_started`.
   * *Abbruch hier bedeutet:* Kunde liest Disclaimer/AGB und bricht sofort ab (Abschreckung durch Hürde vor dem Einstieg).
2. **Stufe 1 (Produktauswahl & Mengen):**
   * Klick auf ein Produkt `openConfig($productId)` -> Event `calc_product_opened`.
   * Wahl Vorlage vs. Selbstbau im Rechner (`openTemplatesList` vs `startCustomConfig`).
   * Express-Checkbox im Rechner `updatedIsExpress()`.
   * Klick auf `goNext()` -> Event `calc_step_form_reached`.
   * *Abbruch hier bedeutet:* Kunde findet kein passendes Produkt, Staffelrabatte reichen nicht aus oder Gesamtpreis im Warenkorb wirkt zu hoch.
3. **Stufe 2 (Konfigurator-Zwischenschritt):**
   * Artikel erfolgreich konfiguriert & übernommen: `saveItemFromConfigurator()` -> Event `calc_item_saved`.
   * Konfiguration abgebrochen: `cancelConfig()` -> Event `calc_item_canceled`.
   * *Abbruch hier bedeutet:* Konfiguration zu umständlich für Großbestellungen.
4. **Stufe 3 (Kontaktformular vor Absenden):**
   * Formular geöffnet (`step = 3`).
   * *Abbruch hier bedeutet:* Hürde bei der Dateneingabe (Kunde möchte keine Telefonnummer angeben, Unklarheit ob Anfrage unverbindlich ist).
5. **Stufe 4 (Erfolgreiche Preisanfrage):**
   * `submit()` erfolgreich ausgeführt -> Event `calc_quote_submitted` mit `quote_number`, `gross_total`, `is_express`, `item_count`.

### 3.2 Wann und warum wird abgebrochen? (Drop-Off-Analyse)
Um festzustellen, **wann** und **warum** abgebrochen wird, erfasst das System:
* **Drop-Off-Step:** Welcher Step war die letzte dokumentierte Interaktion einer Session?
* **Verweildauer pro Step:** Verbringt der Kunde ungewöhnlich viel Zeit in Step 2 (Editor überfordert) oder in Step 3 (Formularfeld-Probleme)?
* **Validierungsfehler:** Wenn in `submit()` oder `startCalculator()` Validierungsfehler auftreten (z.B. AGB nicht angehakt, Pflichtfelder fehlen), wird das Event `calc_validation_failed` protokolliert.
* **Geräteklasse (Mobile vs. Desktop):** Aufschlüsselung, ob Smartphone-Nutzer im Editor oder Formular signifikant öfter abspringen als Desktop-Nutzer.

---

## 4. Platzierung der Auswertungsansicht: Wo macht es am meisten Sinn?

### Analyse der Frage: *Hier ? `/admin/product-analytics`*

Aktuell beherbergt [`/admin/product-analytics`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductAnalytics.php) drei Blöcke:
1. **Wahre Zahlen & Margen:** Deckungsbeitrag, Einkaufspreise, Laserlaufzeit, Logistikreichweite.
2. **LUCID-Bericht:** Verpackungsgewichte nach Materialart.
3. **Schadens- & Qualitäts-Tracker:** Lieferschäden, Reviews, Lieferantenrisiko.

### Bewertung:
* **Empfehlung:** **JA, `/admin/product-analytics` ist die mit Abstand beste und logischste Heimat!**
* **Begründung:** 
  1. In der Backend-Navigation ([`BackendNavigationService.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Navigation/BackendNavigationService.php)) liegt unter der Kategorie `Produkte` der Punkt `Analyse`. Ein Shopbetreiber erwartet genau dort alle Auswertungen rund um seine Artikel und deren Konfiguration.
  2. Ein neuer Hauptmenüpunkt würde die Sidebar unnötig überladen.
  3. Die Verknüpfung beider Welten ist betriebswirtschaftlich Gold wert: Man kann sofort sehen, ob margenstarke Produkte auch die beliebtesten im Konfigurator sind.

### Das ideale UI-Konzept: Horizontale Reiter (Sub-Tabs) in `/admin/product-analytics`:
Wir gliedern `/admin/product-analytics` in drei klar abgegrenzte Tabs:
1. **Tab 1: Wirtschaftlichkeit & LUCID (Bestehend)**
   * Margen, Stückkosten, Laserzeit, Reichweite, Verpackungsregister.
2. **Tab 2: Konfigurator-Verhalten (Neu)**
   * Vorlage vs. Selbstbau (Donut-Chart).
   * Vorlagen-Hitliste (Top 10 Vorlagen mit Vorschau-Bild und Nutzungszahl).
   * Symbol-Bibliothek (Top genutzte Gravur-Icons mit Icon-Vorschau).
   * Datei-Uploads (Upload-Quote, Dateiformate SVG/PNG/PDF, Fehlerquote).
   * Express-Attach-Rate (Wie oft wird das „Eilige Geschenk“ gebucht? Zusatzumsatz in Euro).
   * Konfigurator-Funnel (Start -> Modus -> Gestaltung -> Warenkorb).
3. **Tab 3: Mengenrechner & B2B-Funnel (Neu)**
   * Aufrufe vs. gestartete Anfragen (Step 0 -> Step 1).
   * 5-Stufen-Trichter-Diagramm mit exakter Angabe der Drop-Off-Rate je Stufe.
   * Analyse der Abbruchgründe (Fehlgeschlagene Validierung, Bounce in Step 0, Bounce vor Formular).
   * Beliebteste Produkte im Mengenrechner.

---

## 5. Technische Architektur & Datenmodell

### 5.1 Datenbanktabelle: `analytics_configurator_events`
```sql
CREATE TABLE `analytics_configurator_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_hash` VARCHAR(64) NOT NULL, -- SHA256 von Session-ID oder Daily Salt (vollkommen anonym)
    `source` ENUM('configurator', 'calculator') NOT NULL,
    `event_name` VARCHAR(50) NOT NULL,
    `step` TINYINT UNSIGNED NULL,
    `product_id` BIGINT UNSIGNED NULL,
    `template_id` BIGINT UNSIGNED NULL,
    `item_identifier` VARCHAR(150) NULL, -- Dateiname des Symbols, Upload-Typ, Modus-Name
    `value_cents` INT UNSIGNED NULL, -- Optional: Preis, Express-Aufpreis, Anfragewert
    `device_type` ENUM('desktop', 'tablet', 'mobile') NOT NULL DEFAULT 'desktop',
    `meta_data` JSON NULL, -- Zusätzliche Metriken (Verweildauer, Fehlerdetails, etc.)
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_src_event_created` (`source`, `event_name`, `created_at`),
    INDEX `idx_product_event` (`product_id`, `event_name`),
    INDEX `idx_session` (`session_hash`, `created_at`)
);
```

### 5.2 Service-Schicht: `ConfiguratorTelemetryService`
* Ein schlanker Singleton-Service:
  `ConfiguratorTelemetryService::track(string $source, string $event, array $payload = [])`
* **Performance-Garantie:**
  * Das Schreiben des Events erfolgt entweder in einer schlanken Single-Insert-Query (< 1 ms) oder gepuffert über Laravels Queue/Job-System (`defer()`), sodass für den Endkunden **keinerlei spürbare Ladezeitverzögerung** entsteht.

### 5.3 Erfassung von Seitenabbrüchen (Exit / Bounce)
* Für reguläre Aktionen (Klicks, Uploads, Auswahlen) erfolgt das Logging direkt in PHP/Livewire beim Ausführen der Methode.
* Für das Schließen des Browser-Fensters oder Verlassen der Seite wird ein leichtgewichtiges JS-Event via `window.addEventListener('pagehide', ...)` mit `navigator.sendBeacon('/api/telemetry/beacon', ...)` verwendet. Dies garantiert, dass auch abrupte Seitenabbrüche ohne Cookies erfasst werden.

---

## 6. Zusammenfassung & Nächste Schritte

| Kriterium | Bewertung / Konzept |
| :--- | :--- |
| **Rechtssicherheit** | 100 % konform mit DSGVO & § 25 TDDDG; rein First-Party, keine Cookies, keine IP-Speicherung, kein Consent-Banner nötig. |
| **Datenqualität** | 100 % Erfassung (keine Verzerrung durch Adblocker oder Banner-Ablehnung). |
| **Erfasste Metriken** | Vorlage vs. Selbst, Vorlagen-Ranking, Symbol-Nutzung, Datei-Uploads, Express-Quote, Rechner-Trichter & Abbrüche. |
| **Dashboard-Ort** | `/admin/product-analytics` mit Drei-Tab-System (Wirtschaftlichkeit / Konfigurator / Rechner). |
| **Performance** | Asynchrones / mikro-optimiertes Logging ohne spürbare Latenz im Frontend. |
