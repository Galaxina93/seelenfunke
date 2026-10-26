# System-Dokumentation: Produkte / Produktübersicht (Produkt-CRUD & Varianten)

Die Produktübersicht und das Produkt-Management bilden das Herzstück des E-Commerce-Katalogs. Es ermöglicht die Erfassung von physischen und digitalen Artikeln, die Strukturierung über Kategorien und Attribute, die Verwaltung komplexer Produktvarianten sowie Staffelpreise und Steuersätze.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Zentrale Verwaltung des gesamten Verkaufs- und Produktionssortiments.
- **Varianten-Management:** Abbildung unterschiedlicher Größen, Farben, Materialien oder Personalisierungsoptionen.
- **Preiskalkulation:** Steuerung von Netto- und Bruttopreisen, Mehrwertsteuersätzen (Standard/Ermäßigt) und Mengenrabatten (Staffelpreise).

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponenten
- **Klassen:**
  - `ProductCreate`: Große Wizard-Komponente zur Anlage und Bearbeitung von Produkten (inklusive Medien-Uploads, Beschreibungen und SEO-Metadaten).
  - `ProductVariants`: Verwaltet Variantenkombinationen und abweichende Preise/Lagerbestände für Varianten.
  - `ProductAttributes`: Steuert globale Attribute (z. B. "Material", "Gravurtyp").
  - `ProductCategories`: Organisiert die hierarchische Kategoriestruktur.
  - `ProductTierPricing`: Verwaltet Staffelpreise (z. B. Rabatte ab 10, 50, 100 Stück).
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Produkte`)

### 2.2 Relevante Datenbank-Modelle
- **`App\Models\Product\Product`:**
  Der Hauptdatensatz eines Produkts.
  - `sku`: Unique Stock Keeping Unit (Artikelnummer).
  - `type`: Produkttyp (`physical` oder `digital`).
  - `price`: Bruttoverkaufspreis in Cents.
  - `purchase_price`: Einkaufspreis in Cents.
  - `quantity`: Aktueller Lagerbestand.
  - `status`: Sichtbarkeit im Shop (`draft`, `active`, `archived`).
- **`App\Models\Product\ProductVariant`:**
  Repräsentiert eine konkrete Ausprägung eines Produkts (z. B. "Holzart: Eiche").
  - `sku`, `price`, `quantity`: Überschreibt bei Bedarf die Werte des Hauptprodukts.
- **`App\Models\Product\ProductAttribute` & `ProductAttributeValue`:**
  Definiert Filter- und Auswahlkriterien.
- **`App\Models\Product\ProductTierPrice`:**
  Definiert Preisschwellen für Mengenrabatte.

---

## 3. Kernfunktionen & Datenfluss

### 3.1 Produkt-CRUD Workflow
- **Erstellung (`ProductCreate`):** Ein mehrstufiges Formular validiert die Pflichtangaben (Name, SKU, Preis, Mehrwertsteuer). Bilder werden im öffentlichen Storage unter `products/images` abgelegt.
- **Lagersteuerung:** Das Modell verfügt über die Hilfsmethoden `reduceStock($quantity)` und `increaseStock($quantity)`, um Bestandsveränderungen thread-sicher über die Datenbank auszuführen.

### 3.2 Varianten-Generierung
- Im Modul `ProductVariants` können Attribute kombiniert werden (z. B. Material $\times$ Größe).
- Das System generiert daraus kartesische Produkte als Varianten-Vorlagen, die anschließend mit individuellen Preisen, SKUs und Lagerbeständen versehen und gespeichert werden.

### 3.3 Steuer- und Staffelpreis-Berechnung
- **Mehrwertsteuer (`ProductTax`):** Produkte sind Steuersätzen zugeordnet (z. B. 19% Standard, 7% Ermäßigt). Das System rechnet im Frontend und Backend automatisch zwischen Netto- und Bruttopreisen um.
- **Staffelpreise (`ProductTierPricing`):** Bei der Warenkorbberechnung prüft das System, ob Staffelpreise für das Produkt vorliegen. Überschreitet die Menge eine Schwelle, greift der reduzierte Stückpreis.
