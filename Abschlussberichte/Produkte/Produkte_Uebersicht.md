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

### 3.6 Reiter „Produkt-Ideen“ (`ProductIdeas` & `ProductIdea` – Sortiments- & Set-Planung vor Live-Schaltung)
- In der zentralen Produkt-Suite (`/admin/products?section=ideas`, direkt neben *Kollektion*, *Verpackung & LUCID*, *Lieferanten* und *Gravur-Vorlagen*) steht der 5. Reiter **`Produkt-Ideen`** ([`ProductIdeas.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductIdeas.php), [`product-ideas.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/product-ideas.blade.php), Modell [`ProductIdea.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Product/ProductIdea.php), Seeder [`ProductIdeaSeeder.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/database/seeders/ProductIdeaSeeder.php)) zur Verfügung.
- **Zweck:** Neue Produkte (insbesondere hochpreisige Echtgold-/Echtsilber-Sets, für die erst echte Muster bestellt und Original-Produktfotos/Videos ohne KI-Bilder sowie Influencer-Samples produziert werden müssen) werden hier vorab sauber mit allen Einzel-Komponenten, Artikelnummern, Direkt-Kauflinks und einer Live-Margenkalkulation gepflegt, bevor sie aktiv in die Shop-Kollektion übernommen werden.
- **Top 1 Produkt-Favorit (`is_top_priority = true`, `rank = 1`):**
  - *„Das Echtgold Seelen-Herz – 585er Gold-Herz & 333er Gold-Panzerkette (Komplett-Set)“*
  - Hinterlegte Einzel-Komponenten (`components_links`):
    1. **Herz-Anhänger:** 585er Echtgold (13 × 14 mm, 0,80 g, Art.-Nr. `235559.400`, `134,00 € brutto` / `112,61 € netto`)
    2. **Passende Halskette im Set:** 333er Echtgold Panzerkette (0,8 mm fein, `69,00 € brutto` / `57,98 € netto`)
  - **Kalkulation:** Gesamt-Rohling-EK `203,00 € brutto` (`170,59 € netto`) + `1,06 € netto` Westpack-Schmuckbox + `4,50 € netto` versicherter DHL-Versand $\rightarrow$ Empfohlener Set-VK: **`349,00 € brutto`** (**`+109,69 € Netto-Reingewinn`** pro verkauftem Set; Solo-Anhänger ohne Kette optional für **`249,00 € brutto`** mit **`+86,93 € Netto-Reingewinn`**).

### 3.7 Optionale Digitale Begleitdokumente für Physische Produkte (Echtheitszertifikat, Pflegehinweise & Manufaktur-Zertifikat)
- **Fachliches Konzept:** Unabhängig von reinen Digital-Produkten (`type = 'digital'`) kann jedem physischen Produkt (`type = 'physical'`) optional eine beliebige Anzahl digitaler Begleitdokumente (PDF, ZIP, RAR, JPG, PNG, WEBP bis 50 MB) hinterlegt werden (z. B. ein **Echtheitszertifikat** bei Echtgold-/Kristall-Unikaten oder **Pflegehinweise**).
- **Admin-Bereich (`/admin/products` $\rightarrow$ Schritt 2 „Medien“):**
  - Direkt unterhalb der Bereiche *Produktbilder* und *Produktvideos* in [`_edit_step_2.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/partials/edit-partials/_edit_step_2.blade.php) befindet sich der Bereich **Digitale Dokumente** mit `!`-Hover-Tooltip und 1-Klick-Schnelltiteln (`+ Echtheitszertifikat`, `+ Pflegehinweise`, `+ Manufaktur-Zertifikat`).
  - Hochgeladene Dateien werden verschlüsselt auf der privaten `local`-Disk unter `storage/app/private/produkte/products-secure/{slug}/documents/` abgelegt und in `configurator_settings['digital_documents']` gespeichert (`ProductCreate::updatedNewDigitalDocuments()`, `updateDigitalDocumentTitle()`, `removeDigitalDocument()`, `downloadDigitalDocumentAdmin()`).
- **Bestell-Snapshot & Rückwirkende Bereitstellung (`OrderOrderItem::resolveDigitalDocuments()`):**
  - Beim Kaufabschluss ([`HandlesOrderCreation.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Order/OrderCheckout/Traits/HandlesOrderCreation.php)) werden die hinterlegten Produktdokumente in `order_order_items.configuration['digital_documents']` eingefroren.
  - Zusätzlich kombiniert `OrderOrderItem::resolveDigitalDocuments()` den Kaufzeitpunkt-Snapshot mit `Product::getDigitalDocuments()`, sodass auch nachträglich am Produkt hinterlegte Zertifikate sofort in bestehenden Bestellungen erscheinen.
- **Geschützter Kundenportal-Download (`/orders` $\rightarrow$ „Dokumente“) & E-Mail-Hinweis:**
  - In der Bestellansicht des Kundenportals ([`customer-orders-component.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/customer/customer-orders-component.blade.php)) erscheinen alle digitalen Begleitdokumente direkt unter **Dokumente**.
  - Die Auslieferung erfolgt über den gehärteten [`DigitalProductDownloadController`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/Customer/DigitalProductDownloadController.php) (`GET /digital-product/{item}/download?doc={docId}`) mit Eigentümer-Prüfung, Zahlungsfreigabe (`payment_status === 'paid'`), Path-Traversal-Schutz und `X-Content-Type-Options: nosniff`.
  - In den Bestell-, Zahlungs- und Versand-E-Mails ([`new_order_mail_to_customer.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/global/mails/new_order_mail_to_customer.blade.php), [`payment_received.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/global/mails/payment_received.blade.php), [`new_order_shipped_to_customer.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/global/mails/new_order_shipped_to_customer.blade.php)) wird automatisch ein Hinweis eingeblendet, sobald `$data['has_digital_documents']` aktiv ist.
- **Automatisierte Tests:** Vollständig abgesichert durch [`ProductDigitalDocumentsTest.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/tests/Feature/Order/ProductDigitalDocumentsTest.php).

### 3.8 3-Welten-Kollektions-Weiche & Wert-Gruppierung (`collection_line`: `manufaktur` | `atelier` | `voucher`)
- **Markenpsychologisches Prinzip (Schutz vor Preis-Entwertung):**
  Damit 29,90 € – 69,90 € Alltags-Unikate (Edelstahl/Alu/Weizenglas), 129,00 € – 349,00 € Echtgold-/Echtsilber-Schmuckstücke und digitale Geschenkgutscheine niemals ungefiltert nebeneinander im selben Raster angezeigt werden, trennt das Datenfeld `products.collection_line` das Sortiment strikt in **3 isolierte Shop-Welten**:
  1. **`'manufaktur'` (`✨ Manufaktur-Kollektion` – Standard-Einstieg auf `/shop`):** Physische Alltags-Unikate (Kristall, Edelstahl, Eloxal-Alu, Weizenglas).
  2. **`'atelier'` (`👑 Atelier Echtgold & Silber`):** Exklusiver Premium-Bereich für massives 333er/585er Echtgold und 925er Sterlingsilber (eigener Hero-Header, `👑 Atelier`-Badge und würdevolle Vorschau-Ansicht, solange das erste Echtgold-Unikat noch in den `Produkt-Ideen` vorbereitet wird).
  3. **`'voucher'` (`🎁 Geschenkgutscheine`):** Eigene Kollektions-Welt für digitale PDF-Gutscheine und physische Gold-Gutscheinkarten im Wachssiegel-Umschlag.
- **Shop-Frontend (`/shop` $\rightarrow$ [`ProductFrontendFilterArea.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductFrontendFilterArea.php) & [`product-frontend-filter-area.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/product-frontend-filter-area.blade.php)):**
  - Die 3-Welten-Weiche (`wire:click="selectCollectionWorld('manufaktur'|'atelier'|'voucher')"`) ersetzt den alten technischen Filter (`Alle | Physisch | Digital | Service`).
  - Beim Wechsel der Welt passen sich Hero-Titel, Untertitel, SEO-Metadaten und Kategorie-Pills dynamisch an die jeweilige Kollektions-Welt an.
- **Admin-Steuerung (`/admin/products` $\rightarrow$ [`ProductCreate.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCreate.php), [`_edit_step_1.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/partials/edit-partials/_edit_step_1.blade.php), [`_list.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/partials/_list.blade.php)):**
  - In Schritt 1 (Unter-Schritt 1: *Produkttyp wählen*) kann für jedes physische Produkt per Radio-Kachel zwischen **`✨ Manufaktur-Kollektion`** (`manufaktur`) und **`👑 Atelier Echtgold & Silber`** (`atelier`) gewählt werden.
  - In der Produktübersicht kann über das Filter-Dropdown gezielt nach `Nur Manufaktur-Kollektion` oder `Nur Atelier Echtgold & Silber` gefiltert werden; Atelier-Produkte tragen das goldene `👑 Atelier`-Badge.
  - Auch im **1-Klick KI-Generator (`Neu (KI)`)** steht die Auswahl `Kollektions-Bereich im Shop (3-Welten-Weiche)` (`🤖 KI-Auto` | `✨ Manufaktur-Kollektion` | `👑 Atelier Echtgold & Silber`) bereit; bei `🤖 KI-Auto` erkennt `inferFromQuickAiUrl()` anhand von Domain (`silberketten-store.de`) und Edelmetall-Stempeln (`333`, `585`, `925`, `Echtgold`, `Sterlingsilber`) automatisch den Bereich `atelier` inkl. Westpack-Schmuckbox und versichertem DHL-Paketversand.
  - **Natürliches Hell-Beige-Design im Shop-Frontend:** Aktive Reiter der 3-Welten-Kollektions-Weiche sowie Produkt-Badges (`GUTSCHEIN`, `ATELIER`, `VORBESTELLBAR`) nutzen statt harter schwarzer KI-Boxen einen warmen, edlen Beigeton (`bg-[#F5F1E8]` mit `border-[#D8C9A8]`) und klare schwarze Schrift (`text-gray-900`).
- **Aktives Atelier-Produkt (`PRODUKT 7` in [`ProductSeeder.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/database/seeders/ProductSeeder.php)):**
  - **`Das Echtgold Seelen-Herz (585er Gold & 333er Goldkette)`** (`slug: 'echtgold-seelen-herz'`, `SKU: MSF-ECHTGOLD-HERZ-001`, `collection_line: 'atelier'`, VK `349,00 € brutto`, EK `170,59 € netto` / `203,00 € brutto` bei [`Silberketten-Store.de`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/database/seeders/ProductSupplierSeeder.php), Startbestand `0`, 2D/3D `.glb`, 8 Anlass-Vorlagen und technisches Maßblatt auf letzter Galerie-Position).
- **Verifizierte Gravur-Armbänder für `Atelier Echtgold & Silber` in [`ProductIdeaSeeder.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/database/seeders/ProductIdeaSeeder.php):**
  1. *Das Silber-Seelenband – Figaro-Gravurarmband 4,0 mm (925 Sterling Silber, Platte 40 × 4 mm)*: EK `22,90 € brutto` (`19,24 € netto`) $\rightarrow$ VK **`89,90 € brutto`** (**`+50,75 € Netto-Gewinn`**).
  2. *Das Partner- & Herren-Seelenband – Massives Panzer-ID-Armband 5,5 mm (925 Sterling Silber, Platte 40 × 5,5 mm)*: EK `49,00 € brutto` (`41,18 € netto`) $\rightarrow$ VK **`129,00 € brutto`** (**`+61,66 € Netto-Gewinn`**).
  3. *Das Vergoldete Kugel-Seelenband – Elastic Elements Gravurarmband (925 Silber vergoldet, 7,5 g)*: EK `49,90 € brutto` (`41,93 € netto`) $\rightarrow$ VK **`139,00 € brutto`** (**`+69,31 € Netto-Gewinn`**).
  4. *Das Echtgold Tauf- & Geburts-Seelenband – Gravurarmband (333er & 585er Echtgold, Platte 25 × 5 mm)*: EK `179,00 € brutto` (`150,42 € netto`) $\rightarrow$ VK **`299,00 € brutto`** (**`+95,29 € Netto-Gewinn`**).
