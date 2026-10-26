# System-Dokumentation: Leitung / Linktree

Der Linktree-Manager im Backend dient der dynamischen Pflege der Unternehmens-Link-Landingpage (oft genutzt für Instagram-Biografien oder Kurzbeschreibungen). Er ermöglicht die Verwaltung von Links, das Tracken von Klicks und Seitenaufrufen sowie das Hochladen von Profilbildern und das Anpassen des Themes.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Bereitstellung einer zentralen Landingpage mit allen wichtigen Unternehmenslinks, sozialen Medien und Shop-Aktionen.
- **Analytische Auswertung:** Messung von Klicks und Aufrufen, um die Klickrate (CTR) einzelner Links sowie die globale Performance zu bewerten.
- **Customizing:** Schnelles Ändern von Profilbildern und Theme-Farben direkt aus dem Backend ohne Code-Änderungen.

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`ManagementLinktreeManager`](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Backend/Management/ManagementLinktreeManager.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Leitung`)
- **Traits:** `WithFileUploads` (für Profilbild-Uploads)

### 2.2 Datenbank-Modelle
- **`App\Models\Management\ManagementLinktree`:**
  Repräsentiert die einzelnen Links auf der Landingpage.
  - `title`: Anzeigetext des Links.
  - `url`: Ziel-URL.
  - `icon`: Tailwind- oder SVG-Icon.
  - `type`: Link-Typ (`standard`, `secure`, `highlight` für optisch hervorgehobene Aktionen).
  - `sort_order`: Bestimmt die Reihenfolge der Links (wird per Drag & Drop sortiert).
  - `is_active`: Status, ob der Link auf der Landingpage sichtbar ist.
- **`App\Models\Management\ManagementLinktreeVisit`:**
  Erfasst jeden einzelnen Aufruf (Impression) der Linktree-Landingpage (Gesamtbesucher).
- **`App\Models\Management\ManagementLinktreeClick`:**
  Erfasst jeden Klick auf einen spezifischen Link (verweist per `management_linktree_id` auf das Link-Modell) zur CTR-Berechnung.

---

## 3. Kernfunktionen & Datenfluss

### 3.1 Datenladen & KPI-Berechnung
- Die Methode `loadData()` lädt alle Links inklusive ihrer Klick-Statistiken (`withCount('clicks')`).
- Parallel werden die Gesamtzahl der Besuche (`ManagementLinktreeVisit`) und Klicks (`ManagementLinktreeClick`) gezählt.
- Daraus wird die **globale Click-Through-Rate (CTR)** berechnet: `(Gesamtklicks / Gesamtbesuche) * 100`.

### 3.2 Einstellungs- und Designverwaltung
- **Farbschema:** Über das Feld `themeColor` kann eine primäre Designfarbe festgelegt werden. Diese wird unter dem Konfigurationsschlüssel `linktree_theme_color` in den `SystemSetting` gespeichert.
- **Profilbild-Upload:** Ein neues Profilbild wird hochgeladen, im Storage-Verzeichnis `public/linktree` abgelegt und der relative Pfad als `linktree_profile_image` in den Systemeinstellungen hinterlegt.
- **Cache-Invalidierung:** Nach dem Speichern von Einstellungen wird der globale Shop-Einstellungs-Cache gelöscht (`Cache::forget('global_shop_settings')`), damit Änderungen sofort öffentlich sichtbar sind.

### 3.3 Link-CRUD & Drag & Drop Sortierung
- **Speichern/Bearbeiten (`save`):** Führt Validierungen durch. Ist eine `editId` vorhanden, wird der Link aktualisiert, andernfalls wird er mit einer inkrementierten `sort_order` neu angelegt.
- **Sortierreihenfolge (`updateOrder`):** Die Livewire-Komponente empfängt die per Drag & Drop veränderten Positionen aus dem Frontend und aktualisiert die `sort_order` der Links in der Datenbank entsprechend.
- **Aktivieren/Deaktivieren (`toggleActive`):** Ermöglicht das temporäre Verbergen von Links, ohne diese löschen zu müssen.
