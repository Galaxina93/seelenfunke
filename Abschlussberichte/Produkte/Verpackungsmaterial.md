# System-Dokumentation: Produkte / Verpackungsmaterial (Kartonagen & Umverpackung)

Das Modul Verpackungsmaterial dient der Erfassung und Konfiguration aller verwendeten Versandverpackungen und Füllmaterialien für physische Artikel. Es dient als Grundlage für die gesetzliche Verpackungsmengen-Meldung (LUCID) und zur Berechnung von Versandgewichten.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Lückenlose Dokumentation der in den Verkehr gebrachten Verpackungsmaterialien je Produkt zur Erfüllung gesetzlicher Umweltschutzauflagen (Verpackungsgesetz - VerpackG).
- **Zusammenführung:** Zuweisung verschiedener Verpackungskomponenten (z. B. Karton, Luftpolsterfolie, Klebeband) zu einem physischen Hauptprodukt.
- **Vermeidung von Datenredundanz:** Automatisches Aufsummieren von Gewichten, falls derselbe Materialtyp mehrfach hinzugefügt wird.

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`ProductPackagingConfigurator`](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductPackagingConfigurator.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Produkte`)

### 2.2 Datenbank-Modell
- **`App\Models\Product\ProductPackaging`:**
  Repräsentiert ein verpackungsrelevantes Material für ein bestimmtes Produkt.
  - `product_id`: Zugeordnetes Produkt.
  - `material_type`: Klassifizierung des Materials. Die zulässigen Typen werden über die Methode `getMaterialTypes()` bereitgestellt:
    - `paper` (Papier/Pappe/Karton)
    - `plastic` (Kunststoffe/Folien)
    - `glass` (Glas)
    - `wood` (Holz)
    - `tin` (Blech)
    - `alu` (Aluminium)
    - `composite` (Verbundverpackungen)
    - `other` (Sonstige)
  - `weight_grams`: Gewicht des Materials in Gramm.

---

## 3. Kernfunktionen & Datenfluss

### 3.1 Verpackungskonfiguration
- Der Mitarbeiter wählt ein physisches Produkt aus.
- **Hinzufügen (`addMaterial`):**
  - Es wird ein Materialtyp ausgewählt und das Gewicht in Gramm angegeben.
  - Falls dieses Material für das Produkt bereits hinterlegt ist, wird kein neuer Datensatz erzeugt, sondern das neue Gewicht addiert (`$existing->increment(...)`).
- **Bearbeiten & Löschen:**
  - Über `startEdit()` kann das Gewicht bestehender Materialkomponenten korrigiert werden.
  - Über `deleteMaterial()` werden einzelne Materialkomponenten aus dem Produkt gelöscht.

### 3.2 Schnittstelle zu Finanzen und Logistik
- Die erfassten Verpackungsgewichte fließen direkt in die Berechnungen der Produktanalyse (`ProductAnalytics::getLucidData()`) ein.
- Dort wird anhand der Verkaufsstatistik des aktuellen Kalenderjahres das Gesamtgewicht der lizenzierten Verpackungsabfälle berechnet und für die Meldung bei der Zentralen Stelle Verpackungsregister (ZSVR) aufbereitet.
