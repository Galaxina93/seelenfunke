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
- **Klasse:** [`ProductTemplates`](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductTemplates.php)
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
