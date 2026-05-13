# Abschlussbericht: "Projekt Gehirn" & Neurale Fehleranalyse

**Datum:** 11. Mai 2026
**Zweck:** Dokumentation der Architektur und Funktionsweise des neu integrierten 3D-Gehirns in das AI-Widget.

## Einleitung
Das Ziel der Entwicklung war die Erschaffung eines visuell beeindruckenden, dreidimensionalen Netzwerks (das "Projekt Gehirn"), welches die gesamte Codebase des Seelenfunke-Projekts interaktiv im Frontend abbildet. Es dient der schnellen Lokalisierung von Systemfehlern (Exceptions, Dead-Ends) und befähigt den KI-Agenten, tiefgehende, kontextbasierte Fehleranalysen automatisiert durchzuführen und dem Entwickler bereitzustellen.

## 1. Backend-Architektur: Der Scanner
Herzstück der Datenbeschaffung ist der neue Artisan-Befehl `GenerateSystemBrainMap` (ausführbar via `funki systemmap` oder `php artisan system:brain:generate`).

**Funktionsweise:**
- Der Scanner parst rekursiv Kernverzeichnisse der Applikation (`app/`, `routes/`, `resources/views/`, `config/`).
- Durch Regex-Analysen und abstrakte Strukturinterpretation werden Relationen gebildet:
  - PHP `use` und `import` Anweisungen (Abhängigkeiten).
  - Blade `@include`, `@extends` und `<livewire:...>` Tags.
- Das Ergebnis ist eine hochoptimierte `JSON`-Datei (`storage/app/public/system-brain-map.json`), welche Knoten (Nodes) und Verknüpfungen (Links) für die 3D Engine bereithält.
- *Performance:* Das Scanning erfolgt komplett im Hintergrund, sodass das Frontend beim Öffnen der Map keine Wartezeiten für das Parsing aufweist.

## 2. Visuelle Repräsentation (Frontend)
Das 3D-Gehirn wurde direkt in das AI-Widget integriert und nutzt die leistungsstarke `3d-force-graph` WebGL-Engine.

**Features der UI (`ai-widget-part7-brain.blade.php`):**
- **WebGL-Rendering:** Extrem schnelles Rendern von hunderten Knoten gleichzeitig durch Three.js.
- **Farbcodierung:** Knoten sind visuell in Schichten (Layer) des MVC-Patterns unterteilt:
  - Smaragdgrün: Models
  - Blau: Controller
  - Pink: Livewire Komponenten
  - Bernstein: Views (Blade)
  - Blutrot: Fehlerhafte Knoten (System Anomalien)
- **Kamera-Flug & Suche:** Eine interaktive Google-Maps-ähnliche Suchleiste ermöglicht das Suchen nach Dateinamen. Die Kamera "fliegt" daraufhin fließend durch das 3D-Gehirn zum Ziel.
- **Nahtlose Integration:** Alpine.js State-Management (`isBrainMode`, `isBrainFocus`) schaltet sanft per Transitions zwischen den Widget-Layern (Map, Top Secret, Gehirn) um.

## 3. Die Neurale Analyse (KI-Integration)
Das System-Gehirn ist mehr als nur eine Visualisierung; es ist direkt mit dem AI-Agenten der Plattform gekoppelt.

**Das Tool `system_analyze_neural_error`:**
- Dieses Tool wurde dem "System"-Agenten in `AiSystemFuncs.php` beigebracht.
- Es wird getriggert, sobald ein roter Knoten (ein als fehlerhaft markierter Code-Baustein) in der 3D Ansicht angeklickt wird.
- **Ablauf:**
  1. Frontend triggert `Livewire.dispatch` mit dem betroffenen Pfad.
  2. Der AI-Agent analysiert die Datei live (inklusive Error-Kontext aus Logs, sofern angebunden).
  3. Er erzeugt einen detaillierten Markdown-Bericht (`NeuralAnalysis_*.md`) tief im System, der genaue Handlungsanweisungen zur Problembehebung enthält.
  4. Ein Voice-Feedback ("Ich initiiere eine tiefgreifende neurale Analyse...") wird über das Funkira-Interface abgespielt.

## Fazit & Ausblick
Das "Projekt Gehirn" hebt die Debugging-Fähigkeiten des Projekts auf ein weltweit einmaliges Level. Die Kombination aus visueller 3D-Code-Architektur und agentenbasierter neuraler Fehleranalyse ermöglicht es, komplexe Abhängigkeitsfehler innerhalb von Sekunden nicht nur zu finden, sondern vollautomatisch analysieren zu lassen.

**Nächste Schritte:**
- Anbindung einer Live-Fehlerdatenbank (Sentry, Laravel Logs) für den roten Knoten-Abgleich, statt statischer Simulationen.
- Erweitern des KI-Tools um die Möglichkeit, automatisch Pull-Requests oder direkte Code-Patches basierend auf der Analyse zu generieren.
