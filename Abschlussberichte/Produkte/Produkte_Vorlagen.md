# System-Dokumentation: Produkte / Vorlagen (Design- & Produkt-Templates)

Das Vorlagen-Modul verwaltet vordefinierte Design- und Personalisierungs-Templates für physische Produkte (z. B. Gravur-Vorlagen für Geburtstage oder Weihnachten). Es erlaubt Kunden im Shop, auf fertige Gestaltungen zurückzugreifen und diese individuell anzupassen.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Erhöhung der Conversion-Rate durch Bereitstellung von Design-Inspirationen und fertigen Vorlagen, die Kunden mit eigenen Texten/Fotos personalisieren können.
- **Anbindung an Feiertage:** Zuordnung von Vorlagen zu saisonalen Anlässen (z. B. `weihnachten`, `ostern`, `muttertag`) zur dynamischen Sortierung und Bewerbung im Frontend.
- **Verknüpfung:** Vorlagen sind immer einem personalisierbaren Hauptprodukt zugeordnet (`is_personalizable = true`).

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`ProductTemplates`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductTemplates.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Produkte`)
- **Traits:** `WithFileUploads` (für Vorschaubilder der Vorlagen)

### 2.2 Datenbank-Modell
- **`App\Models\Product\ProductTemplate`:**
  Repräsentiert die Design-Vorlage.
  - `product_id`: Referenz auf das zugehörige personalisierbare Produkt.
  - `name`: Anzeigename der Vorlage (z. B. "Bester Papa der Welt").
  - `preview_image`: Eigener Upload für die Design-Vorschau (gespeichert in `produkte/product-templates/`).
  - `configuration`: JSON-Objekt mit Koordinaten, Schriftarten, vordefinierten Texten und Bildplatzhaltern der Personalisierung.
  - `is_active`: Sichtbarkeit der Vorlage im Konfigurator.
  - `holiday`: Text-Schlagwort zur saisonalen Zuordnung.

---

## 3. Kernfunktionen & Datenfluss

### 3.1 Template-Workflow & Zuweisung
- **Erstellung:** Über `createNew()` wechselt die Komponente in den Auswahlmodus. Es werden nur physische Produkte aufgelistet, die als personalisierbar (`is_personalizable = true`) und aktiv markiert sind.
- **Konfiguration (`handleTemplateSaved`):**
  - Die Design-Konfiguration wird per Livewire-Event `save-template-data` übergeben.
  - Wenn ein eigenes Vorschaubild hochgeladen wird, wird es im Speicher abgelegt. Andernfalls greift das System auf das Standardbild des Hauptprodukts zurück.

### 3.2 Bereinigung von Mediendateien (Fix zur Müllvermeidung)
- Beim Bearbeiten (`handleTemplateSaved`) oder Löschen (`delete()`) von Vorlagen wird geprüft, ob ein eigenes Vorschaubild existiert:
  - Das System gleicht ab, ob der Pfad mit `produkte/product-templates/` beginnt.
  - Dadurch wird sichergestellt, dass **nur** das spezifische Vorlagen-Bild gelöscht wird, während das originale Produktbild im Dateisystem unberührt bleibt.

### 3.3 Automatischer Saison-Fokus, Ganzjahres-Klassiker & Toggle „Alle Vorlagen anzeigen“
- **Zentrale Klassifizierung auf [`ProductTemplate`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Product/ProductTemplate.php):**
  - Kalender-gebundene Feiertage (`Weihnachten`, `Ostern`, `Valentinstag`, `Muttertag`, `Vatertag`, `Halloween`, `Neujahr`/`Silvester`, `Weltfrauentag`, `Einschulung`) und Jahreszeiten (`Frühling`, `Sommer`, `Herbst`, `Winter`) werden gekoppelt an `Product::resolveActivePrimarySeasonalSlot()` und den [`HolidayOfferAutopilotService`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/Marketing/HolidayOfferAutopilotService.php) automatisch auf aktuelle Relevanz geprüft (`35` Tage Vorlauf vor dem Fest; `Weihnachten` nahtlos vom `01.11.` bis `26.12.`; `Neujahr` vom `27.12.` bis `08.01.`; `Einschulung` vom `15.07.` bis `30.09.`).
  - Zeitlose Ganzjahres-Klassiker (`Geburtstag`, `Hochzeit`, `Jubiläum`, `Jahrestag`, `Taufe`, `Geburt`, `Trauer`, `Gedenken`, `Freundschaft`, `Liebe`, `Dankeschön`, neutrale/leere Werte sowie individuelle freie Anlassnamen) sind 365 Tage im Jahr sichtbar.
- **Verkaufspsychologische Sortierung (`filterAndSortForStorefront`):**
  1. Platz 1: Aktiver bzw. kommender Haupt-Feiertag (z. B. `Weihnachten` im November/Dezember).
  2. Platz 2: Aktuelle Jahreszeit (`season_slot`).
  3. Platz 3: Zeitlose Ganzjahres-Klassiker (`Geburtstag`, `Hochzeit` usw.).
  4. Platz 4 (nur aufgeklappt): Außerhalb der Saison liegende Feiertags-Vorlagen.
- **Clean-Design-Toggle auf Produktseite & im Angebotskalkulator:**
  - In [`ProductShow`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductShow.php) ([`right.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/partials/product-show/right.blade.php)) und [`ProductCalculator`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductCalculator/ProductCalculator.php) ([`product-calculator.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/product-calculator/product-calculator.blade.php)) blendet ein dezenter Link unter dem Grid (`Alle Vorlagen anzeigen (X)` / `Nur aktuelle Saison anzeigen`) bei Bedarf auch außer-saisonale Vorlagen ein.
  - **Deep-Link-Garantie (`?t=<id>`):** Direktlinks auf aktive Vorlagen funktionieren 365 Tage im Jahr unabhängig vom Kalenderdatum.
- **Synchronisierter Shop-Anlassfilter & Admin-Badge:**
  - Auf `/shop` ([`ProductFrontendFilterArea`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductFrontendFilterArea.php)) zeigt das Anlass-Dropdown standardmäßig nur aktuell relevante Feiertage und Ganzjahres-Anlässe (aktiv per URL gesetzte `filterHoliday`-Parameter bleiben erhalten).
  - Unter `/admin/product-templates` ([`product-templates.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/product/product-templates.blade.php)) bleiben alle Vorlagen sichtbar und tragen bei saisonalen Anlässen ein kompaktes Status-Badge (`Saison aktiv` bzw. `Außer Saison`).

