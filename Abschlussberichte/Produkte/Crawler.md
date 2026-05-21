# System-Dokumentation: Produkte / Crawler (Nischen-Scanner & Wettbewerbs-Preismonitoring)

Das Crawler-Modul dient der Erkennung von Marktnischen auf E-Commerce-Plattformen (z. B. Etsy, Amazon) und dem Preismonitoring von Wettbewerbern für personalisierte Geschenkartikel. Es ermöglicht automatische Suchläufe und die Speicherung historischer Snapshots zur Trendanalyse.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Aufspüren lukrativer Produktideen im E-Commerce und Marktbeobachtung durch automatisierte Suchen und Scoring-Algorithmen.
- **Nischen-Scoring:** Bewertung von Produkten nach Relevanz, Verkaufszahlen, Konkurrenzdichte und Beliebtheit zur Ermittlung eines "Niche Scores".
- **Historische Vergleiche:** Festhalten von Scan-Snapshots, um die Preisentwicklung und Sortimentsveränderungen von Mitbewerbern über die Zeit zu dokumentieren.

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`ProductCrawler`](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCrawler.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Produkte`)

### 2.2 Relevante Datenbank-Modelle
- **`App\Models\Product\ProductNicheItem`:**
  Speichert die aktuellen Suchergebnisse des Crawlers für die Live-Ansicht (wird bei Bereinigungen zurückgesetzt).
  - `title`: Produktname des Mitbewerbers.
  - `platform`: Verkaufsplattform (z. B. "Etsy", "Amazon").
  - `price`: Mitbewerberpreis.
  - `niche_score`: Berechneter Score (Relevanz- und Potenzialwert).
- **`App\Models\Product\ProductNicheCrawlerRun`:**
  Persistiert historische Snapshots abgeschlossener Suchläufe.
  - `admin_id`: Ausführender Mitarbeiter.
  - `name`: Bezeichnung des Snapshots (z. B. "Suche: Holzschild (Etsy)").
  - `keyword` & `platform`: Genutzte Suchbegriffe und Kanäle.
  - `products_data`: JSON-Daten aller gecrawlten Nischen-Items.

---

## 3. Kernfunktionen & Datenfluss

### 3.1 Asynchroner Crawler-Start
1. Der Mitarbeiter wählt ein Suchwort (z. B. "personalisiertes geschenk") und die Zielplattformen aus.
2. Beim Auslösen (`dispatchCrawler()`) wird ein eindeutiger Job-Identifikator erzeugt und im Cache registriert.
3. Der Job `RunProductNicheCrawlerJob` wird in die Laravel-Warteschlange (Queue) eingereiht.
4. **Live-Fortschritts-Tracking:** Die Node- oder PHP-Worker schreiben den aktuellen Status und Fortschritt (in %) in den Cache (`crawler_job_{jobId}`), welcher im Livewire-Dashboard per AJAX-Polling oder WebSocket-Push visualisiert wird.
5. Ein laufender Job kann jederzeit über `cancelCrawler()` abgebrochen und aus dem Cache entfernt werden.

### 3.2 Snapshot-Verwaltung & Historischer Modus
- **Speichern (`saveCurrentRun`):** Die aktuellen Live-Daten aus `ProductNicheItem` werden kopiert und als JSON-Array in einem neuen Eintrag der Tabelle `product_niche_crawler_runs` archiviert.
- **Laden (`loadHistoricalRun`):** Schaltet die Komponente in den historischen Modus um. Die Datenbankabfragen für die Live-Tabelle werden umgangen, und die JSON-Produktdaten aus dem geladenen Snapshot werden zur tabellarischen Ansicht und Chart-Generierung aufbereitet.
- **Export:** Export des Suchlaufs (Live oder Historisch) als PDF-Report über die Methode `exportTop5Pdf`.
