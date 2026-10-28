# System-Dokumentation: Produkte / Verpackungsmaterial (Kartonagen, Luftpolsterumschläge & LUCID)

Das Modul Verpackungsmaterial (`/admin/product-packaging`) dient der lückenlosen Erfassung und Konfiguration aller verwendeten Versandverpackungen und Polstermaterialien für physische Artikel. Es bildet die verbindliche Grundlage für die gesetzliche Verpackungsmengen-Meldung (**LUCID / VerpackG**), die Unit-Economics-Margenkalkulation sowie die automatische Versandarten-Erkennung (**Brief / Luftpolsterumschlag vs. DHL Paket**).

---

## 1. Übersicht & Verbindliche Versand-/Verpackungsregeln

- **Ziel:** Lückenlose Dokumentation der in den Verkehr gebrachten Verpackungsmaterialien je Produkt zur Erfüllung gesetzlicher Umweltschutzauflagen (Verpackungsgesetz – VerpackG) sowie präzise Margenberechnung.
- **Keine Geschenkboxen / Samtetuis:** Aus Margen- und Logistikgründen werden Produkte **nicht** in schwarzen Schmuck-/Geschenkboxen oder Samtetuis versendet und dürfen auch auf Produktbildern niemals in solchen Boxen dargestellt werden.
- **Zwei standardisierte Versandprofile im Sortiment:**
  1. **Brief / Luftpolster-Umschlag (`shipping_class = 'brief'`):**
     - Für flache, unempfindliche Produkte bis `100 g` (aktuell: **Der Seelenanhänger**, **Der Seelen-Talisman**, **Die Seelen-Pfote**).
     - **Einkaufsbasis:** *Karton.eu – 400 Stk. Luftpolster-Versandtüten* (`57,16 €` netto / `68,02 €` brutto $\rightarrow$ **`0,14 €` (`14 Cent`) netto pro Stück**).
     - **Tara-Gewicht (`packaging_weight`):** **`15 g`**.
     - **LUCID-Materialaufteilung (`product_packagings`):**
       - `composite` (*Verbundmaterialien* – Luftpolsterversandtasche aus Papier außen + PE-Luftpolsterfolie innen): **`12 g`**
       - `paper` (*Pappe, Papier & Karton* – Adress-/Versandetikett & Beileger): **`3 g`**
  2. **DHL Standard-Paket / Versandkarton (`shipping_class = null`):**
     - Für schwere oder bruchempfindliche Glas-/Kristallprodukte (aktuell: **Der Seelen Kristall**, **Der Feierabend-Funke**).
     - **Einkaufsbasis:** *Karton.eu – 240 Stk. Versandkartons* (`144,00 €` netto / `171,36 €` brutto $\rightarrow$ **`0,60 €` (`60 Cent`) netto pro Stück**).
     - **Tara-Gewicht (`packaging_weight`):** **`150 g`** (*Der Seelen Kristall*) bzw. **`120 g`** (*Der Feierabend-Funke*).
     - **LUCID-Materialaufteilung (`product_packagings`):**
       - `paper` (*Pappe, Papier & Karton* – Versandkarton, Papierklebeband & Lieferschein): **`130 g`** (bzw. **`100 g`** beim Weizenglas)
       - `plastic` (*Kunststoffe* – zusätzliche Luftpolsterfolie zum Bruchschutz): **`20 g`**

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`ProductPackagingConfigurator`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductPackagingConfigurator.php)
- **View:** [`product-packaging-configurator.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/product-packaging-configurator.blade.php)
- **Route:** `/admin/product-packaging` (Department-Theme: `Produkte`)

### 2.2 Datenbank-Modell
- **[`App\Models\Product\ProductPackaging`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Product/ProductPackaging.php):**
  Repräsentiert ein verpackungsrelevantes Material für ein bestimmtes Produkt.
  - `product_id`: Zugeordnetes Produkt.
  - `material_type`: Klassifizierung des Materials über `ProductPackaging::getMaterialTypes()`:
    - `paper` (Pappe, Papier & Karton)
    - `plastic` (Kunststoffe)
    - `glass` (Glas)
    - `wood` (Holz & Kork)
    - `tin` (Eisenmetalle / Weißblech)
    - `alu` (Aluminium)
    - `composite` (Verbundmaterialien – z. B. Luftpolsterversandtaschen Papier/Folie)
    - `other` (Sonstige Materialien, z. B. Naturfasern)
  - `weight_grams`: Gewicht des Materials in Gramm.

---

## 3. Vollautomatische Seeder- & Produkterstellungs-Integration

1. **Standard-Seeding ([`ProductSeeder.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/database/seeders/ProductSeeder.php)):**
   - Beim Ausführen von `ProductSeeder` werden für alle 5 physischen Produkte automatisch die exakten `product_packagings`-Datensätze angelegt, sodass auf `/admin/product-packaging` jedes Produkt den grünen Status **„2 Materialien gespeichert“** aufweist.
2. **Automatische Anlage bei neuen Produkten ([`ProductCreate.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCreate.php)):**
   - Sowohl im **1-Link-KI-Generator (`createQuickAiProduct()`)** als auch beim **manuellen Speichern (`save()`)** eines physischen Produkts ohne bisherige Verpackungsdaten werden die LUCID-Materialeinträge (`product_packagings`), die Versandart (`shipping_class`), die Verpackungskosten (`packaging_cost`) und das Tara-Gewicht (`packaging_weight`) automatisch anhand des Produktgewichts (`<= 100 g` $\rightarrow$ Brief/Luftpolsterumschlag, `> 100 g` $\rightarrow$ Karton/Paket) angelegt.

---

## 4. Aktuelle Verpackungs-Matrix aller physischen Produkte

| Produkt (`slug`) | Produktgewicht (`weight`) | Tara (`packaging_weight`) | Verpackungskosten netto (`packaging_cost`) | Versandart (`shipping_class`) | LUCID-Einträge auf `/admin/product-packaging` |
| :--- | :---: | :---: | :---: | :---: | :--- |
| **Der Seelenanhänger** (`seelen-anhaenger`) | `19 g` | `15 g` | `0,14 €` (`14 ct`) | `brief` (Luftpolster-Umschlag) | `composite: 12 g`, `paper: 3 g` |
| **Der Seelen-Talisman** (`seelen-talisman`) | `24 g` | `15 g` | `0,14 €` (`14 ct`) | `brief` (Luftpolster-Umschlag) | `composite: 12 g`, `paper: 3 g` |
| **Die Seelen-Pfote** (`seelen-pfote-hundemarke`) | `12 g` | `15 g` | `0,14 €` (`14 ct`) | `brief` (Luftpolster-Umschlag) | `composite: 12 g`, `paper: 3 g` |
| **Der Seelen Kristall** (`seelen-kristall`) | `920 g` | `150 g` | `0,60 €` (`60 ct`) | `null` (DHL Standard Paket) | `paper: 130 g`, `plastic: 20 g` |
| **Der Feierabend-Funke** (`weizenglas-personalisiert`) | `450 g` | `120 g` | `0,60 €` (`60 ct`) | `null` (DHL Standard Paket) | `paper: 100 g`, `plastic: 20 g` |
