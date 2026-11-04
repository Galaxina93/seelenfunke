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

### 3.1 Produkt-CRUD Workflow & Verbindliche Artikelnummern-Systematik (SKU)
- **Erstellung (`ProductCreate`):** Ein mehrstufiges Formular validiert die Pflichtangaben (Name, SKU, Preis, Mehrwertsteuer). Zusätzlich steht oben rechts in der Kopfzeile neben `Archiv öffnen` und `+ Neu` der Schnell-Button **`Neu (KI)`** zur Verfügung, der aus einem einzigen Händler-Kauflink vollautomatisch ein verkaufsfertiges Produkt (mit `quantity = 0`, verifizierten Maßen, Versandklasse und LUCID-Verpackungsdaten) anlegt.
- **Einheitlicher SKU-Standard (`<MATERIAL/KATEGORIE>-<FORM/TYP>-<NNN>`):**
  Alle Produkte führen eine dreiteilige, fortlaufend nummerierte Artikelnummer (`001`, `002`, ...) ohne Zufallszahlen:
  - `CRYSTAL-3D-001` (*Der Seelen Kristall*)
  - `ALU-HEART-001` (*Der Seelenanhänger*)
  - `GLASS-WEIZEN-001` (*Der Feierabend-Funke*)
  - `STEEL-BAR-001` (*Der Seelen-Talisman*)
  - `STEEL-PAW-001` (*Die Seelen-Pfote*)
  - `GOLD-HEART-001` (*Das Goldene Seelen-Herz*)
  - `GIFT-VOUCHER-001` (*Geschenkgutschein*)
  Über `ProductCreate::generateNextCleanSku()` und `normalizeCleanSku()` wird bei jeder Neuanlage automatisch die nächste freie dreistellige Nummer vergeben.
- **Lagersteuerung:** Neu angelegte Produkte starten immer mit einem Lagerbestand von **`0 Stk.` (`quantity = 0`)**. Das Modell verfügt über die Hilfsmethoden `reduceStock($quantity)` und `increaseStock($quantity)`, um Bestandsveränderungen thread-sicher über die Datenbank auszuführen.

### 3.2 Varianten-Generierung
- Im Modul `ProductVariants` können Attribute kombiniert werden (z. B. Material $\times$ Größe).
- Das System generiert daraus kartesische Produkte als Varianten-Vorlagen, die anschließend mit individuellen Preisen, SKUs und Lagerbeständen versehen und gespeichert werden.

### 3.3 Steuer- und Staffelpreis-Berechnung
- **Mehrwertsteuer (`ProductTax`):** Produkte sind Steuersätzen zugeordnet (z. B. 19% Standard, 7% Ermäßigt). Das System rechnet im Frontend und Backend automatisch zwischen Netto- und Bruttopreisen um.
- **Staffelpreise (`ProductTierPricing`):** Bei der Warenkorbberechnung prüft das System, ob Staffelpreise für das Produkt vorliegen. Überschreitet die Menge eine Schwelle, greift der reduzierte Stückpreis.

### 3.4 1-Klick Herkunfts-Siegel („Made in Germany“ / „Handveredelt in Deutschland“)
- Direkt in den Produktkacheln & der Listenansicht unter **`/admin/products` („Deine Kollektion“)** befindet sich neben dem „Im Sortiment“- und „Auf Lager“-Button der **1-Klick Herkunfts-Umschalter** (`wire:click="toggleOriginBadge('{{ $prod->id }}')"` in [`ProductCreate.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCreate.php)).
- Jeder Klick schaltet den Zustand in `configurator_settings['origin_badge']` (und synchron in `attributes['Herkunft']`) nahtlos durch 3 Stufen:
  1. **`'made_in_germany'` (`🇩🇪 Made in Germany`):** Für Produkte aus deutscher Rohlings-/Glashüttenfertigung und deutscher Laser-Veredelung (z. B. `Der Feierabend-Funke`, `Das Goldene Seelen-Herz`).
  2. **`'handcrafted_in_germany'` (`🇩🇪 Handveredelt in Deutschland`):** Rechtssichere Kennzeichnung (§ 5 UWG) für importierte Rohlinge, die in unserer deutschen Manufaktur per Laser veredelt und einzelgeprüft werden (z. B. `Der Seelen Kristall`, `Der Seelenanhänger`, `Der Seelen-Talisman`, `Die Seelen-Pfote`).
  3. **`'none'` (`Herkunft: Aus`):** Kein Herkunfts-Badge im Shop-Frontend.
- Im Shop-Frontend wird das aktive Siegel mit Schwarz-Rot-Gold-Icon automatisch auf den **Produktkacheln** ([`product-frontend-filter-area.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/product-frontend-filter-area.blade.php)) und auf der **Produktdetailseite** ([`right.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/partials/product-show/right.blade.php)) ausgespielt.

### 3.5 Drei Personalisierungs- & Verkaufs-Modi (`studio`, `custom_fields`, `standard`) & Dynamischer Eingabefeld-Baukasten
- Im Produkt-Editor Schritt 1 ([`_edit_step_1.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/partials/edit-partials/_edit_step_1.blade.php)) sowie im **`Neu (KI)`**-Modal ([`_list.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/partials/_list.blade.php)) stehen drei Verkaufs- und Personalisierungsmodi zur Auswahl:
  1. **`studio` („🎨 Interaktives 2D/3D-Design-Studio“):** Vollwertiger visueller Konfigurator mit 3D-Three.js-Modell und 2D-Canvas.
  2. **`custom_fields` („⚡ Schlanke Schnell-Personalisierung / Nur Eingabefelder“):** Dynamischer Eingabefeld-Baukasten ohne 3D-Modell-Abhängigkeit. Jedes Feld besitzt eine frei wählbare **Feld-Art** (`text`, `textarea`, **`font` = Schriftart-Auswahl mit allen 22 Konfigurator-Schriften**, `select` = eigenes Auswahl-Dropdown, `date`), einen eigenen **`!`-Ausrufungszeichen-Hilfetext**, eine Zeichenbegrenzung sowie einen optionalen Einzel-Aufpreis.
  3. **`standard` (`is_personalizable = false`, „📦 Fertiges Produkt / Bereit zum Kauf“):** Für vorab fertig gelaserte Artikel zum sofortigen 1-Klick-Kauf.
- Siehe detaillierte technische Dokumentation in [`Produkte_Konfigurator.md` (Kapitel 9)](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/Abschlussberichte/Produkte/Produkte_Konfigurator.md).
