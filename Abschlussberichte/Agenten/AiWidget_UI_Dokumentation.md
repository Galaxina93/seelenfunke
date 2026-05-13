# AI Widget Dokumentation & Architektur-Map

Diese Dokumentation beschreibt den Aufbau, die Architektur und die Funktionsweise der zentralseitigen AI Widget-Komponente ("FunkiView") in Seelenfunke. 
Aufgrund der hohen Komplexität (Alpine.js State, Livewire, Three.js 3D-Engine, Mapbox 2D/3D Layer, Web Speech API, WebSockets) wurde die Komponente in mehrere Sub-Dateien aufgeteilt. Dies verhindert Rendering-Fehler im DOM, die durch Konflikte zwischen Alpine.js Teleport (`x-teleport`) und der Three.js Canvas Instanz entstehen können.

## Architekturübersicht

Das AI Widget wird initial über `ai-widget.blade.php` geladen, welches als Hülle für Livewire dient und die Unterkomponenten (`part1` bis `part6`) via Blade `@include` einbindet.
Die UI-Logik ist vollständig in Alpine.js (`funkiView` Data-Objekt) gekapselt, während die 3D-Rendering-Logik in einer dedizierten globalen `window.t3` Struktur gespeichert ist. Die Kommunikation zwischen Backend (Persona / Events) und Frontend erfolgt fast ausschließlich über vom Backend dispatchte `window`-Events.

---

## AI Map: Datei-Struktur & Zuständigkeiten

Die folgenden Dateien bilden das Gesamtsystem des AI Widgets. **Für zukünftige Agenten:** Wenn eine Änderung vorgenommen werden muss, suche anhand dieser Map die zuständige Datei heraus, um Nebenwirkungen in der 3D-Engine oder im State-Management zu vermeiden.

### 1. Einstiegspunkt & Livewire Controller
* **Dateipfad:** `resources/views/livewire/shop/ai/ai-widget.blade.php`
* **Inhalt:** Das absolute Root-Element. Definiert die Livewire-Komponente `<div wire:ignore.self>`. Includiert die restlichen Parts in der korrekten Reihenfolge. Hier sollte **keine** weitere Logik stehen.
* **Dateipfad Backend:** `app/Livewire/Shop/Ai/AiWidget.php`
* **Inhalt:** Die Livewire Backend-Komponente. Kümmert sich um Initialisierung, Chat-Historien und Speicherung der Widget-Konfigurationen in der Datenbank.

### 2. Frontend-Rendering & UI-Overlays (Part 1)
* **Dateipfad:** `resources/views/livewire/shop/ai/ai-widget-part1.blade.php`
* **Inhalt:** Das Haupt-Template der UI. 
  * Definiert den Alpine-Scope: `<div x-data="funkiView(...)">`.
  * **Event-Listener:** Beinhaltet alle wichtigen `@...window` Alpine-Listener (z. B. `@ai-show-persona.window`, `@ai-transform-core.window`, `@ai-toggle-secret-workspace.window`).
  * **UI-Elemente:** Beinhaltet das Side-Dock, den Mapbox-Container, den Canvas-Container für Three.js, sowie alle 2D-Overlays: News Widgets, YouTube Pool, Persona Pool (Geheimdienst-Akten) und das "Top Secret" String-Board.

### 3. State-Management & Kern-Logik (Part 2)
* **Dateipfad:** `resources/views/livewire/shop/ai/ai-widget-part2.blade.php`
* **Inhalt:** Das Gehirn der UI. Definiert das JavaScript Alpine `funkiView` Data-Objekt.
  * **State:** Beinhaltet reaktive Variablen wie `isJarvis`, `isSecretMode`, `intelWidgets`, `activeAgentId`, etc.
  * **Funktionen:** Hier liegen die Methoden für die Interaktion, wie `sendToAI()` (REST Chat API), `toggleLiveMode()`, Audio-Steuerung und `updateJarvisMode()`.

### 4. Speech Recognition & Synthesis (Part 3)
* **Dateipfad:** `resources/views/livewire/shop/ai/ai-widget-part3.blade.php`
* **Inhalt:** Beinhaltet die Logik für Spracherkennung (`window.SpeechRecognition`) und Sprachsynthese (`window.speechSynthesis`).
  * Kümmert sich um Push-to-Talk, kontinuierliches Zuhören und die Transformation von Audio-Chunks.
  * *Hinweis: Wird perspektivisch von WebSockets im LiveMode (Part 6) abgelöst oder ergänzt.*

### 5. Mapbox 3D Engine & Geolocation (Part 4)
* **Dateipfad:** `resources/views/livewire/shop/ai/ai-widget-part4.blade.php`
* **Inhalt:** Setup für die Mapbox GL JS Karte.
  * Flugsicherungsdaten (Live Flight Radar), Map-Styles (Dark Cyber, Satellite), Custom Markers, Kamerafahrten (`flyTo`).

### 6. Three.js 3D Kern (Part 5)
* **Dateipfad:** `resources/views/livewire/shop/ai/ai-widget-part5.blade.php`
* **Inhalt:** Die native WebGL/Three.js Engine.
  * Initialisiert `t3` (Scene, Camera, Renderer).
  * Enthält den extrem komplexen Raymarching Fragment-Shader für die pulsierende 3D-Kugel.
  * **Jarvis Transformation:** Wechselt dynamisch zwischen Shader-Geometrie und der `IcosahedronGeometry` (Drahtgitter-Box) über Alpine-Aufrufe. *Achtung: Änderungen am Canvas oder WebGL-Kontext müssen hier durchgeführt werden.*

### 7. LiveMode WebSockets & Audio Streams (Part 6)
* **Dateipfad:** `resources/views/livewire/shop/ai/ai-widget-part6.blade.php`
* **Inhalt:** Realtime-Streaming via WebSockets für latenzfreie "Echtzeit"-AI Voice Calls.
  * Verbindet sich über Port 8081 mit dem Node.js Middleware-Server. 
  * Spielt Base64-Audiodaten ab und streamt das Mikrofon live.

### 8. Backend Event Dispatcher (Persona Functions)
* **Dateipfad:** `app/Services/AI/Functions/AiPersonaFuncs.php`
* **Inhalt:** Der Backend Service, in dem die Werkzeuge ("Tools") für den AI Agenten definiert sind.
  * Löst Events wie `ai-toggle-secret-workspace` oder `ai-transform-core` über `Livewire::dispatch()` aus. Diese Events werden in **Part 1** empfangen und in **Part 2/5** ausgeführt.

---

## Interaktionsbeispiel: "Zeige mir den Jarvis Modus"

Um zu verstehen, wie das Widget als System arbeitet, hier der Datenfluss für die Aktivierung des Jarvis-Modus:

1. **User spricht:** "Aktivier den Jarvis Modus." -> Das Audio wird per `sendToAI()` (Part 2) an das Backend gesendet.
2. **Backend (AiPersonaFuncs):** Der Agent entscheidet, das Tool `persona_transform_core` mit `target: 'jarvis'` aufzurufen.
3. **Dispatch:** Das Backend feuert das Event `ai-transform-core` über Livewire an das Frontend.
4. **Listener (Part 1):** `@ai-transform-core.window` fängt das Event ab.
5. **State (Part 1 & 2):** Setzt `isJarvis = true` und ruft `updateJarvisMode()` auf.
6. **Engine (Part 2 -> Part 5):** `updateJarvisMode()` tauscht die `coreMesh.geometry` von der Default Raymarching-Geometrie zur Custom `IcosahedronGeometry` (Jarvis Wireframe). Der 3D Kern verwandelt sich augenblicklich.

## Besondere Hinweise für zukünftige Entwicklungen

1. **x-teleport Konflikte vermeiden:** Three.js und Mapbox mögen es gar nicht, wenn ihre Container-DIVs von Alpine durch `x-show` oder DOM-Ersetzungen neu gerendert werden. Verwende CSS `opacity` und `pointer-events`, um die 3D Layer im Hintergrund ein- und auszublenden, anstatt sie aus dem DOM zu entfernen (oder `x-show` auf Wrapper zu nutzen, die DOM-Knoten entfernen).
2. **Alpine State:** Speichere keine Three.js Objekte (`Scene`, `Renderer`) in Alpine (`this`), da Alpine diese proxied und Proxy-Lese-/Schreibkonflikte oder immense Performance-Einbrüche erzeugt. Speichere sie stattdessen im globalen Scope (z. B. in `let t3 = {}`).
3. **Backend-Frontend Sync:** Jeder Befehl, den die KI ausführt und der das UI verändern soll, MUSS über ein standardisiertes CustomEvent (z. B. `ai-show-xy`) via Livewire dispatched werden, das im Alpine Root (Part 1) deklariert ist.
