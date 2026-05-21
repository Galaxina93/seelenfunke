# System-Dokumentation: Produkte / Analyse

Das Produkt-Analyse-Modul dient der wirtschaftlichen Auswertung des Sortiments. Es berechnet detaillierte Gewinnmargen unter Berücksichtigung aller Kosten (Wareneinkauf, Maschinenlaufzeit, Strom, Verpackung, Versand und Marketing) und führt Prognosen zur Reichweite des Lagerbestands aus. Darüber hinaus liefert es Berichtsdaten für gesetzliche Verpackungsmeldungen.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Erfassung und Optimierung der Profitabilität aller physischen Produkte sowie Risikominimierung durch automatisierte Bestandsreichweiten-Prognosen.
- **Vollständige Kostenerfassung:** Einbeziehung von Einkaufspreisen, Strom- und Verschleißkosten für den Laserzuschnitt (Maschinenlaufzeit), Versandkosten, Verpackungsmaterialien und Marketing-Pauschalen.
- **LUCID-Reporting (VerpackG):** Automatisierte Berechnung des jährlichen Verpackungsgewichts aufgeteilt nach Materialarten.

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`ProductAnalytics`](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductAnalytics.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Produkte`)

### 2.2 Relevante Datenbank-Modelle
- **`App\Models\Product\Product`:** Liefert Produktdaten wie Lagerbestand, Einkaufspreis, Maschineneinstellungen, Laserlaufzeit und Gewicht.
- **`App\Models\Order\OrderOrderItem`:** Liefert Verkaufszahlen zur Berechnung der Absatzgeschwindigkeit.
- **`App\Models\Product\ProductLoss`:** Liefert Daten zu Abschreibungen und Ausschuss.
- **`App\Models\Product\ProductReview`:** Liefert Rezensionen zur Qualitätsbewertung.
- **`App\Models\Product\ProductSupplier`:** Liefert Lieferzeiten zur Reichweitenprognose.

---

## 3. Kernfunktionen & Datenfluss

### 3.1 Margen- und Kostenberechnung (`getCombinedAnalyticsData`)
Für jedes aktive, physische Produkt wird eine vollständige Deckungsbeitragsrechnung durchgeführt:
- **Laserkosten:** Berechnet aus der Laser-Laufzeit in Minuten (`laser_runtime_minutes`) multipliziert mit dem Strom- und Verschleißfaktor (`electricity_wear_factor`).
- **Marketingkosten:** Prozentuale Marketingpauschale (Standard: 15% des Netto-Verkaufspreises).
- **Versandkosten:** Ermittlung aus dem Produkt oder Fallback auf die globale Shopesinstellung.
- **Gesamtkosten & Nettomarge:**
  $$\text{Gesamtkosten} = \text{Einkaufspreis} + \text{Laserkosten} + \text{Verpackung} + \text{Versand} + \text{Marketing}$$
  $$\text{Nettomarge} = \text{Nettoverkaufspreis} - \text{Gesamtkosten}$$

### 3.2 Absatz- und Reichweitenprognose
- **Absatzgeschwindigkeit (Velocity):** Ermittlung der Verkaufszahlen der letzten 30 Tage dividiert durch 30, um den täglichen Durchschnittsabsatz zu berechnen.
- **Reichweite (Reach Days):** Aktueller Lagerbestand dividiert durch die Absatzgeschwindigkeit.
- **Statusbewertung:**
  - `out_of_stock`: Bestand ist 0.
  - `critical`: Reichweite ist kleiner oder gleich der Lieferzeit des Lieferanten (`lead_time`).
  - `warning`: Reichweite liegt weniger als 7 Tage über der Lieferzeit.
  - `ok`: Ausreichender Lagerbestand vorhanden.

### 3.3 Verpackungsmengen-Bilanzierung (`getLucidData`)
- Zur Einhaltung des Verpackungsgesetzes summiert die Methode `getLucidData()` für alle Verkäufe des aktuellen Kalenderjahres das verpackte Gewicht nach Materialtypen (`paper`, `plastic`, `glass`, `wood`, `tin`, `alu`, `composite`, `other`).
- Die Daten können als PDF-Bericht heruntergeladen werden.
