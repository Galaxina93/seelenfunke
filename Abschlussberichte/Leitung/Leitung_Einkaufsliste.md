# System-Dokumentation: Leitung / Einkaufsliste

Die Einkaufsliste dient der operativen Beschaffungsverwaltung von Verbrauchs- und Betriebsmaterialien im Unternehmen. Sie ermöglicht es, Bedarfe zu erfassen, diese nach Kategorien zu ordnen und einen automatischen Bestandsabgleich bzw. eine Nachbestell-Historie zu verwalten.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Zentrale Erfassung und Strukturierung von benötigten Gütern (Büromaterial, Verpackungen, sonstige Bedarfe).
- **Kategorisierung:** Gruppierung von Einkaufsartikeln nach Abteilungen oder Warengruppen für eine effiziente Beschaffung.
- **Historisierung:** Verfolgung des Einkaufsverlaufs, um festzustellen, welche Artikel am längsten nicht mehr beschafft wurden ("Oldest Items") und wie häufig diese benötigt werden.

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`ManagementShoppingList`](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementShoppingList.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Leitung`)

### 2.2 Datenbank-Modelle
- **`App\Models\Management\ManagementShoppingCategory`:**
  Repräsentiert die Warengruppen oder Einkaufsabteilungen (z. B. "Büro", "Verpackung", "Küche").
  - `name`: Name der Kategorie.
  - `icon`: Icon zur visuellen Kennzeichnung (z. B. `shopping-cart`, `sparkles`).
  - `sort_order`: Bestimmt die Sortierreihenfolge der Kategorien in der Ansicht.
  - `is_archived`: Boolean-Archivierungsstatus.
- **`App\Models\Management\ManagementShoppingItem`:**
  Repräsentiert den einzelnen Einkaufsartikel.
  - `category_id`: Fremdschlüssel auf die zugehörige Kategorie (`management_shopping_categories`).
  - `name`: Name des Artikels.
  - `status`: Status des Artikels (Standard-Werte: `needed` für benötigt, `stocked` für eingekauft/vorrätig).
  - `last_purchased_at`: Zeitstempel der letzten Anschaffung.
  - `purchase_count`: Anzahl der getätigten Käufe (Zähler zur Bedarfsanalyse).

---

## 3. Kernfunktionen & Datenfluss

### 3.1 Tab-Steuerung und Datenabfrage
- Die Ansicht besitzt zwei Haupt-Tabs (`needed` und `all` / `categories`):
  - **Benötigt (`needed`):** Zeigt alle Artikel mit dem Status `needed`, gruppiert nach ihren Kategorien. Zudem wird eine Liste der 10 am längsten vorrätigen Artikel (`oldestItems`) geladen (`last_purchased_at ASC`), um dem Nutzer Vorschläge für anstehende Nachbestellungen zu machen.
  - **Alle (`all`):** Listet alle Artikel auf, sortiert nach dem Kaufdatum, um eine komplette Übersicht des Bestands und der Einkaufshistorie zu bieten.

### 3.2 Artikel- und Bestandsverwaltung
- **Schnelles Hinzufügen (`addItem`):** Erstellt einen neuen Artikel in einer ausgewählten Kategorie mit dem Status `needed`. Falls bereits ein gleichnamiger Artikel existiert, wird dessen Status wieder auf `needed` zurückgesetzt (Reaktivierung), anstatt ein Duplikat anzulegen.
- **Status umschalten (`toggleItemStatus`):** Klickt der Nutzer auf einen Artikel, wird dessen Status zwischen `needed` und `stocked` umgeschaltet. Beim Wechsel zu `stocked` wird:
  - Der aktuelle Zeitstempel in `last_purchased_at` eingetragen.
  - Der Kaufzähler `purchase_count` inkrementiert.
- **Löschen von Artikeln (`deleteItem`):** Entfernt das Element permanent aus der Datenbank.

### 3.3 Kategorie-Management
- **Kategorie erstellen (`addCategory`):** Ermöglicht das Anlegen einer neuen Kategorie inklusive der Zuweisung eines Icons aus einer vordefinierten Icon-Auswahl (`availableIcons`).
- **Kategorie löschen (`deleteCategory`):** Löscht eine Kategorie. Zuvor werden alle ihr zugeordneten Artikel automatisch auf `category_id = null` gesetzt ("Ohne Kategorie"), um Datenverluste zu vermeiden.
