# Architektur-Report: SystemCompanyMap (Architektur-Map)

## 1. Einleitung
Die **SystemCompanyMap** (Architektur-Map) war ein interaktives Tool im ERP-Dashboard "Mein-Seelenfunke", mit dem die System-Infrastruktur visuell als Node-basierte Flow-Map dargestellt wurde. Sie visualisierte sowohl das ERP-Ökosystem (externe API-Anbindungen) als auch die KI-Architektur in Form von verbundenen Knoten.
Diese Komponente wurde inzwischen **deprecated** und aus der Codebase entfernt, da ihre relevanten Informationsinhalte direkt als maschinenlesbarer Text in die KI-Wissensdatenbank (`AiKnowledgeBaseSeeder`) überführt wurden.

## 2. Technischer Aufbau der Komponente

Die Komponente bestand im Wesentlichen aus einem **Livewire-Backend** und einem hochdynamischen **Alpine.js-Frontend** zur Darstellung von Drag & Drop, Panning und Zooming auf einem HTML5-Canvas-ähnlichen SVG-Grid.

### 2.1 Backend (Livewire & Eloquent)
- **Livewire-Controller (`SystemCompanyMap.php`):**
  Lief im Full-Page-Modus und versorgte das Frontend mit den aktuellen Knoten (`nodes`) und Verbindungen (`edges`). Er bot Funktionen wie das Erstellen, Verschieben (`updateNodePosition()`) und Löschen von Knoten sowie das Testen von API-Latenzen im Hintergrund.
- **Datenbankmodelle:**
  - `SystemMapNode`: Speichert X/Y Koordinaten in Prozent, Labels, Icons und Kategorien (`core`, `api`, `finance`, `sales`).
  - `SystemMapEdge`: Speichert Quell- und Ziel-Knoten (`source_id`, `target_id`), Beschreibung und Verbindungsstatus (z. B. `active`, `inactive`, `planned`).
- **Seeding (`SystemMapSeeder.php`):**
  Befüllte die Tabelle standardmäßig mit fixen X/Y-Koordinaten (z. B. Google API, Stripe, DATEV, Mittwald) für ein ansprechendes visuelles Erst-Layout.

### 2.2 Frontend (Blade & Alpine.js)
Das Frontend (insbesondere `scripts.blade.php`) verwendete Alpine.js, um eine native Drag- und Zoom-Logik zu implementieren, ohne schwere Bibliotheken wie React Flow oder D3.js nutzen zu müssen.

- **Zoom & Pan (`onZoom`, `startPan`):**
  Durch das Rad der Maus (`e.deltaY`) wurde ein Skalierungsfaktor (`scale`) berechnet. Die Ansicht (Panning) wurde mit Maus/Touch-Gesten verschoben. Die logischen Punkte der Knoten wurden dynamisch umgerechnet, sodass der Zoom exakt auf die Cursor-Position zentriert blieb.
- **Node Dragging (`startDragNode`, `onMove`):**
  Knoten konnten via Drag & Drop auf dem Raster frei verschoben werden. Alpine berechnete dabei Pixelverschiebungen in prozentuale X/Y-Werte (`0-100%`) um, um sicherzustellen, dass die Karte auf allen Bildschirmgrößen konsistent blieb.
- **Verbindungen / SVG Path Routing (`calculatePath`):**
  Verbindungen zwischen Knoten wurden nicht per Canvas API gezeichnet, sondern mit nativen SVG `<path>` Elementen. Für saubere Kurven (insbesondere für Back-Flows) wurde eine dynamische Bezier-Kurven-Logik (`M x1,y1 Q x_mid,y_mid x2,y2`) angewendet.

## 3. Entfernung und Migration

Die Komponente wurde aus dem System entfernt. Die entscheidenden Gründe dafür waren:
1. **Redundanz:** Die visualisierten API-Strukturen und der KI-Workflow änderten sich selten.
2. **KI-Training:** Eine visuelle SVG-Karte ist für die Sprach-KI nur bedingt verständlich. Die reinen Fakten (welche API tut was, wie ist der KI-Workflow) haben einen weitaus höheren Wert in reiner Textform (Markdown).
3. **Komplexität:** Der Wartungsaufwand für die aufwändige Touch- und Drag-Logik in Alpine.js war angesichts des eher dekorativen Nutzens zu hoch.

### Datenmigration
Die in der `SystemMapSeeder` festgehaltenen Knoten-Strukturen (Mittwald, Google API, DATEV, Stripe, etc.) sowie der exakte Ablauf des KI-Workflows (User -> JS -> Livewire -> Agent -> LLM) wurden sauber in das Textformat extrahiert und in den `AiKnowledgeBaseSeeder` migriert.
Dadurch kann die KI nun selbst direkte Auskunft über die System- und Schnittstellen-Architektur geben, ohne dass eine dedizierte Map-Ansicht benötigt wird.

**Gelöschte Dateien:**
- `app/Models/System/SystemMapEdge.php`
- `app/Models/System/SystemMapNode.php`
- `app/Livewire/Shop/System/SystemCompanyMap.php`
- `database/seeders/SystemMapSeeder.php`
- Diverse Frontend-Komponenten im Ordner `resources/views/livewire/shop/system/system-map-partials` und das Haupttemplate.
