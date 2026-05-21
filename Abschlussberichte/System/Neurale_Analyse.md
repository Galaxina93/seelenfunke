# Neurale Analyse

Die Neurale Analyse ("Projekt Gehirn") stellt ein innovatives System zur dreidimensionalen Code-Visualisierung und KI-gestützten Fehlerdiagnose im Seelenfunke-Projekt dar. Es bildet die gesamte Codebase als interaktives neuronales Netzwerk ab und verknüpft strukturelle Code-Abhängigkeiten direkt mit agentenbasierter Problemlösung.

## Zielsetzung
Das Modul dient Entwicklern und KI-Agenten zur Analyse von Software-Abhängigkeiten, zum schnellen Auffinden kritischer Systemstellen und zur automatisierten Generierung von Detailberichten zu einzelnen Code-Dateien. Fehlerhafte Komponenten (z. B. fehlerhafte Views oder Controller) werden farblich hervorgehoben (rot) und können direkt einer neuralen Fehleranalyse unterzogen werden.

---

## Beteiligte Komponenten & Klassen

### Datenbank-Modelle
- [SystemNeuralNode](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/System/SystemNeuralNode.php): Repräsentiert einen einzelnen Knoten im neuronalen Code-Netzwerk. Speichert Dateipfad, Name, Dateityp-Gruppe, Abhängigkeiten (als JSON-Array), extrahierte PHP-Methoden (JSON-Array) und den Datei-Hash (`content_hash`) zur Erkennung von Code-Änderungen.

### Livewire-Controller
- [SystemNeuralAnalysisIndex](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Backend/System/SystemNeuralAnalysisIndex.php): Das administrative Dashboard im Backend zur Auflistung, Suche und Filterung aller erkannten System-Knoten. Bietet die Möglichkeit, für einzelne Knoten eine Struktur-Dokumentation im Markdown-Format zu generieren.

### Konsolen-Befehle (Artisan Commands)
- [GenerateSystemBrainMap](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Console/Commands/GenerateSystemBrainMap.php): Scannt das Projekt, parst Abhängigkeiten über Regex, schreibt die 3D-Knotendaten als JSON und befüllt die Datenbanktabelle `system_neural_nodes` neu. Ausführbar über `php artisan system:brain:generate` oder den CLI-Shortcut `funki systemmap`.

---

## Funktionsweise des Code-Scanners (GenerateSystemBrainMap)

Der Scanner durchläuft rekursiv die Verzeichnisse `app/`, `routes/`, `resources/views/` und `config/`. Der Ablauf gliedert sich in folgende Phasen:

```mermaid
graph TD
    A[Artisan system:brain:generate] --> B[Rekursiver Datei-Scan]
    B --> C[Phasen-Parsing & Indizierung]
    
    subgraph Phase 1: Indizierung (Nodes)
        C --> D[Klassennamen / Views erfassen]
        D --> E[Zuordnung zu Gruppe 1-9 & UUID generieren]
    end

    subgraph Phase 2: Relationen (Links)
        E --> F[Scanne use/import-Statements in PHP]
        E --> G[Scanne include/extends/livewire-Tags in Blade]
        F & G --> H[Dedupliziere & Erstelle Link-Graph]
    end

    subgraph Phase 3: Speicherung
        H --> I[Schreibe system-brain-map.json für Frontend]
        I --> J[Befülle system_neural_nodes Datenbank per Bulk-Insert]
    end
```

### Gruppen-Klassifizierung
Jede Datei wird anhand ihres Pfades einer vordefinierten Strukturgruppe zugewiesen:
1. **Allgemein** (Fallbacks)
2. **Models** (Pfad: `app/Models`)
3. **Controllers** (Pfad: `app/Http/Controllers`)
4. **Livewire** (Pfad: `app/Livewire`)
5. **Views** (Pfad: `resources/views`)
6. **Routes** (Pfad: `routes/`)
7. **Config** (Pfad: `config/`)
8. **Services** (Pfad: `app/Services`)
9. **Console/Commands** (Pfad: `app/Console`)

---

## Neurale Fehlerdiagnose & Dokumenten-Generierung

### 1. Interaktives 3D-Gehirn im Frontend
Im Frontend wird über die Bibliothek `3d-force-graph` auf Basis von Three.js (WebGL) ein räumliches Netzwerk erzeugt.
- **Visualisierung**: Jede Strukturgruppe hat eine eigene Farbe (z. B. Smaragd für Models, Pink für Livewire).
- **Fehlerhafte Knoten**: Werden über Log-Abgleiche rot dargestellt. Bei Klick wird das Werkzeug `system_analyze_neural_error` aufgerufen.

### 2. Generierung von Strukturberichten
Der Livewire-Controller [SystemNeuralAnalysisIndex](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Backend/System/SystemNeuralAnalysisIndex.php) stellt die statische Funktion `generateMarkdownForFile()` bereit. 
Wenn ein Benutzer oder ein KI-Agent die Analyse eines Knotens triggert, wird eine Markdown-Datei im Verzeichnis `storage/app/public/agenten/workspace/md/` angelegt (z. B. `Struktur_app_Models_Ai_AiAgent.php.md`). 

Der Bericht enthält:
- Pfad der Datei und Gruppen-Typ.
- Letzter Scan-Hash.
- Vollständige Liste der direkten Code-Abhängigkeiten (Dependencies).
- Alle im Code deklarierten PHP-Methoden (über Regex `(?:public|protected|private)\s+function\s+...` ermittelt).

Dieser Bericht wird anschließend in das Kontextfenster des zuständigen KI-Agenten geladen, um dem Entwickler eine detaillierte Behebungsstrategie vorzuschlagen.
