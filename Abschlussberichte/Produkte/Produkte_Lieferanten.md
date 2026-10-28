# System-Dokumentation: Produkte / Lieferanten (Stammdaten & Logistikkonditionen)

Das Lieferanten-Modul verwaltet alle Stammdaten der Großhändler und Hersteller. Es erfasst logistische Lieferzeiten (Lead Times) für verschiedene Transportwege und Zahlungskonditionen, welche direkt in die automatische Bestandsreichweitenprognose des Shops einfließen.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Zentrale Verwaltung aller Bezugsquellen physischer Rohmaterialien und Produkte zur Sicherung der Lieferkette.
- **Logistik-Optimierung:** Hinterlegung differenzierter Lieferzeiten je nach Versandmethode (Land, Luft, See, Schiene) zur präzisen Berechnung kritischer Bestellzeitpunkte.
- **Konditions-Monitoring:** Speicherung von Zahlungszielen, Mindestbestellwerten und Frachtkosten zur Einkaufsplanung.

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`ProductSuppliers`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductSuppliers.php)
- **View:** [`product-suppliers.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/product-suppliers.blade.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Produkte`)

### 2.2 Datenbank-Modell
- **`App\Models\Product\ProductSupplier`:**
  Der Hauptdatensatz eines Lieferanten.
  - `name` & `company_name`: Interne Bezeichnung und offizieller Firmenname.
  - `street`, `house_number`, `zip`, `city`, `country`, `country_code`: Vollständige Anschrift.
  - `contact_person`, `email`, `phone`, `website`: Ansprechpartner und Kommunikationskanäle.
  - `tax_id`, `vat_id`, `bank_name`, `iban`, `bic`: Steuer- und Bankdaten.
  - `customer_number`: Eigene Kundennummer beim Lieferanten.
  - `minimum_order_value`: Mindestbestellwert in Cents.
  - `shipping_costs`: Standard-Frachtkosten in Cents.
  - `lead_time_land_days`, `lead_time_air_days`, `lead_time_sea_days`, `lead_time_train_days`: Lieferzeiten für Land-, Luft-, See- und Bahntransporte.
  - `shipping_method`: Aktuell gewählte Transportmethode (`land`, `air`, `sea`, `train`).
  - `dynamic_links`: JSON-Array zur Speicherung externer Händlerportale oder Produktkataloge.

---

## 3. Kernfunktionen & Datenfluss

### 3.1 Zuweisung und Bestandsreichweite
- Jedes physische Produkt (`Product`) ist über den Fremdschlüssel `product_supplier_id` mit einem `ProductSupplier` verknüpft (`$product->belongsTo(ProductSupplier::class, 'product_supplier_id')`).
- In den Produktanalysen (`ProductAnalytics`) wird die Lieferzeit des zugewiesenen Lieferanten basierend auf dessen gewählter Versandart (`shipping_method`) geladen (z. B. `lead_time_sea_days` bei Überseelieferungen).
- Im Chart „Lieferanten Klumpenrisiko“ wird die Anzahl aktiver physischer Produkte pro Lieferant über `product_supplier_id` gruppiert dargestellt.
- Ist die geschätzte Bestandsreichweite des Produkts kleiner als diese Lieferzeit, wechselt der Produktstatus automatisch auf `critical`, um dem Einkauf eine sofortige Nachbestellung zu signalisieren.

### 3.2 Dynamic Links & Kompakte Enterprise-Tabellenansicht
- Das System erlaubt über die Eigenschaft `dynamic_links` das flexible Hinzufügen von Portallinks (z. B. Bestellplattformen, Rohlings-Kategorien, Reklamationsformulare), die in der Tabelle als kompakte Schnellzugriffs-Badges gerendert werden.
- **Kompakte Lieferanten-Tabelle:** Statt großflächiger Einzelkarten stellt [`product-suppliers.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/product-suppliers.blade.php) alle Großhändler in einer hochdichten B2B-Tabelle dar (deutsche Händler `DE` automatisch zuerst sortiert), inklusive Live-Suche und Länder-Schnellfilter (`Alle` / `🇩🇪 DE` / `🌍 Int.`).
- **Gelieferte Produkte im kompakten Scrollbereich:** Zugeordnete Produkte werden platzsparend mit `28×28 px` (`w-7 h-7`) Mini-Thumbnails, Stück-EK, Lagerbestand und 1-Klick-Nachbestelllink innerhalb eines vertikalen Scrollbereichs (`max-h-20 overflow-y-auto`) direkt in der Tabellenzeile geladen.
