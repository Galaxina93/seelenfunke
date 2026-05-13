# Abschlussbericht: Domain-Driven Design (DDD) & Dynamic Theming Refactoring
**Projekt:** Seelenfunke Shop & Backend
**Datum:** 26. März 2026

---

## 1. Ausgangslage & Zielsetzung
Das Seelenfunke Backend hat in den letzten Monaten massiv an Features gewonnen. Dies führte zu monolithischen Staus in generischen Ordnern wie `Livewire/Shop/` oder `Livewire/Global/`. Gleichzeitig erforderten die neuen KI-Agenten und ihre spezifischen Abteilungen ("Produkte", "Marketing", "Buchhaltung" etc.) eine glasklare Architektur, damit die LLMs (Language Models) nicht halluzinieren, wenn sie auf Datenbank-Entitäten zugreifen.

**Das Ziel dieser Refactoring-Initiative:**
1. **Domain-Driven Design (DDD):** Harte Aufspaltung aller Backend-Funktionen, Livewire-Komponenten, Models und Migrationen in geschäftslogische und streng gekapselte Abteilungs-Namespaces (Domains).
2. **Visuelle Identität:** Einbindung eines flächendeckenden "Dynamic Theming"-Systems, durch das sämtliche UI-Sektionen automatisch die Farbe ihrer zugewiesenen KI-Abteilung in Echtzeit (JIT) erben.

---

## 2. Struktur-Transformationen (Domain-Driven Design)

Sämtlicher Legacy-Code wurde analysiert, umbenannt und in fünf primäre Unternehmens-Abteilungen überführt:

### 🏢 Management (Firmenleitung / CEO Zentrale)
- **Komponenten:** Alle Management-Ansichten (ehemals `CrmInbox`, `ManagementContactsManager`, `DayRoutine`, `AiCeoHealth` etc.) wurden in den Namespace `App\Livewire\Shop\Management` portiert und zur sofortigen Erkennung mit dem Präfix `Management...` versehen (z.B. `ManagementEMails`, `ManagementManagementContacts`).
- **Models & Datenbank:** Zugehörige Modelle wie `Task`, `CalendarEvent` und `ManagementContact` residieren nun isoliert in `app/Models/Management/`. Alle spezifischen Migrationen wurden nach `database/migrations/management/` verlagert.

### 📦 Produkte (Product & PIM)
- **Komponenten:** Die eShop-Verwaltung (`ProductAnalytics`, `ProductCreate`, `ProductFracture`, `ProductSuppliers` etc.) befand sich glücklicherweise bereits in einer vorbildlichen Struktur (`App\Livewire\Shop\Product`).
- **Models & Datenbank:** Um die Kapselung zu vollenden, wurden Basis-E-Commerce-Klassen wie `Category.php` und `ShopAttribute.php` aus dem Root-Verzeichnis nach `app/Models/Product/` migriert. Alle 10 zugehörigen Tabellen-Migrationen wurden analog in `migrations/product/` gebündelt.

### 📢 Marketing
- **Komponenten:** Die ehemals verstreuten Module (Newsletter, Gutscheine, Blog) wurden unter der Marketing-Flagge vereint (`App\Livewire\Shop\Marketing\MarketingNewsletter`, `MarketingVoucher`, `MarketingBlog`).
- **Models & Datenbank:** Vouchers, Coupons und Newsletter-Tabellen wurden restlos aus dem `shop/` Ordner entfernt und liegen nun ordentlich getrennt und strukturiert unter `Marketing/`.

### 🛒 Bestellungen (Order, Quote, Revocations)
- **Komponenten:** Das kommerzielle Zentrum des Shops. Aus alten Klassen wie `Orders`, `QuoteRequests` oder `RevocationIndex` wurden die präzise benannten Controller `OrderOverview`, `OrderQuoteRequests`, `OrderQuoteAcceptance` und `OrderRevocations`.
- **Models & Datenbank:** Widerrufs- (Revocations) und Angebotsmodelle (Quotes) sind nun sauber als Unter-Domains in `app/Models/Order/` verankert, womit die Legacy-Ordner `shop/` und `crawler/` endlich entlastet sind.

### 💸 Buchhaltung (Accounting & Financials)
- **Komponenten:** Die umfassendste Transformation. Das veraltete `Financial`-Präfix wurde komplett gestrichen. Die 8 Hauptkomponenten (inkl. Bank, Steuern, Liquidität, Fixkosten und Rechnungen) lauten nun einheitlich `AccountingBank`, `AccountingTax`, `AccountingEvaluation`, `AccountingInvoice` etc., und befinden sich im Namespace `App\Livewire\Shop\Accounting/`.
- **Models & Datenbank:** Der komplette Namespace `Models\Financial\` wurde zu `Models\Accounting\` konvertiert. Das freischwebende `Invoice.php` Model wurde eingemeindet. Rund ein Dutzend Datenbank-Migrationen rund um Konten, Steuern und Rechnungen liegen nun gekapselt in `migrations/accounting/`.

---

## 3. Dynamic Department Theming (Die visuelle Revolution)

Um die neuen Software-Domains optisch spürbar an ihre KI-Abteilungen zu koppeln, wurde das **Dynamic Theming Pattern** entwickelt und 100% flächendeckend implementiert.

1. **Das `WithDepartmentTheming` Trait**: 
   Diese Livewire-Erweiterung wurde in über 30 Master-Controllern aller Abteilungen integriert. Sie schlägt die Brücke zur KI-Datenbank, liest die hexadezimale Hausfarbe der verknüpften Abteilung (z.B. Gold für Management, Purple für Marketing) aus und stellt diese samt Opazitäts-Schattierungen zur Verfügung.
   
2. **CSS-Variablen Injection**:
   In den Render-Views der Komponenten wird direkt auf Root-Ebene ein dynamischer Style-Block injiziert (z.B. `--theme-color: #C5A059`). 

3. **Tailwind JIT Refactoring**:
   In einer gigantischen "Suchen & Ersetzen"-Operation wurden hunderte von hartkodierten Tailwind-Klassen (`text-primary`, `bg-primary/10`, `border-primary`) projektweit durch dynamische Echtzeit-Verweise ersetzt (`text-[var(--theme-color)]`, `bg-[var(--theme-color-10)]`). 
   
**Das Ergebnis:** Sobald im "KI Organigramm" die Farbe einer Abteilung geändert wird, erstrahlen alle zugehörigen Tool-Dashboards, Tabellen, Charts und Buttons sofort und ohne Neukompilierung des Codes exakt in dieser neuen Farbwelt.

---

## 4. Fazit & Betriebssicherheit

Durch dieses drastische architektonische Refactoring hat das Projekt eine Enterprise-Level Struktur erreicht, die exakt auf die Zukunft skalierbarer AI-Integrationen zugeschnitten ist. 
* Klassenkollisionen und inkonsistente Autoloads wurden durch Cache-Löschungen, striktes Namespace-Typing und Routing-Korrekturen ausgemerzt.
* Der `app/Models/` Ordner ist nicht länger ein unüberschaubares Root-Dateigrab, sondern gliedert sich exakt in die Logik-Silos des Unternehmens ein.
* Die KI-Modelle haben durch das klare Naming ab sofort direkten semantischen Bezug zu dem, woran sie gerade "operieren".

Das System läuft performant, alle Caches sind warm, und die Codebase ist bereit für den Ausbau der nächsten großen AI-Features.
