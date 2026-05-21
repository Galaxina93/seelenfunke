# Marketing - Gutscheine

Dieses Dokument beschreibt die technische Struktur und Funktionsweise des Gutschein- und Rabattsystems im Laravel-Projekt. Es wird zwischen automatischen Aktions-Gutscheinen und manuell erstellten Rabattcodes unterschieden. Zudem wird die Nutzung im Bestellprozess und die visuelle Auswertung im Dashboard erläutert.

## Zielsetzung
Das Gutscheinsystem dient der Conversion-Optimierung und Kundenbindung. Es bietet flexible Rabattierungsmodelle (Prozentual, Fester Wert, Versandkostenfrei) mit konfigurierbaren Einschränkungen wie Mindestbestellwerten, Gültigkeitszeiträumen und Nutzungsbeschränkungen.

---

## Beteiligte Komponenten & Modelle

### Backend-Livewire-Controller
* [MarketingVoucher](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingVoucher.php)
  * Verwaltet das Anlegen, Bearbeiten und Löschen von manuellen Gutscheinen (`mode` = `'manual'`).
  * Listet automatische Gutscheine (`mode` = `'auto'`) auf.
  * Berechnet Daten für das Gutschein-Performance-Diagramm.

### Hilfskomponenten
* [MarketingVoucherSlider](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingVoucherSlider.php)
  * Zeigt aktive Werbeaktionen im Frontend-Header/Slider an.

### Modelle
* [MarketingVoucher](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Marketing/MarketingVoucher.php)
  * Repräsentiert die Rabatteinstellungen in der Datenbank.

---

## Technische Struktur & Datenbankfelder

Im Modell [MarketingVoucher](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Marketing/MarketingVoucher.php) sind folgende Attribute definiert:
* `code`: Der eindeutige, großgeschriebene Gutscheincode (z. B. `SUMMER20`).
* `title`: Interne oder externe Beschreibung.
* `type`:
  * `fixed`: Fester Rabattbetrag in Cent (z. B. `1000` für 10,00 €).
  * `percent`: Prozentualer Abzug vom Gesamtwert.
  * `shipping`: Kostenloser Versand (Versandkostenbefreiung).
* `value`: Der numerische Wert (Euro-Cent oder Prozentpunkte).
* `min_order_value`: Der Mindestwarenwert in Cent, ab dem der Code anwendbar ist.
* `usage_limit`: Maximale Anzahl an Gesamtanwendungen (z. B. "Nur für die ersten 100 Kunden").
* `valid_from` & `valid_until`: Gültigkeitszeitraum.
* `mode`: `'auto'` (systemgesteuert für Feiertage/Events) oder `'manual'` (individuell erstellt).
* `is_active`: Status-Flag zum Pausieren.

---

## Technischer Ablauf & Validierung

### 1. Gutschein-Erstellung (Backend)
* **Code-Generierung**: Bei manueller Erstellung schlägt das System einen zufälligen Code vor: `strtoupper(Str::random(8))`.
* **Cent-Umrechnung**: Beträge werden im UI als Dezimalzahlen eingegeben (z. B. `9.99` €) und in der Datenbank als Ganzzahl in Cent (`999` Cent) persistiert, um Rundungsfehler zu vermeiden.

### 2. Validierung im Warenkorb (Checkout-Logik)
Bei der Eingabe eines Gutscheincodes im Checkout werden folgende Prüfungen durchgeführt:
1. Existiert der Code in `marketing_vouchers`?
2. Ist `is_active` auf `true`?
3. Befindet sich das aktuelle Datum zwischen `valid_from` und `valid_until`?
4. Ist der Warenkorb-Wert $\ge$ `min_order_value`?
5. Falls ein `usage_limit` existiert: Ist die Anzahl der erfolgreichen Bestellungen mit diesem Gutschein in `order_orders` kleiner als das Limit?

### 3. Gutschein-Performance (Chart-Aggregation)
Die Methode `getChartData()` analysiert die Performance der Top 10 Gutscheincodes über die letzten 12 Monate:
* Auslesen aller Datensätze der Tabelle `order_orders` mit ausgefülltem `coupon_code` im Intervall.
* Gruppieren der Bestellungen nach Kalendermonaten (`Y-m`).
* Rückgabe eines Datasets für Chart.js zur Visualisierung, welche Kampagne die meisten Konvertierungen erzielt hat.
